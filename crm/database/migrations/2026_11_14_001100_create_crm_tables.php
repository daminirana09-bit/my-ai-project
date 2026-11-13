<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row: the company that installed this copy of the software sets its own details.
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('drug_licence_no')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            // Default office timing for everyone; a user can have their own timing.
            $table->time('office_start')->default('09:30:00');
            $table->time('office_end')->default('18:30:00');
            $table->time('lunch_start')->default('13:30:00');
            $table->time('lunch_end')->default('14:00:00');
            $table->unsignedSmallInteger('late_grace_minutes')->default(15);
            $table->unsignedSmallInteger('full_day_minutes')->default(480); // 8 h of work
            $table->unsignedSmallInteger('half_day_minutes')->default(240); // 4 h of work
            $table->string('weekly_off', 20)->default('0'); // 0 = Sunday, comma separated
            $table->boolean('reps_can_export_own_leads')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('rep')->index(); // admin | manager | rep
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('phone')->nullable(); // encrypted
            $table->boolean('active')->default(true);
            $table->text('two_factor_secret')->nullable(); // encrypted
            // Personal timing; null means "use the company timing".
            $table->time('office_start')->nullable();
            $table->time('office_end')->nullable();
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('locale', 5)->default('en'); // en | hing | hi
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('firm_name');
            $table->string('contact_name')->nullable();
            $table->text('phone')->nullable();        // encrypted
            $table->string('phone_hash', 64)->nullable()->index(); // keyed hash, used to find the same client twice
            $table->text('email')->nullable();        // encrypted
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('gstin', 20)->nullable()->index();
            $table->string('source', 40)->nullable();  // IndiaMART, Website, WhatsApp, Referral, Import...
            $table->string('client_type', 40)->nullable(); // Third-party, PCD, Distributor, Hospital, Export
            $table->string('requirement')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->decimal('expected_value', 14, 2)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_via', 20)->default('manual'); // manual | import
            $table->timestamp('last_contact_at')->nullable();
            $table->date('next_follow_up_on')->nullable();
            $table->string('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index(); // call | whatsapp | visit | note | status | assign
            $table->string('outcome', 40)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'type', 'occurred_at']);
        });

        // Two salespeople working the same client: admin and managers decide who keeps it.
        Schema::create('lead_clashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('existing_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('other_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 30); // added_again | imported_again | contacted
            $table->string('detail')->nullable();
            $table->string('status', 10)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7); // 2026-11
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['user_id', 'month']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_no')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('sale_date')->index();
            $table->string('status', 12)->default('confirmed'); // confirmed | pending
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 3); // in | out
            $table->timestamp('punched_at')->index();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('first_in')->nullable();
            $table->timestamp('last_out')->nullable();
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->string('status', 15); // full_day | half_day | absent | single_swipe | weekly_off | leave
            $table->boolean('late')->default(false);
            $table->boolean('manual')->default(false); // set by admin, not recalculated
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        // Every export, import, assignment and settings change. Nobody can edit it from the app.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('subject')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'attendance_days', 'attendance_punches', 'sales', 'targets', 'lead_clashes', 'lead_activities', 'leads', 'company_settings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['role', 'phone', 'active', 'two_factor_secret', 'office_start', 'office_end', 'lunch_start', 'lunch_end', 'last_login_at', 'locale']);
        });
    }
};
