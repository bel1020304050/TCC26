<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos, itens do pedido e histórico de status.
 * Valores, itens e endereço são COPIADOS no momento da criação ("congelados"):
 * se o produto ou o cadastro do cliente mudar depois, o pedido continua igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('carrinho_id')->nullable()->unique()->constrained('carrinhos'); // 1 pedido por carrinho
            $table->string('status', 30)->default('aguardando_pagamento')->index();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('cupom', 40)->nullable();          // código do cupom usado (se houver)
            $table->string('forma_pagamento', 20)->nullable(); // pix, cartao, boleto (preenchido quando o pagamento é aprovado)

            // Endereço de entrega copiado do cadastro
            $table->string('cep', 9);
            $table->string('rua');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro', 120);
            $table->string('cidade', 120);
            $table->char('uf', 2);

            $table->timestamps();
        });

        Schema::create('pedido_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->string('nome');
            $table->string('tamanho', 20);
            $table->string('cor', 40);
            $table->unsignedInteger('quantidade');
            $table->decimal('preco_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
        });

        // Histórico de status: cada mudança com data, hora e responsável
        Schema::create('pedido_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('status', 30);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('observacao')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_historicos');
        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
    }
};
