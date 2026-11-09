<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico do pedido: guarda também o status ANTERIOR de cada mudança.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_historicos', function (Blueprint $table) {
            $table->string('status_anterior', 30)->nullable()->after('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_historicos', function (Blueprint $table) {
            $table->dropColumn('status_anterior');
        });
    }
};
