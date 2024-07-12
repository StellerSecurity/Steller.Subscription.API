<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request): \Illuminate\Http\JsonResponse
    {

        $data = $request->all();

        if($request->input('id') === null or $request->input('id') == 1977 or $request->input('id') == 1988) {
            $data['id'] = Str::uuid();
        }

        $subscription = Subscription::create($data);
        return response()->json($subscription);
    }

    // returns a list of subscriptions a reseller has sold
    public function reseller(int $reseller_user_id, Request $request)
    {
        $subscriptions = Subscription::where('reseller_user_id', $reseller_user_id)->orderBy('created_at', 'desc')->get();
        return response()->json($subscriptions);
    }

    public function findusersubscriptions(Request $request)
    {

        $where['user_id'] = $request->input('user_id');

        if($request->input('type') !== null) {
            $where['type'] = $request->input('type');
        }

        $subscriptions = Subscription::where($where)->orderBy('created_at', 'desc')->get();
        return response()->json($subscriptions);
    }

    /**
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function find(string $id): \Illuminate\Http\JsonResponse
    {
        $subscription = Subscription::find($id);

        if($subscription === null) {
            return response()->json(null, 400);
        }
        return response()->json($subscription, 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request): \Illuminate\Http\JsonResponse
    {

        $subscription = Subscription::find($request->input('id'));
        if($subscription === null) {
            return response()->json([], 400);
        }

        $subscription->delete();

        return response()->json([], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function patch(Request $request): \Illuminate\Http\JsonResponse
    {

        $subscription = Subscription::find($request->input('id'));

        if($subscription === null) {
            return response()->json([], 400);
        }

        $subscription->fill($request->all());
        $subscription->save();

        return response()->json($subscription, 200);

    }

    public function scheduler()
    {

        // sets expired subs to inactive.
        $data = Subscription::where(
            [['expires_at', '<=', Carbon::now()],
            ['status', '!=', SubscriptionStatus::INACTIVE->value]])->update(['status' => SubscriptionStatus::INACTIVE->value]
        );

        // sets un-expired subs to active.
        $data = Subscription::where(
            [['expires_at', '>=', Carbon::now()],
                ['status', '!=', SubscriptionStatus::ACTIVE->value]])->update(['status' => SubscriptionStatus::ACTIVE->value]
        );

        return response()->json($data);

    }

}
