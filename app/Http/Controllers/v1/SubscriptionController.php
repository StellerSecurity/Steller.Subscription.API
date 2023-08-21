<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Support\Facades\Request;

class SubscriptionController extends Controller
{

    public function add(Request $request)
    {
        $subscription = Subscription::create($request->all());
        return response()->json($subscription);
    }

    public function find(Request $request): \Illuminate\Http\JsonResponse
    {
        $subscription = Subscription::find($request->input('id'));

        if($subscription === null) {
            return response()->json([], 400);
        }
        return response()->json($subscription, 200);
    }

    public function delete(Request $request): \Illuminate\Http\JsonResponse
    {

        $subscription = Subscription::find($request->input('id'));
        if($subscription === null) {
            return response()->json([], 400);
        }

        $subscription->delete();

        return response()->json([], 200);
    }

}
