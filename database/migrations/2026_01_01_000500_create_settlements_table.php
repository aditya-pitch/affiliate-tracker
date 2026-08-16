<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (creator, ended sale) -- the settlement record that carries a
     * creator from "sale closed" through to "paid" (spec section 5.7).
     *
     * The summary figures are snapshotted here at close time rather than
     * recomputed on read. That is what "the report is locked" means in section
     * 5.7: if a refund is processed late, or a commission rate is changed for
     * the next campaign, an already-settled report does not silently change
     * underneath a creator who has already invoiced against it.
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['pending', 'invoice_uploaded', 'paid'])
                ->default('pending')
                ->index();

            // --- Locked summary snapshot (spec section 5.2) ---
            $table->char('currency', 3);
            $table->unsignedInteger('units_sold')->default(0);
            $table->unsignedInteger('refunded_orders')->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);   // GST inclusive
            $table->decimal('gst_amount', 14, 2)->default(0);       // less GST @ 18%
            $table->decimal('net_sales', 14, 2)->default(0);        // "A" in section 5.5
            $table->decimal('commission_rate', 6, 4);               // the creator's own rate
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('transaction_fee', 14, 2)->default(0);  // less fees @ 5%
            $table->decimal('payout_amount', 14, 2)->default(0);    // final affiliate payout

            $table->timestamp('finalised_at')->nullable();

            // --- Invoice upload (spec section 5.7) ---
            $table->string('invoice_path')->nullable();
            $table->string('invoice_original_name')->nullable();
            $table->unsignedInteger('invoice_size')->nullable();
            $table->timestamp('invoice_uploaded_at')->nullable();

            // --- Payment, recorded by our team (spec section 5.7) ---
            $table->decimal('paid_amount', 14, 2)->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // A creator settles a given sale exactly once.
            $table->unique(['sale_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
