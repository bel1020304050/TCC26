<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Promocao;
use App\Support\Status;
use Illuminate\Database\Seeder;

/**
 * Promoções de exemplo, com datas relativas a hoje para a situação
 * aparecer certa em qualquer dia: Vigente, Agendada, Inativa e Expirada. Só com APP_ENV=local.
 */
class PromocoesTesteSeeder extends Seeder
{
    public function run(): void
    {
        $categoria = fn (string $nome) => Categoria::where('nome', $nome)->value('id');
        $hoje = today();

        foreach ([
            ['Semana Mary', 'cupom', 'MARY20', 'percentual', 20, -7, 7, 'geral', null, Status::ATIVO],
            ['Queima de Saias', 'automatica', null, 'percentual', 15, -3, 14, 'categoria', 'Saias', Status::ATIVO],
            // Cupom de categoria (antes era o "Cliente VIP"): mostra a dica de cupom na página dos vestidos
            ['Festival de Vestidos', 'cupom', 'VESTIDO30', 'percentual', 30, -2, 10, 'categoria', 'Vestidos', Status::ATIVO],
            // Agendada: ainda não aparece na lista de cupons do carrinho
            ['Black Friday', 'cupom', 'BLACK25', 'percentual', 25, 15, 18, 'geral', null, Status::ATIVO],
            ['Desconto Blusas', 'automatica', null, 'valor', 10, -10, 20, 'categoria', 'Blusas', Status::INATIVO],
            ['Liquidação Inverno', 'automatica', null, 'percentual', 25, -60, -30, 'categoria', 'Jaquetas', Status::ATIVO],
        ] as [$nome, $modo, $codigo, $tipo, $valor, $inicio, $fim, $aplicacao, $alvo, $status]) {
            $promocao = Promocao::firstOrNew(['nome' => $nome]);
            $promocao->fill([
                'modo' => $modo, 'codigo' => $codigo, 'tipo' => $tipo, 'valor' => $valor,
                'data_inicio' => $hoje->copy()->addDays($inicio), 'data_fim' => $hoje->copy()->addDays($fim),
                'aplicacao' => $aplicacao, 'categoria_id' => $alvo ? $categoria($alvo) : null,
            ]);
            $promocao->status = $status;
            $promocao->save();
        }
    }
}
