<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR: employee details, holidays, leave, expense claims (TA/DA), payroll and documents.
 * Personal, bank, ID and salary details are stored encrypted (Rule 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('emp_code', 20)->nullable()->unique();
            $t->string('designation', 80)->nullable();
            $t->string('department', 80)->nullable();
            $t->string('headquarter', 80)->nullable();
            $t->string('employment_type', 12)->default('permanent'); // permanent | probation | contract | intern
            $t->date('date_of_joining')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('gender', 10)->nullable();
            $t->string('status', 10)->default('active'); // active | notice | left
            $t->date('exit_date')->nullable();
            $t->text('personal')->nullable(); // encrypted: phone, email, address, emergency contact, blood group
            $t->text('bank')->nullable();     // encrypted: account name, number, IFSC, bank
            $t->text('ids')->nullable();      // encrypted: PAN, UAN, ESIC, last 4 of Aadhaar
            $t->text('salary')->nullable();   // encrypted: monthly components and deductions
            $t->timestamps();
        });

        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name', 100);
            $t->string('type', 10)->default('festival'); // national | festival | optional
            $t->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name', 60);
            $t->decimal('yearly_quota', 5, 1)->nullable(); // null = no limit
            $t->boolean('paid')->default(true);
            $t->boolean('active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained();
            $t->date('from_date');
            $t->date('to_date');
            $t->boolean('half_day')->default(false);
            $t->decimal('days', 5, 1);
            $t->text('reason')->nullable(); // encrypted (may be medical)
            $t->string('status', 10)->default('pending'); // pending | approved | rejected | cancelled
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 300)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status', 'from_date']);
        });

        Schema::create('expense_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->string('kind', 10); // da | travel | hotel | food | phone | other
            $t->string('detail', 12)->nullable(); // DA: hq | exhq | outstation; travel: bike | car
            $t->string('from_place', 80)->nullable();
            $t->string('to_place', 80)->nullable();
            $t->decimal('km', 7, 1)->nullable();
            $t->decimal('amount', 10, 2);
            $t->string('note', 300)->nullable();
            $t->string('receipt_path')->nullable();
            $t->string('receipt_mime', 60)->nullable();
            $t->string('status', 10)->default('pending'); // pending | approved | rejected | paid
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('decision_note', 300)->nullable();
            $t->unsignedBigInteger('payslip_id')->nullable()->index();
            $t->timestamps();
            $t->index(['user_id', 'status', 'date']);
        });

        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->date('month')->unique(); // first day of the month
            $t->string('status', 8)->default('draft'); // draft | final
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('finalized_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payslips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('data');  // encrypted: days, earnings, deductions, totals
            $t->text('adjust')->nullable(); // encrypted: incentive, other deduction, note entered by HR
            $t->timestamps();
            $t->unique(['payroll_run_id', 'user_id']);
        });

        Schema::create('hr_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 120);
            $t->string('path');
            $t->string('extension', 8);
            $t->unsignedInteger('size');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('company_settings', function (Blueprint $t) {
            $t->json('hr_config')->nullable();
        });

        $now = now();
        DB::table('leave_types')->insert([
            ['code' => 'CL', 'name' => 'Casual leave', 'yearly_quota' => 12, 'paid' => true, 'active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SL', 'name' => 'Sick leave', 'yearly_quota' => 7, 'paid' => true, 'active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EL', 'name' => 'Earned leave', 'yearly_quota' => 15, 'paid' => true, 'active' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LWP', 'name' => 'Leave without pay', 'yearly_quota' => null, 'paid' => false, 'active' => true, 'sort' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
        // Only the fixed-date national holidays; festivals change every year and by state, so the admin adds them.
        $rows = [];
        foreach ([(int) $now->format('Y'), (int) $now->format('Y') + 1] as $y) {
            foreach (['01-26' => 'Republic Day', '08-15' => 'Independence Day', '10-02' => 'Gandhi Jayanti'] as $md => $name) {
                $rows[] = ['date' => "$y-$md", 'name' => $name, 'type' => 'national', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('holidays')->insert($rows);
    }

    public function down(): void
    {
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn('hr_config'));
        foreach (['hr_documents', 'payslips', 'payroll_runs', 'expense_claims', 'leave_requests', 'leave_types', 'holidays', 'employee_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
