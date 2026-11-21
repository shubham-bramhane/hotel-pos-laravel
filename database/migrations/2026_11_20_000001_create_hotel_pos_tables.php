<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('role', 20)->index(); // owner, cashier, kitchen, waiter
            $table->string('pin_code', 10)->unique(); // 4-digit fast POS PIN
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->string('section', 60)->default('Main Hall');
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->string('status', 20)->default('available')->index(); // available, occupied, billing
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('short_code', 20)->nullable()->index();
            $table->decimal('price', 10, 2);
            $table->boolean('is_veg')->default(true);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('settled_by_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('order_type', 20)->default('dine_in'); // dine_in, takeaway
            $table->unsignedSmallInteger('guest_count')->default(2);
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('status', 20)->default('open')->index(); // open, billing, paid, cancelled
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('discount_type', 15)->default('none'); // none, flat, percent
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(5.00);
            $table->decimal('cgst_amount', 10, 2)->default(0);
            $table->decimal('sgst_amount', 10, 2)->default(0);
            $table->decimal('round_off', 6, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->string('payment_method', 20)->nullable(); // cash, upi, card, split
            $table->decimal('cash_amount', 10, 2)->default(0);
            $table->decimal('upi_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('kots', function (Blueprint $table) {
            $table->id();
            $table->string('kot_no', 30);
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, preparing, ready, served, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('kot_id')->nullable()->constrained('kots')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('item_name', 120);
            $table->boolean('is_veg')->default(true);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 10, 2);
            $table->string('special_note', 160)->nullable();
            $table->string('status', 20)->default('pending'); // pending, preparing, ready, served, cancelled
            $table->string('cancel_reason', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('staff_name', 100);
            $table->string('staff_role', 20);
            $table->string('action', 60)->index();
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('hotel_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('kots');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('staff_users');
    }
};
