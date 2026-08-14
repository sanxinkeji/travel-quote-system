<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->string('title');
            $table->string('customer_title')->nullable();
            $table->string('destination')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->unsignedTinyInteger('duration_days')->default(1)->index();
            $table->unsignedTinyInteger('nights')->default(0);
            $table->unsignedSmallInteger('people_count')->index();
            $table->decimal('budget_per_person', 12, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('per_person_amount', 12, 2)->default(0)->index();
            $table->string('planner_name')->nullable();
            $table->string('wechat')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('executor')->nullable();
            $table->string('reminder_title')->nullable();
            $table->text('reminder_text')->nullable();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->string('status', 20)->default('historical')->index();
            $table->timestamps();

            $table->index(['year', 'month', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
