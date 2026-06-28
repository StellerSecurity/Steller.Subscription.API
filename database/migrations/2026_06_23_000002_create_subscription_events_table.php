<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('subscription_id')->nullable()->index();
            $table->integer('user_id')->nullable()->index();
            $table->string('event_type', 80)->index();
            $table->string('event_source', 80)->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'event_type'], 'subscription_events_subscription_event_index');
            $table->index(['event_type', 'created_at'], 'subscription_events_event_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
