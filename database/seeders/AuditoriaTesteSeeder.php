<?php

namespace Database\Seeders;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Histórico de auditoria de exemplo (só ambiente local), coerente com os dados de teste:
 * explica por que a categoria Jaquetas, o fornecedor Jeans Sul, a Jaqueta Jeans, a promoção Desconto Blusas
 * e a atendente Juliana estão inativos. Assim o relatório de Auditoria já abre com conteúdo na apresentação.
 */
class AuditoriaTesteSeeder extends Seeder
{
    public function run(): void
    {
        if (Auditoria::whereNotNull('motivo')->exists()) {
            return; // já rodou
        }

        $admin = User::where('email', 'mary@marymodas.com.br')->first() ?? User::where('perfil', User::PERFIL_ADMIN)->first();
        $gerente = User::where('email', 'bruno@marymodas.com.br')->first() ?? $admin;

        $historico = [
            // [quem, ação, registro, motivo, dias atrás, detalhes]
            [$gerente, 'inativou', Fornecedor::where('nome', 'Jeans Sul Ltda')->first(), 'Fornecedor encerrou as atividades em outubro', 9, []],
            [$gerente, 'inativou', Produto::where('nome', 'Jaqueta Jeans')->first(), 'Sem estoque e sem reposição do fornecedor', 9, []],
            [$admin, 'inativou', Categoria::where('nome', 'Jaquetas')->first(), 'Fim da coleção de inverno', 8, []],
            [$gerente, 'inativou', Promocao::where('nome', 'Desconto Blusas')->first(), 'Campanha encerrada antes do previsto', 5, []],
            [$admin, 'inativou', User::where('email', 'juliana@marymodas.com.br')->first(), 'Contrato de experiência encerrado', 3, []],
            [$gerente, 'editou', Produto::where('nome', 'Vestido Floral')->first(), null, 2,
                ['antes' => ['preco' => '149.90', 'estoque' => 5], 'depois' => ['preco' => '129.90', 'estoque' => 8]]],
        ];

        foreach ($historico as [$quem, $acao, $registro, $motivo, $dias, $detalhes]) {
            if (! $quem || ! $registro) {
                continue;
            }
            $linha = new Auditoria([
                'user_id' => $quem->id, 'perfil' => $quem->perfil, 'acao' => $acao, 'entidade' => $registro->getTable(),
                'entidade_id' => $registro->id, 'detalhes' => $detalhes ?: null, 'motivo' => $motivo,
            ]);
            $linha->created_at = now()->subDays($dias)->setTime(10 + $dias % 7, 15);
            $linha->save();
        }
    }
}
