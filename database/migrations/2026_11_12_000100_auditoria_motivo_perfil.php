<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoria: guarda o motivo (inativar, reativar e cancelar) e o perfil de quem fez a ação
     * no momento da ação (se a pessoa mudar de perfil depois, o registro antigo continua certo).
     */
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->string('perfil', 20)->nullable()->after('user_id'); // admin | gerente | atendente | cliente | sistema
            $table->string('motivo', 500)->nullable()->after('detalhes');
            $table->index('created_at');
        });

        // Preenche o perfil dos registros antigos com o perfil atual de quem fez
        DB::table('auditorias')->whereNotNull('user_id')->update([
            'perfil' => DB::raw('(select perfil from users where users.id = auditorias.user_id)'),
        ]);

        // O motivo do cancelamento ficava dentro de "detalhes": passa para a coluna própria
        foreach (DB::table('auditorias')->where('acao', 'cancelou')->get(['id', 'detalhes']) as $registro) {
            $detalhes = json_decode((string) $registro->detalhes, true) ?: [];
            DB::table('auditorias')->where('id', $registro->id)->update([
                'motivo' => $detalhes['motivo'] ?? null,
                'detalhes' => json_encode(array_diff_key($detalhes, ['motivo' => 1]) ?: null),
            ]);
        }

        // LGPD: alterações feitas pelo cliente na própria conta ficam só como fato (sem nome, e-mail, endereço...)
        DB::table('auditorias')
            ->where('entidade', 'users')->where('acao', 'editou')->where('perfil', 'cliente')
            ->update(['detalhes' => null]);
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['perfil', 'motivo']);
        });
    }
};
