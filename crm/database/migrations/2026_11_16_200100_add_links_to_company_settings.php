<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('links')->nullable();          // website, social pages, Google review link…
            $table->json('share_messages')->nullable(); // ready texts the team sends to clients
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn(['links', 'share_messages']));
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn('review_requested_at'));
    }
};
