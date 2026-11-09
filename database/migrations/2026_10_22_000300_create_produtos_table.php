<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produtos. Cada combinação de tamanho e cor é um registro próprio, com estoque próprio.
     * A regra "não inativar categoria/fornecedor com produtos ativos" consulta esta tabela.
     */
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->text('descricao')->nullable();
            // restrictOnDelete: o banco impede apagar categoria/fornecedor que tenha produto
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->restrictOnDelete();
            $table->decimal('preco', 10, 2);
            $table->unsignedInteger('estoque')->default(0);
            $table->string('tamanho', 20);
            $table->string('cor', 30);
            $table->string('imagem')->nullable();
            $table->string('status', 20)->default('ativo')->index(); // ativo | inativo
            $table->timestamps();

            // Não pode repetir Nome + Categoria + Tamanho + Cor
            $table->unique(['nome', 'categoria_id', 'tamanho', 'cor'], 'produtos_variacao_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
