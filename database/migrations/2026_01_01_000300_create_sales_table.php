<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec section 5.1: promotional campaigns such as "Summer Sale 2026".
     * Section 7: "each promotional sale is defined by a name and a start and
     * end date; an order falls under whichever sale was live when it was
     * placed."
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();

            /*
             | Set when the sale is closed out. Once this is stamped the report
             | is locked: figures, the Excel download and the invoice all refer
             | to the same final numbers (spec section 5.7).
             */
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
