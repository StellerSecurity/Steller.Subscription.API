<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_credits', function (Blueprint $table) {
            $table->string('payment_reference', 191)->primary();
            $table->uuid('subscription_id')->index();
            $table->unsignedInteger('days');
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_credits');
    }
};
