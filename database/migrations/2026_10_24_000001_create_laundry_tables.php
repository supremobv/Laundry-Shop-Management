<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 30)->default('cashier');
            $t->boolean('active')->default(true);
            $t->boolean('must_change_password')->default(true);
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name')->default('FreshFold Laundry');
            $t->string('tagline')->default('A fresh start, every load.');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('address')->nullable();
            $t->string('currency', 3)->default('PHP');
            $t->string('timezone')->default('Asia/Manila');
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->string('receipt_footer')->default('Thank you for choosing us. See you next wash!');
            $t->string('logo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 40)->index();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('unit', 20);
            $t->unsignedInteger('price_cents');
            $t->unsignedInteger('turnaround_hours')->default(24);
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('status', 30)->default('received')->index();
            $t->string('priority', 20)->default('normal');
            $t->string('rack')->nullable();
            $t->timestamp('due_at')->index();
            $t->timestamp('completed_at')->nullable();
            $t->text('notes')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->unsignedInteger('subtotal_cents');
            $t->unsignedInteger('discount_cents')->default(0);
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->unsignedInteger('tax_cents')->default(0);
            $t->unsignedInteger('total_cents');
            $t->timestamps();
            $t->index(['status', 'due_at']);
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('unit', 20);
            $t->decimal('quantity', 10, 2);
            $t->unsignedInteger('unit_price_cents');
            $t->unsignedInteger('total_cents');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('amount_cents');
            $t->string('method', 30);
            $t->string('reference')->nullable();
            $t->uuid('idempotency_key')->unique();
            $t->timestamps();
        });
        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('description');
            $t->string('category', 50);
            $t->unsignedInteger('amount_cents');
            $t->date('spent_on')->index();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
        });
        Schema::create('supplies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('unit', 20);
            $t->decimal('quantity', 12, 2)->default(0);
            $t->decimal('reorder_level', 12, 2)->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supply_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 12, 2);
            $t->string('reason');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action')->index();
            $t->string('subject')->nullable();
            $t->json('metadata')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'stock_movements', 'supplies', 'expenses', 'order_events', 'payments', 'order_items', 'orders', 'services', 'customers', 'business_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active', 'must_change_password']));
    }
};
