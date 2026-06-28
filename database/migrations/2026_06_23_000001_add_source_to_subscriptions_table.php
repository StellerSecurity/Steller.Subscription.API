<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('source', 80)->nullable()->after('status');
            $table->json('source_meta')->nullable()->after('source');
            $table->index('source', 'subscriptions_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_source_index');
            $table->dropColumn(['source', 'source_meta']);
        });
    }
};
