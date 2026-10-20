<?php
namespace Tests\Integration;

use App\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Separate processes and connections against a disposable InnoDB database. */
final class MySqlClaimConcurrencyTest extends \Tests\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('stellar_claim_test', DB::connection()->getDatabaseName());
        $this->assertSame('mysql', config('database.connections.mysql.host'));
        $this->assertTrue(function_exists('pcntl_fork'), 'pcntl is required; do not silently skip concurrency');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->withoutMiddleware();
    }

    private function requestData(array $changes = []): array
    {
        return array_merge(['provisional_user_id'=>'123', 'token'=>str_repeat('a',64),
            'username'=>'synthetic@example.invalid', 'target_user_id'=>'456'], $changes);
    }

    private function grants(): void
    {
        foreach ([1,2] as $type) {
            Subscription::create(['user_id'=>123, 'type'=>$type, 'status'=>1,
                'expires_at'=>'2028-01-01 00:00:00']);
        }
    }

    private function race(array $operations): array
    {
        $directory = sys_get_temp_dir().'/stellar-claim-'.bin2hex(random_bytes(12));
        mkdir($directory,0700);
        $children = [];
        // Never inherit an open MySQL socket across fork.
        DB::disconnect();
        try {
            foreach ($operations as $index => [$action,$data]) {
                $pid = pcntl_fork();
                if ($pid < 0) { throw new \RuntimeException('fork failed'); }
                if ($pid === 0) {
                    try {
                        DB::purge();
                        $connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                        file_put_contents($directory.'/ready-'.$index,(string)$connection);
                        $deadline = microtime(true)+15;
                        while (!file_exists($directory.'/go')) {
                            if (microtime(true)>$deadline) { throw new \RuntimeException('barrier timeout'); }
                            usleep(1000);
                        }
                        $response = $this->postJson('/api/v1/account-claims/'.$action,$data);
                        file_put_contents($directory.'/result-'.$index,(string)$response->getStatusCode());
                        exit(0);
                    } catch (\Throwable $error) {
                        file_put_contents($directory.'/result-'.$index,'worker-error:'.get_class($error));
                        exit(1);
                    }
                }
                $children[] = $pid;
            }
            $deadline = microtime(true)+15;
            while (count(glob($directory.'/ready-*')) !== count($operations)) {
                if (microtime(true)>$deadline) { throw new \RuntimeException('workers did not become ready'); }
                usleep(1000);
            }
            $ids = array_map('file_get_contents',glob($directory.'/ready-*'));
            $this->assertCount(count($operations),array_unique($ids), 'Workers must use independent MySQL connections');
            touch($directory.'/go');
            $results = [];
            foreach ($children as $index=>$pid) {
                $deadline = microtime(true)+30;
                do {
                    $waited = pcntl_waitpid($pid,$status,WNOHANG);
                    if ($waited === $pid) { break; }
                    if ($waited === -1 || microtime(true)>$deadline) { throw new \RuntimeException('worker timed out'); }
                    usleep(10000);
                } while (true);
                $children[$index] = 0;
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status)===0,
                    'Child failed: '.file_get_contents($directory.'/result-'.$index));
                $results[] = (int)file_get_contents($directory.'/result-'.$index);
            }
            return $results;
        } finally {
            foreach ($children as $pid) {
                if ($pid > 0) { posix_kill($pid,SIGKILL); pcntl_waitpid($pid,$status); }
            }
            DB::purge();
            foreach (glob($directory.'/*') as $file) { unlink($file); }
            rmdir($directory);
        }
    }

    public function test_two_destinations_cannot_split_ownership(): void
    {
        for ($iteration=0;$iteration<8;$iteration++) {
            DB::table('provisional_account_claims')->delete();
            DB::table('subscriptions')->delete();
            $this->grants();
            $this->postJson('/api/v1/account-claims/register',$this->requestData())->assertOk();
            $codes = $this->race([['transfer',$this->requestData()],
                ['transfer',$this->requestData(['target_user_id'=>'789','username'=>'second@example.invalid'])]]);
            sort($codes); $this->assertSame([200,403],$codes);
            $claim=DB::table('provisional_account_claims')->first();
            $this->assertNotNull($claim->consumed_at);
            $this->assertSame(2,Subscription::where('user_id',$claim->target_user_id)->count());
            $this->assertSame(0,Subscription::where('user_id',123)->count());
            $this->assertSame(2,Subscription::where('expires_at','2028-01-01 00:00:00')->count());
        }
    }

    public function test_registration_and_legacy_transfer_contend_on_same_row(): void
    {
        for ($iteration=0;$iteration<8;$iteration++) {
            DB::table('provisional_account_claims')->delete();
            DB::table('subscriptions')->delete();
            $this->grants();
            [$registered,$legacy] = $this->race([['register',$this->requestData()],['legacy-transfer',$this->requestData()]]);
            $claim=DB::table('provisional_account_claims')->first();
            if ($registered===200) {
                $this->assertSame(403,$legacy); $this->assertNull($claim->consumed_at);
                $this->assertSame(hash('sha256',str_repeat('a',64)),$claim->token_hash);
                $this->assertSame(2,Subscription::where('user_id',123)->count());
            } else {
                $this->assertSame([409,200],[$registered,$legacy]);
                $this->assertSame('legacy',$claim->token_hash); $this->assertNotNull($claim->consumed_at);
                $this->assertSame(2,Subscription::where('user_id',456)->count());
            }
        }
    }

    public function test_empty_legacy_transfer_cannot_block_new_activation(): void
    {
        [$registered,$legacy] = $this->race([['register',$this->requestData()],['legacy-transfer',$this->requestData()]]);
        $this->assertSame(200,$registered); $this->assertContains($legacy,[403,409]);
        $this->grants();
        $this->postJson('/api/v1/account-claims/transfer',$this->requestData())->assertOk();
        $this->assertSame(2,Subscription::where('user_id',456)->count());
    }

    public function test_failed_second_grant_rolls_back_and_can_be_retried(): void
    {
        $this->grants();
        $this->postJson('/api/v1/account-claims/register',$this->requestData())->assertOk();
        Subscription::updating(function ($model) { if ((int)$model->type===2) { throw new \RuntimeException('synthetic failure'); } });
        try { $this->postJson('/api/v1/account-claims/transfer',$this->requestData())->assertStatus(500); }
        finally { Subscription::flushEventListeners(); }
        $this->assertSame(2,Subscription::where('user_id',123)->count());
        $this->assertNull(DB::table('provisional_account_claims')->first()->consumed_at);
        $this->postJson('/api/v1/account-claims/transfer',$this->requestData())->assertOk();
        $this->assertSame(2,Subscription::where('user_id',456)->count());
    }
}
