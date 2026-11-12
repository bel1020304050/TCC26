<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integração com o Asaas:
     * - pagamentos: imagem do QR Code do PIX e o link do boleto (PDF) ou da fatura (cartão)
     * - users: código do cliente no Asaas (criado no primeiro pagamento e reaproveitado)
     */
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->longText('pix_qr')->nullable()->after('pix_codigo');            // imagem PNG em base64
            $table->string('link_pagamento', 255)->nullable()->after('boleto_vencimento'); // boleto PDF ou fatura
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('asaas_cliente_id', 40)->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('asaas_cliente_id');
        });
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->dropColumn(['pix_qr', 'link_pagamento']);
        });
    }
};
