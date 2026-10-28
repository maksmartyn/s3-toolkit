<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('provider', 32);
            $table->string('endpoint')->nullable();
            $table->string('region', 64);
            $table->boolean('use_path_style')->default(false);
            $table->boolean('verify_ssl')->default(true);
            // Ключи и данные API управления шифруются ключом приложения (cast encrypted)
            $table->text('access_key_id');
            $table->text('secret_access_key');
            $table->text('admin_credentials')->nullable();
            // Ключ ограничен бакетами: работаем только с ними и не вызываем ListBuckets
            $table->json('buckets')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('storage_user', function (Blueprint $table) {
            $table->foreignId('storage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('permissions')->default(0);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->primary(['storage_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_user');
        Schema::dropIfExists('storages');
    }
};
