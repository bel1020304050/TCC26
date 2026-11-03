<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RNF.01 da 3.20: toda mudança de status registra o status ANTERIOR e o novo, com data, hora e responsável.
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
