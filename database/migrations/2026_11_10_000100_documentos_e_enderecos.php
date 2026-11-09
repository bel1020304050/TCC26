<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fornecedor: tipo de pessoa (física/jurídica) e endereço.
     * Cliente: CPF (salvo só com números, único quando informado).
     * As colunas aceitam vazio no banco para não quebrar registros antigos;
     * a obrigatoriedade é garantida pelos formulários.
     */
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('tipo_pessoa', 10)->default('juridica')->after('nome'); // fisica | juridica
            $table->string('cep', 9)->nullable()->after('email');
            $table->string('rua', 120)->nullable()->after('cep');
            $table->string('numero', 10)->nullable()->after('rua');
            $table->string('complemento', 60)->nullable()->after('numero');
            $table->string('bairro', 80)->nullable()->after('complemento');
            $table->string('cidade', 80)->nullable()->after('bairro');
            $table->char('uf', 2)->nullable()->after('cidade');
        });

        // Quem já tinha CPF (11 caracteres) vira pessoa física. LENGTH funciona no MySQL e no SQLite dos testes.
        DB::table('fornecedores')->whereRaw('LENGTH(documento) = 11')->update(['tipo_pessoa' => 'fisica']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->unique()->after('telefone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropColumn(['tipo_pessoa', 'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf']);
        });
    }
};
