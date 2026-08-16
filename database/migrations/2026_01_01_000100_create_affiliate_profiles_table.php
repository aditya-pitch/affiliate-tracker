<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 7: "each creator record holds their email, password, coupon
     * code(s), commission rate, notification preferences, and payout details."
     * Email and password live on `users`; everything else lives here.
     */
    public function up(): void
    {
        Schema::create('affiliate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('display_name')->nullable();

            /*
             | Spec section 5.5: "The commission rate is set per affiliate and is
             | not fixed, so the dashboard must use each creator's own rate
             | rather than a single hard-coded value."
             |
             | Stored as a decimal fraction: 0.1500 == 15%. Four decimal places
             | allows rates like 12.5%.
             */
            $table->decimal('commission_rate', 6, 4);

            /*
             | Spec section 5.4: summary totals are converted to INR for Indian
             | creators and USD for creators based abroad. Individual order rows
             | always keep the currency the customer actually paid in.
             */
            $table->char('payout_currency', 3)->default('INR');
            $table->char('country_code', 2)->default('IN');

            // Payout details. Not shown to anyone but the creator and our team.
            $table->string('payout_account_name')->nullable();
            $table->text('payout_details')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();

            /*
             | Notification preferences, spec section 6.1. The master switch
             | overrides the individual ones. Settlement emails (6.2) are
             | deliberately absent here: they are always sent.
             */
            $table->boolean('notify_master')->default(true);
            $table->boolean('notify_on_sale')->default(true);
            $table->boolean('notify_weekly_summary')->default(true);

            // Confirmed during spec review: creators can batch the per-order
            // emails so a busy sale does not flood their inbox.
            $table->enum('sale_notification_frequency', ['immediate', 'hourly', 'daily'])
                ->default('immediate');
            $table->timestamp('last_digest_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_profiles');
    }
};
