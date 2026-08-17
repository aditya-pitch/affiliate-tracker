<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports the admin provisioning screens: setting a creator up and sending
     * them their login details.
     *
     * Note what is deliberately absent: anywhere to keep the password itself.
     * Passwords stay hashed and unreadable. These columns only record *when* a
     * password was issued and *when* the welcome email went out, which is what
     * the admin actually needs to know -- whether a creator has been set up and
     * told about it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_issued_at')->nullable()->after('password');
            $table->timestamp('welcome_sent_at')->nullable()->after('password_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_issued_at', 'welcome_sent_at']);
        });
    }
};
