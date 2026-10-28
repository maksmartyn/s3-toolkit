<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storages', function (Blueprint $table) {
            // Только отличия от таблицы по умолчанию для провайдера: {capability: bool}
            $table->json('capabilities')->nullable()->after('buckets');
        });

        Schema::create('background_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('bucket');
            $table->string('status', 16);
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('total')->nullable();
            // Состояние для продолжения в следующем запуске задачи (этап, маркеры пагинации)
            $table->json('state')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['storage_id', 'status']);
            $table->index(['storage_id', 'bucket']);
        });

        Schema::create('bucket_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_id')->constrained()->cascadeOnDelete();
            $table->string('bucket');
            $table->unsignedBigInteger('object_count');
            $table->unsignedBigInteger('total_size');
            // С учётом всех версий, если у хранилища есть версионирование
            $table->unsignedBigInteger('version_count')->nullable();
            $table->unsignedBigInteger('versions_size')->nullable();
            $table->timestamp('calculated_at');

            $table->unique(['storage_id', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bucket_stats');
        Schema::dropIfExists('background_operations');

        Schema::table('storages', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};
