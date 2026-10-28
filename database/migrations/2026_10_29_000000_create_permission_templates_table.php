<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope', 16);
            $table->unsignedBigInteger('permissions')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_templates');
    }
};
