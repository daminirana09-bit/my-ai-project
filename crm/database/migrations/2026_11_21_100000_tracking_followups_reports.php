<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Team tracking (location on punch and visit check-in), auto WhatsApp follow-ups, and deal dates for reports. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('accuracy')->nullable(); // metres
        });
        Schema::table('lead_activities', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('accuracy')->nullable();
            $t->unsignedInteger('distance_m')->nullable(); // from the client's saved location
            $t->index(['occurred_at', 'user_id']);
        });
        Schema::table('leads', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable();     // the client's place, saved at the first visit check-in
            $t->decimal('lng', 10, 7)->nullable();
            $t->timestamp('won_at')->nullable();
            $t->timestamp('lost_at')->nullable();
        });
        Schema::table('quotes', function (Blueprint $t) {
            $t->timestamp('sent_at')->nullable();
        });

        Schema::create('followup_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('trigger', 20);           // follow_up_due | quote_no_reply | no_reply | reorder | new_lead
            $t->unsignedSmallInteger('after')->default(2);   // days (hours for new_lead)
            $t->unsignedSmallInteger('repeat_days')->default(7); // not again to the same client within
            $t->string('template_name', 120)->nullable();
            $t->string('template_language', 10)->default('en');
            $t->json('variables')->nullable();
            $t->text('text')->nullable();         // used instead of the template within 24 hours of the client's message
            $t->boolean('active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
        });
        Schema::create('followup_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('followup_rule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->string('status', 10); // sent | failed | skipped
            $t->string('wamid')->nullable()->index();
            $t->string('error', 255)->nullable();
            $t->timestamp('sent_at')->index();
            $t->index(['followup_rule_id', 'lead_id', 'sent_at']);
        });

        // Deal dates for leads already won or lost
        DB::table('leads')->where('status', 'won')->whereNull('won_at')->update(['won_at' => DB::raw('updated_at')]);
        DB::table('leads')->where('status', 'lost')->whereNull('lost_at')->update(['lost_at' => DB::raw('updated_at')]);
        DB::table('quotes')->where('status', 'sent')->whereNull('sent_at')->update(['sent_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('followup_logs');
        Schema::dropIfExists('followup_rules');
        Schema::table('quotes', fn (Blueprint $t) => $t->dropColumn('sent_at'));
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng', 'won_at', 'lost_at']));
        Schema::table('lead_activities', function (Blueprint $t) {
            $t->dropIndex(['occurred_at', 'user_id']);
            $t->dropColumn(['lat', 'lng', 'accuracy', 'distance_m']);
        });
        Schema::table('attendance_punches', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng', 'accuracy']));
    }
};
