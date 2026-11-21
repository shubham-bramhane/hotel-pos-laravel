<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table): void {
            $table->id();
            $table->string('outlet_code', 40)->unique();
            $table->string('name', 120);
            $table->string('city', 80)->nullable();
            $table->string('state', 80)->nullable();
            $table->unsignedInteger('seating_capacity')->default(0);
            $table->string('sync_secret_hash', 120)->nullable();
            $table->boolean('is_local')->default(false)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_analytics_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->date('business_date')->index();
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('dine_in_orders')->default(0);
            $table->unsignedInteger('takeaway_orders')->default(0);
            $table->decimal('gross_sales', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('gst_total', 12, 2)->default(0);
            $table->decimal('net_revenue', 12, 2)->default(0);
            $table->decimal('avg_bill_value', 10, 2)->default(0);
            $table->decimal('cash_total', 12, 2)->default(0);
            $table->decimal('upi_total', 12, 2)->default(0);
            $table->decimal('card_total', 12, 2)->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('cancelled_items')->default(0);
            $table->unsignedInteger('guest_count')->default(0);
            $table->json('category_sales')->nullable();
            $table->json('top_dishes')->nullable();
            $table->json('hourly_sales')->nullable();
            $table->json('waiter_performance')->nullable();
            $table->json('veg_nonveg_split')->nullable();
            $table->string('payload_checksum', 64)->nullable();
            $table->string('sync_status', 20)->default('pending')->index(); // pending, synced, failed
            $table->timestamp('synced_at')->nullable();
            $table->unsignedSmallInteger('sync_attempts')->default(0);
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['outlet_id', 'business_date']);
        });

        Schema::create('ai_insight_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('scope', 20)->default('outlet')->index(); // outlet, portfolio
            $table->date('period_from');
            $table->date('period_to');
            $table->string('provider', 30)->default('local_fallback'); // gemini, local_fallback
            $table->string('model_name', 60)->default('statistical-engine-v1');
            $table->text('executive_summary');
            $table->unsignedTinyInteger('health_score')->default(75);
            $table->json('highlights')->nullable();
            $table->json('risks')->nullable();
            $table->json('recommendations')->nullable();
            $table->text('investor_narrative')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->foreignId('generated_by_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investor_share_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('title', 140);
            $table->string('investor_name', 100)->nullable();
            $table->string('token', 64)->unique();
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->boolean('include_ai_insights')->default(true);
            $table->boolean('include_branch_comparison')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cloud_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('direction', 20)->default('outbound')->index(); // outbound, inbound
            $table->string('trigger_type', 20)->default('scheduled'); // scheduled, manual, api
            $table->string('status', 20)->default('success')->index(); // success, failed, skipped
            $table->unsignedInteger('snapshots_count')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_sync_logs');
        Schema::dropIfExists('investor_share_links');
        Schema::dropIfExists('ai_insight_reports');
        Schema::dropIfExists('daily_analytics_snapshots');
        Schema::dropIfExists('outlets');
    }
};
