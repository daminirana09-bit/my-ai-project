<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('whatsapp_config')->nullable(); // encrypted JSON: access token, app secret, verify token, API version
            $table->json('automations')->nullable();
            $table->string('lead_capture_token', 64)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Away status for WhatsApp cover: half_day | leave | meeting
            $table->string('away_type', 12)->nullable();
            $table->timestamp('away_until')->nullable();
            $table->foreignId('backup_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('salutation', 10)->default('ji'); // ji | sir | madam
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_out')->default(false);
            $table->string('language', 8)->nullable(); // client's language, detected from messages
        });

        // One row per company WhatsApp number. A salesperson's own number has user_id set.
        Schema::create('whatsapp_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('label', 60);
            $table->string('display_phone', 20);
            $table->string('phone_number_id', 40)->unique(); // from Meta WhatsApp Manager
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_number_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id_hash', 64);  // keyed hash of the client's WhatsApp number
            $table->text('wa_id');             // encrypted
            $table->string('contact_name')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language', 8)->nullable();
            $table->string('last_intent', 20)->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('unread')->default(0);
            $table->timestamp('auto_replied_at')->nullable();
            $table->boolean('waiting_callback')->default(false)->index();
            $table->boolean('urgent')->default(false);
            $table->timestamps();
            $table->unique(['whatsapp_number_id', 'wa_id_hash']);
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3); // in | out
            $table->text('body')->nullable(); // encrypted
            $table->string('type', 20)->default('text'); // text | template | document | auto
            $table->string('wamid')->nullable()->index(); // Meta message id
            $table->string('status', 12)->default('received'); // received | queued | sent | delivered | read | failed
            $table->string('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who sent it (null = automatic)
            $table->string('language', 8)->nullable();
            $table->string('intent', 20)->nullable();
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });

        // Ready replies in many languages. The company edits them; defaults are seeded.
        Schema::create('reply_templates', function (Blueprint $table) {
            $table->id();
            $table->string('intent', 20)->index(); // greeting | price | order_status | payment | sample | documents | complaint | new_order | away | other
            $table->string('language', 8);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['intent', 'language']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('whatsapp_number_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name', 120); // approved template name in WhatsApp Manager
            $table->string('template_language', 10)->default('en');
            $table->json('filters')->nullable();
            $table->json('variables')->nullable(); // {{1}} → contact_name …
            $table->string('status', 12)->default('draft'); // draft | sending | done
            $table->unsignedInteger('total')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('pending'); // pending | sent | delivered | read | failed | skipped
            $table->string('wamid')->nullable()->index();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'lead_id']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('quote_date');
            $table->unsignedSmallInteger('valid_days')->default(15);
            $table->decimal('freight', 12, 2)->default(0);
            $table->text('terms')->nullable();
            $table->string('status', 12)->default('draft'); // draft | sent | accepted | rejected
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('gst_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('packing', 60)->nullable();
            $table->string('hsn', 12)->nullable();
            $table->unsignedInteger('qty');
            $table->decimal('rate', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(5);
            $table->decimal('amount', 14, 2); // after discount, before GST
            $table->timestamps();
        });

        $now = now();
        $rows = [];
        foreach (require database_path('data/reply_templates.php') as $lang => $byIntent) {
            foreach ($byIntent as $intent => $body) {
                $rows[] = ['intent' => $intent, 'language' => $lang, 'body' => $body, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            \Illuminate\Support\Facades\DB::table('reply_templates')->insert($chunk);
        }
    }

    public function down(): void
    {
        foreach (['quote_items', 'quotes', 'campaign_recipients', 'campaigns', 'reply_templates', 'wa_messages', 'wa_conversations', 'whatsapp_numbers'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['whatsapp_opt_out', 'language']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('backup_user_id');
            $t->dropColumn(['away_type', 'away_until', 'salutation']);
        });
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn(['whatsapp_config', 'automations', 'lead_capture_token']));
    }
};
