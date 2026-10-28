<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storages', function (Blueprint $table) {
            // Адрес для ссылок, которые открывает браузер, если сервер ходит к хранилищу по внутреннему адресу
            $table->string('public_endpoint')->nullable()->after('endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('storages', function (Blueprint $table) {
            $table->dropColumn('public_endpoint');
        });
    }
};
