<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfis da equipe passam a ser Administrador, Gerente e Atendente/Vendedor.
 * O antigo "Funcionário" vira Atendente/Vendedor, e o campo "cargo" (só informativo) sai,
 * porque agora o próprio perfil diz a função e as permissões de cada pessoa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Banco: converte os dados antes de mexer na estrutura, para ninguém perder o acesso
        DB::table('users')->where('perfil', 'funcionario')->update(['perfil' => 'atendente']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cargo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cargo', 60)->nullable()->after('perfil');
        });

        DB::table('users')->whereIn('perfil', ['atendente', 'gerente'])->update(['perfil' => 'funcionario']);
    }
};
