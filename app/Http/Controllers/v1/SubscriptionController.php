<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request): \Illuminate\Http\JsonResponse
    {
        $subscription = Subscription::create($request->all());
        return response()->json($subscription);
    }

    /**
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function find(string $id): \Illuminate\Http\JsonResponse
    {
        $subscription = Subscription::find($id);

        if($subscription === null) {
            return response()->json([], 400);
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

}
