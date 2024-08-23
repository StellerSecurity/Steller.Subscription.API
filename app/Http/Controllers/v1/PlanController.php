<?php

namespace App\Http\Controllers\v1;

use App\Models\Plan;

class PlanController
{

    public function plan(string $plan_id): \Illuminate\Http\JsonResponse
    {

        $plan = Plan::find($plan_id);
        return response()->json($plan);

    }

}
