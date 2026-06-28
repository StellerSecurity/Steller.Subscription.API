<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionEvent;
use App\Services\SubscriptionEventLogger;
use App\SubscriptionEventType;
use Illuminate\Http\Request;

class SubscriptionEventController extends Controller
{
    public function add(Request $request, SubscriptionEventLogger $logger): \Illuminate\Http\JsonResponse
    {
        $eventType = SubscriptionEventType::normalize($request->input('event_type'));

        if ($eventType === null) {
            return response()->json(['error' => 'event_type is required'], 400);
        }

        $userId = $request->input('user_id');

        $event = $logger->log(
            $request->input('subscription_id'),
            $eventType,
            $userId === null ? null : (int) $userId,
            $request->input('event_source'),
            $request->input('meta')
        );

        return response()->json($event, 201);
    }

    public function find(string $id): \Illuminate\Http\JsonResponse
    {
        $event = SubscriptionEvent::find($id);

        if ($event === null) {
            return response()->json(null, 200);
        }

        return response()->json($event, 200);
    }

    public function subscription(string $subscription_id, Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = (int) $request->input('limit', 100);
        $limit = max(1, min($limit, 500));

        $query = SubscriptionEvent::where('subscription_id', $subscription_id)->orderBy('created_at', 'desc');

        if ($request->input('event_type') !== null) {
            $query->where('event_type', SubscriptionEventType::normalize($request->input('event_type')));
        }

        if ($request->input('event_source') !== null) {
            $query->where('event_source', $request->input('event_source'));
        }

        return response()->json($query->limit($limit)->get(), 200);
    }

    public function user(int $user_id, Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = (int) $request->input('limit', 100);
        $limit = max(1, min($limit, 500));

        $query = SubscriptionEvent::where('user_id', $user_id)->orderBy('created_at', 'desc');

        if ($request->input('event_type') !== null) {
            $query->where('event_type', SubscriptionEventType::normalize($request->input('event_type')));
        }

        return response()->json($query->limit($limit)->get(), 200);
    }
}
