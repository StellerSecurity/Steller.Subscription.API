<?php
namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Internal service endpoints: all routes require existing basicAuth credentials. */
final class ProvisionalAccountClaimController extends Controller
{
    public function ready() {
        abort_unless(Schema::hasTable('provisional_account_claims'), 503);
        return response()->json(['ready'=>true]);
    }
    public function status(Request $request) {
        $data=$request->validate(['provisional_user_id'=>'required|string|max:20|regex:/\A[1-9][0-9]*\z/']);
        return response()->json(['protected'=>DB::table('provisional_account_claims')
            ->where('provisional_user_id',$data['provisional_user_id'])->exists()]);
    }
    public function register(Request $request) {
        $data=$request->validate(['provisional_user_id'=>'required|string|max:20|regex:/\A[1-9][0-9]*\z/',
            'token'=>'required|string|regex:/\A[a-f0-9]{64}\z/']);
        $hash=hash('sha256',$data['token']);
        // insertOrIgnore is idempotent for transport retries, but never replaces proof.
        DB::table('provisional_account_claims')->insertOrIgnore([
            'provisional_user_id'=>$data['provisional_user_id'],'token_hash'=>$hash,'expires_at'=>now()->addYear(),
        ]);
        $row=DB::table('provisional_account_claims')->where('provisional_user_id',$data['provisional_user_id'])->first();
        abort_unless($row && hash_equals($row->token_hash,$hash) && $row->consumed_at===null,409);
        return response()->json(['registered'=>true]);
    }
    private function input(Request $request): array {
        return $request->validate(['provisional_user_id'=>'required|string|max:20|regex:/\A[1-9][0-9]*\z/',
            'token'=>'required|string|regex:/\A[a-f0-9]{64}\z/', 'username'=>'required|email|max:254']);
    }
    private function claim(array $data) {
        $row=DB::table('provisional_account_claims')->where('provisional_user_id',$data['provisional_user_id'])->lockForUpdate()->first();
        abort_unless($row && hash_equals($row->token_hash,hash('sha256',$data['token']))
            && now()->lessThan($row->expires_at),403);
        abort_if(($row->consumed_at!==null || $row->target_user_id!==null)
            && $row->target_username!==$data['username'],403);
        return $row;
    }
    public function authorizeClaim(Request $request) {
        $data=$this->input($request);
        return DB::transaction(function() use($data) {
            $this->claim($data);
            return response()->json(['authorized'=>true]);
        },3);
    }
    public function transfer(Request $request) {
        $data=$this->input($request);
        $target=$request->validate(['target_user_id'=>'required|string|max:20|regex:/\A[1-9][0-9]*\z/'])['target_user_id'];
        abort_if($target===$data['provisional_user_id'],422);
        return DB::transaction(function() use($data,$target) {
            $row=$this->claim($data);
            abort_if($row->target_user_id!==null && $row->target_user_id!==$target,403);
            if($row->consumed_at!==null) return response()->json(['completed'=>true]);
            // Owner changes and proof consumption commit together. No partial VPN/AV transfer.
            $subscriptions=Subscription::where('user_id',$data['provisional_user_id'])->whereIn('type',[1,2])->lockForUpdate()->get();
            abort_if($subscriptions->isEmpty(),409);
            foreach($subscriptions as $subscription) $subscription->forceFill(['user_id'=>$target])->save();
            DB::table('provisional_account_claims')->where('provisional_user_id',$data['provisional_user_id'])
                ->update(['target_username'=>$data['username'],'target_user_id'=>$target,'consumed_at'=>now()]);
            return response()->json(['completed'=>true]);
        },3);
    }
}
