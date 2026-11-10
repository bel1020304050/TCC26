<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Senha provisória da equipe: quando o Administrador ou o Gerente gera uma senha para alguém,
     * a pessoa é obrigada a criar a própria senha no próximo login.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('senha_provisoria')->default(false)->after('password');
        });

        // Contas que já existiam continuam entrando normalmente: consideramos o e-mail já confirmado
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('senha_provisoria');
        });
    }
};
