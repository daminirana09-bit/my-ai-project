<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false); // set by the admin after a reset
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('mail_config')->nullable(); // encrypted: SMTP server for password-reset mails
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('must_change_password'));
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn('mail_config'));
    }
};
