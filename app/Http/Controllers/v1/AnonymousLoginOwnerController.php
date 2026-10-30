<?php
declare(strict_types=1);
namespace App\Http\Controllers\v1;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/** Service Basic auth only. Resolves ownership without activating or modifying a plan. */
final class AnonymousLoginOwnerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $number = $request->input('account_number');
        if (!is_string($number) || !preg_match('/^[0-9]{16}$/D', $number)) {
            return new JsonResponse(['error' => 'invalid_input'], 422, ['Cache-Control' => 'no-store']);
        }
        $subscription = Subscription::query()->where('id', $number)->where('type', 1)->first(['id', 'user_id', 'type']);
        if (!$subscription || !preg_match('/^[1-9][0-9]*$/D', (string) $subscription->user_id)) {
            return new JsonResponse(['error' => 'invalid_credentials'], 404, ['Cache-Control' => 'no-store']);
        }
        return new JsonResponse(['account_number' => (string) $subscription->id, 'user_id' => (string) $subscription->user_id,
            'type' => 1], 200, ['Cache-Control' => 'no-store']);
    }
}

