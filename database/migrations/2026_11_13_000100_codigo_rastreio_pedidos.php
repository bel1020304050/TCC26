<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Código de rastreio (simulado), gerado quando o pedido passa para "Enviado"
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('codigo_rastreio', 13)->nullable()->unique()->after('forma_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropUnique(['codigo_rastreio']);
            $table->dropColumn('codigo_rastreio');
        });
    }
};
