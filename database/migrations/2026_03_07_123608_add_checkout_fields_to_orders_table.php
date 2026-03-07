<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('order_token')->nullable()->unique()->after('id');
            $table->string('source')->nullable()->after('order_token');
            $table->foreignId('user_id')->nullable()->after('source')->constrained()->nullOnDelete();

            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone', 40)->nullable()->after('customer_email');

            $table->foreignId('shipping_country_id')->nullable()->after('customer_phone')->constrained('countries')->nullOnDelete();
            $table->foreignId('shipping_department_id')->nullable()->after('shipping_country_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('shipping_province_id')->nullable()->after('shipping_department_id')->constrained('provinces')->nullOnDelete();
            $table->foreignId('shipping_district_id')->nullable()->after('shipping_province_id')->constrained('districts')->nullOnDelete();
            $table->string('shipping_address_line')->nullable()->after('shipping_district_id');
            $table->string('shipping_reference')->nullable()->after('shipping_address_line');

            $table->string('payment_gateway')->nullable()->after('shipping_reference');
            $table->string('payment_status')->nullable()->index()->after('payment_gateway');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->string('payment_error_code')->nullable()->after('payment_reference');
            $table->text('payment_error_message')->nullable()->after('payment_error_code');
            $table->timestamp('paid_at')->nullable()->after('payment_error_message');

            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['customer_email']);
            $table->dropIndex(['payment_status']);

            $table->dropConstrainedForeignId('shipping_district_id');
            $table->dropConstrainedForeignId('shipping_province_id');
            $table->dropConstrainedForeignId('shipping_department_id');
            $table->dropConstrainedForeignId('shipping_country_id');
            $table->dropConstrainedForeignId('user_id');

            $table->dropColumn([
                'order_token',
                'source',
                'customer_name',
                'customer_email',
                'customer_phone',
                'shipping_address_line',
                'shipping_reference',
                'payment_gateway',
                'payment_status',
                'payment_reference',
                'payment_error_code',
                'payment_error_message',
                'paid_at',
            ]);
        });
    }
};
