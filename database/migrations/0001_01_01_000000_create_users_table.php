<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            // Уникальность email только среди неудалённых: у удалённых колонка NULL
            $table->string('active_email')
                ->nullable()
                ->storedAs('IF(`deleted_at` IS NULL, `email`, NULL)')
                ->unique();
            $table->string('password');
            $table->boolean('password_change_required')->default(false);
            $table->boolean('is_superadmin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('permissions')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
