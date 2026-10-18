<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('factory_vpn_handovers', function (Blueprint $table) {
            $table->string('os_subscription_id', 36)->primary();
            $table->string('vpn_subscription_id', 36)->unique();
            $table->string('token_hash', 64)->unique();
            $table->text('baseline');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('factory_vpn_handovers'); }
};
