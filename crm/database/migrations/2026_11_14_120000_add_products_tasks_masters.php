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
            $table->string('temperature', 10)->nullable()->index(); // hot | warm | cold
            $table->string('email_hash', 64)->nullable()->index();  // keyed hash, for exact email search
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('permissions')->nullable(); // role → permission → bool, set on Roles & Permissions
        });

        // Lists each company keeps for itself: lead sources, client types, reasons for losing a lead…
        Schema::create('master_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index(); // source | client_type | lost_reason | product_category
            $table->string('value', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'value']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('composition')->nullable();
            $table->string('form', 40)->nullable();      // Tablet, Capsule, Syrup…
            $table->string('packing', 60)->nullable();   // 10×10 Alu-Alu…
            $table->string('category', 60)->nullable();
            $table->decimal('rate', 12, 2)->nullable();  // per strip / unit
            $table->string('unit', 20)->default('strip');
            $table->unsignedInteger('moq')->nullable();
            $table->string('hsn', 12)->nullable();
            $table->decimal('gst_percent', 5, 2)->default(5);
            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('details')->nullable();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->nullable()->index();
            $table->string('priority', 10)->default('normal'); // low | normal | high
            $table->string('status', 15)->default('pending')->index(); // pending | in_progress | done
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status']);
        });

        $now = now();
        $rows = [];
        $defaults = [
            'source' => ['IndiaMART', 'TradeIndia', 'Website', 'WhatsApp', 'Call', 'Email', 'Referral', 'Exhibition', 'Import', 'Other'],
            'client_type' => ['Third-party', 'PCD Franchise', 'Distributor', 'Hospital', 'Export', 'Other'],
            'lost_reason' => ['Price too high', 'Bought from another company', 'Product not available', 'No response', 'Not a real buyer', 'Other'],
            'product_category' => ['Analgesic', 'Antibiotic', 'Gastro', 'Respiratory', 'Nutraceutical', 'Cardiac', 'Diabetic', 'Derma', 'Other'],
        ];
        foreach ($defaults as $type => $values) {
            foreach ($values as $i => $v) {
                $rows[] = ['type' => $type, 'value' => $v, 'sort' => $i, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('master_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('master_options');
        Schema::table('company_settings', fn (Blueprint $t) => $t->dropColumn('permissions'));
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['temperature']);
            $table->dropIndex(['email_hash']);
            $table->dropColumn(['temperature', 'email_hash']);
        });
    }
};
