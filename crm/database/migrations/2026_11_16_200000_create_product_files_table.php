<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price lists, catalogues, visualisers, COAs… shared with the whole team.
        Schema::create('product_files', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('category', 60)->nullable();
            $table->string('path');                         // inside storage/app/private, never public
            $table->string('original_name');
            $table->string('extension', 8);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_files');
    }
};
