<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('tags')->nullable();                  // ["IndiaMART", "Buy-lead", "Pantoprazole"]
            $table->string('campaign', 150)->nullable();       // ad campaign / form name
            $table->string('referred_by', 150)->nullable();    // for leads that came by reference
            $table->string('external_ref', 120)->nullable();   // id of the enquiry on the platform
            $table->index('source');
        });

        // One row per lead platform (IndiaMART, TradeIndia, Facebook…). Keys are stored encrypted.
        Schema::create('lead_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30)->unique();
            $table->boolean('enabled')->default(false);
            $table->text('config')->nullable();                // encrypted JSON
            $table->string('token', 64)->unique();             // secret part of the receiving link
            $table->json('assign_to')->nullable();             // rep ids; empty = automatic
            $table->string('extra_tags', 255)->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_lead_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_fields', 1000)->nullable();   // names of fields last received (no values)
            $table->unsignedInteger('received')->default(0);
            $table->timestamps();
        });

        // Every enquiry received once: stops the same enquiry becoming two leads.
        Schema::create('lead_inbounds', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);
            $table->string('external_id', 120);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 12); // created | existing | test | skipped
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['platform', 'external_id']);
        });

        // "Reference" is the word salespeople use.
        DB::table('master_options')->where('type', 'source')->where('value', 'Referral')->update(['value' => 'Reference']);
        DB::table('leads')->where('source', 'Referral')->update(['source' => 'Reference']);

        $have = DB::table('master_options')->where('type', 'source')->pluck('value')->map(fn ($v) => strtolower($v))->all();
        $sort = (int) DB::table('master_options')->where('type', 'source')->max('sort');
        $now = now();
        $rows = [];
        foreach (['ExportersIndia', 'JustDial', 'Facebook Ads', 'Instagram Ads', 'Google Ads', 'Google (search)', 'Google Maps / Business',
            'Reference', 'Walk-in', 'Cold call', 'Existing customer', 'Other apps'] as $v) {
            if (! in_array(strtolower($v), $have, true)) {
                $rows[] = ['type' => 'source', 'value' => $v, 'sort' => ++$sort, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        if ($rows) {
            DB::table('master_options')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_inbounds');
        Schema::dropIfExists('lead_integrations');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['tags', 'campaign', 'referred_by', 'external_ref']);
        });
    }
};
