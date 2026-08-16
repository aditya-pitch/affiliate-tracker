<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A record of every email the dashboard sends a creator.
     *
     * Two reasons this exists rather than relying on the mail log: the digest
     * options (spec 6.1) need to know which orders have already been reported
     * so an hourly digest does not repeat itself, and settlement emails (6.2)
     * must be provably sent when a payout is disputed.
     */
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 64)->index();
            $table->string('channel', 32)->default('mail');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['user_id', 'type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
    }
};
