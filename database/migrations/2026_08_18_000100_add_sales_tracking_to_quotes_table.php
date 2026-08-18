<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('sales_status', 20)->default('following')->index()->after('status');
            $table->timestamp('won_at')->nullable()->index()->after('sales_status');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['sales_status']);
            $table->dropIndex(['won_at']);
            $table->dropColumn(['sales_status', 'won_at']);
        });
    }
};
