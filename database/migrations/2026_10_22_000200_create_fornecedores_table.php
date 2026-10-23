<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fornecedores — stories 3.26 a 3.29. Fornecedor não é usuário e não faz login.
     */
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            // CPF (11 dígitos) ou CNPJ (14 dígitos), salvo só com números. Opcional, mas único quando informado.
            $table->string('documento', 14)->nullable()->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('status', 20)->default('ativo')->index(); // ativo | inativo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
