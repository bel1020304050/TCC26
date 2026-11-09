<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoções automáticas e cupons de desconto.
 * "status" guarda só o manual (Ativa/Inativa). Agendada, Vigente e Expirada são calculadas pelas datas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->string('modo', 20);                     // automatica | cupom
            $table->string('codigo', 30)->nullable()->unique(); // só no modo cupom
            $table->string('tipo', 20);                     // percentual | valor
            $table->decimal('valor', 10, 2);
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->string('aplicacao', 20);                // geral | produto | categoria
            $table->foreignId('produto_id')->nullable()->constrained('produtos');
            $table->foreignId('categoria_id')->nullable()->constrained('categorias');
            $table->string('status', 10)->default('ativo')->index();
            $table->timestamps();
        });

        // Cupom digitado pelo cliente no carrinho (revalidado a cada atualização)
        Schema::table('carrinhos', function (Blueprint $table) {
            $table->string('cupom', 30)->nullable()->after('status');
        });

        // Promoção usada no pedido (o desconto em si já fica congelado no pedido)
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('promocao_id')->nullable()->after('cupom')->constrained('promocoes');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promocao_id');
        });
        Schema::table('carrinhos', function (Blueprint $table) {
            $table->dropColumn('cupom');
        });
        Schema::dropIfExists('promocoes');
    }
};
