<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per order placed with an affiliate's coupon code.
     *
     * The columns the creator actually sees are listed in spec section 5.3.
     * Everything else here exists so the summary in 5.2 can be produced
     * without recalculating exchange rates after the fact.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_code_id')->constrained()->cascadeOnDelete();

            /*
             | Denormalised owner. Attribution runs through the coupon code, but
             | every query in the dashboard is "this creator's orders", and this
             | column is what makes the ownership check a single indexed
             | comparison instead of a join we could forget to write.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Our order reference. Shown masked to the creator (section 5.3).
            $table->string('order_ref', 64)->unique();

            $table->timestamp('placed_at')->index();

            /*
             | Customer details. The creator sees the first name, the country
             | and the state. The surname is masked and the email is never
             | exposed at all -- confirmed during spec review.
             */
            $table->string('customer_first_name');
            $table->string('customer_last_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('country', 64);
            $table->string('state', 64)->nullable();

            // The product purchased, e.g. "Loop2Kit", "Crystal Snow".
            $table->string('plugin');

            /*
             | Money, as the customer paid it. `amount` is GST inclusive and in
             | `currency` -- this pair is what the orders table displays.
             */
            $table->char('currency', 3);
            $table->decimal('amount', 14, 2);

            /*
             | Spec sections 5.4 / 8: the exchange rate is locked at the time of
             | the order so historical totals never shift. `converted_amount` is
             | `amount` expressed in the creator's payout currency, and is what
             | the summary adds up.
             */
            $table->char('payout_currency', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('converted_amount', 14, 2);

            /*
             | Spec section 8: "Refunded orders never count toward gross
             | earnings or commission; they appear only in the Refunded orders
             | total."
             */
            $table->boolean('is_refunded')->default(false);
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            // The dashboard's hot path: one creator, one sale, newest first.
            $table->index(['user_id', 'sale_id', 'placed_at']);
            $table->index(['sale_id', 'is_refunded']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
