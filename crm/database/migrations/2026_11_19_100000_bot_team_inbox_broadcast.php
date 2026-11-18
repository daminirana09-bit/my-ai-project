<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chatbot, team inbox (assign, notes, open/closed) and broadcast scheduling. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $t) {
            $t->json('bot_config')->nullable();
        });

        Schema::create('bot_answers', function (Blueprint $t) {
            $t->id();
            $t->string('keywords', 500);      // words or phrases, comma separated, in any language
            $t->text('answer');               // written once (English or Hindi); sent in the client's language
            $t->boolean('active')->default(true);
            $t->unsignedInteger('hits')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('wa_messages', function (Blueprint $t) {
            $t->string('direction', 8)->change(); // in | out | note (team note, never sent)
        });

        Schema::table('wa_conversations', function (Blueprint $t) {
            $t->foreignId('assigned_to')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            $t->string('status', 10)->default('open')->after('assigned_to');
            $t->timestamp('closed_at')->nullable();
            $t->string('bot_step', 20)->nullable();
            $t->timestamp('bot_paused_until')->nullable();
            $t->timestamp('bot_replied_at')->nullable();
            $t->index(['assigned_to', 'status']);
        });

        Schema::table('campaigns', function (Blueprint $t) {
            $t->timestamp('scheduled_at')->nullable();
            $t->string('header_type', 10)->nullable();
            $t->string('header_url', 500)->nullable();
            $t->timestamp('finished_at')->nullable();
        });

        Schema::table('campaign_recipients', function (Blueprint $t) {
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('replied_at')->nullable();
            $t->index(['lead_id', 'sent_at']);
            $t->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('campaign_recipients', 'campaign_recipients_lead_id_plain_index')) {
            Schema::table('campaign_recipients', function (Blueprint $t) {
                $t->index('lead_id', 'campaign_recipients_lead_id_plain_index'); // MySQL: the foreign key needs an index of its own again
            });
        }
        Schema::table('campaign_recipients', function (Blueprint $t) {
            $t->dropIndex(['lead_id', 'sent_at']);
            $t->dropIndex(['status', 'sent_at']);
            $t->dropColumn(['sent_at', 'replied_at']);
        });
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['scheduled_at', 'header_type', 'header_url', 'finished_at']));
        Schema::table('wa_conversations', function (Blueprint $t) {
            $t->dropForeign(['assigned_to']);
            $t->dropIndex(['assigned_to', 'status']);
            $t->dropColumn(['assigned_to', 'status', 'closed_at', 'bot_step', 'bot_paused_until', 'bot_replied_at']);
        });
        \Illuminate\Support\Facades\DB::table('wa_messages')->where('direction', 'note')->delete();
        Schema::table('wa_messages', fn (Blueprint $t) => $t->string('direction', 3)->change());
        Schema::dropIfExists('bot_answers');
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn('bot_config'));
    }
};
