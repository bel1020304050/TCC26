<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tentativas de pagamento dos pedidos.
 * Cada tentativa fica registrada com status Pendente, Aprovado, Recusado ou Estornado.
 * Dados de cartão NÃO são guardados: só a bandeira e os 4 últimos dígitos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->string('forma', 20);                    // pix, cartao, boleto
            $table->string('status', 20)->default('pendente')->index();
            $table->decimal('valor', 10, 2);
            $table->string('referencia', 60)->nullable();   // código da transação no provedor
            $table->string('mensagem')->nullable();         // motivo da recusa/estorno

            $table->string('cartao_bandeira', 20)->nullable();
            $table->char('cartao_final', 4)->nullable();
            $table->text('pix_codigo')->nullable();
            $table->string('boleto_linha', 60)->nullable();
            $table->date('boleto_vencimento')->nullable();

            // Quando a aprovação foi processada. Se já estiver preenchido, uma 2ª aprovação é ignorada
            // (assim o estoque nunca é baixado duas vezes)
            $table->timestamp('processado_em')->nullable();
            $table->foreignId('confirmado_por')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamentos');
    }
};
