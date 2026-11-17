<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('translation_config')->nullable(); // encrypted: provider and keys
        });
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->text('translation')->nullable();        // encrypted: meaning in the salesperson's language, or their own words
            $table->string('translation_lang', 8)->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('read_lang', 8)->nullable();     // language client messages are translated into for this person
        });
        // Translations already done, so the same line is never sent twice. Text is encrypted.
        Schema::create('translation_cache', function (Blueprint $table) {
            $table->id();
            $table->char('key', 64)->unique();
            $table->text('text');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_cache');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('read_lang'));
        Schema::table('wa_messages', fn (Blueprint $t) => $t->dropColumn(['translation', 'translation_lang']));
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn('translation_config'));
    }
};
