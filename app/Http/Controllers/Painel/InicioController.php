<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Produto;
use App\Relatorios\RelatorioEstoque;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página inicial do painel: resumo do que precisa de atenção na loja hoje.
 * Cada número é um atalho para a tela onde a equipe resolve aquilo.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $inicioDoMes = now()->startOfMonth();

        // Banco: 4 contagens simples, uma consulta para cada indicador
        $resumo = [
            'aguardando' => Pedido::where('status', Pedido::AGUARDANDO_PAGAMENTO)->count(),
            'separar' => Pedido::where('status', Pedido::PAGO)->count(),
            'estoqueBaixo' => Produto::ativos()->where('estoque', '<=', RelatorioEstoque::LIMITE_BAIXO)->count(),
            // Regra: faturamento do mês = pedidos pagos (Pago, Em Separação, Enviado ou Entregue) criados neste mês
            'vendasMes' => (float) Pedido::whereIn('status', Pedido::VENDA_PAGA)->where('created_at', '>=', $inicioDoMes)->sum('total'),
        ];

        $ultimosPedidos = Pedido::with('cliente:id,name,status')->latest('id')->limit(5)->get();

        return view('painel.inicio', [
            'usuario' => $request->user(),
            'resumo' => $resumo,
            'ultimosPedidos' => $ultimosPedidos,
        ]);
    }
}
