<?php

namespace App\Relatorios;

use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Story 3.32 – Relatório de Vendas (só Administrador).
 * Venda paga = Pago, Em Separação, Enviado ou Entregue. O resto aparece na lista, mas não entra no faturamento.
 */
class RelatorioVendas extends Relatorio
{
    public function titulo(): string
    {
        return 'Relatório de vendas';
    }

    public function permissao(): string
    {
        return 'relatorio-vendas';
    }

    protected function lerFiltros(Request $request): array
    {
        [$de, $ate] = $this->periodo($request);
        $status = (string) $request->query('status');
        $forma = (string) $request->query('forma');

        return [
            'de' => $de, 'ate' => $ate,
            'status' => array_key_exists($status, Pedido::STATUS) ? $status : '',
            'forma' => array_key_exists($forma, Pedido::FORMAS_PAGAMENTO) ? $forma : '',
        ];
    }

    protected function consulta(): Builder
    {
        $f = $this->filtros;

        return Pedido::query()
            ->with('cliente:id,name,status')
            ->whereBetween('created_at', [$f['de'], $f['ate']])
            ->when($f['status'], fn ($q) => $q->where('status', $f['status']))
            ->when($f['forma'], fn ($q) => $q->where('forma_pagamento', $f['forma']))
            ->latest('created_at')->latest('id');
    }

    public function colunas(): array
    {
        return ['Pedido', 'Data', 'Cliente', 'Status', 'Pagamento', 'Total', 'Entra no faturamento'];
    }

    public function colunaStatus(): ?int
    {
        return 3;
    }

    public function linha(Model $pedido): array
    {
        $pago = in_array($pedido->status, Pedido::VENDA_PAGA, true);

        return [
            $pedido->numero(),
            $pedido->created_at->format('d/m/Y'),
            $pedido->cliente->name,
            $pedido->statusLabel(),
            // Forma da tentativa aprovada (gravada no pedido quando o pagamento é aprovado)
            $pedido->forma_pagamento ? Pedido::FORMAS_PAGAMENTO[$pedido->forma_pagamento] : '—',
            self::dinheiro($pedido->total),
            $pago ? 'Sim' : 'Não',
        ];
    }

    public function indicadores(): array
    {
        $pagas = $this->consulta()->whereIn('status', Pedido::VENDA_PAGA);
        $quantidade = (clone $pagas)->count();
        $faturamento = (float) (clone $pagas)->sum('total');

        return [
            'Vendas pagas no período' => (string) $quantidade,
            'Faturamento' => self::dinheiro($faturamento),
            'Ticket médio' => self::dinheiro($quantidade ? $faturamento / $quantidade : 0),
        ];
    }

    public function descricaoFiltros(): string
    {
        $f = $this->filtros;

        return 'Período: '.$f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y')
            .' · Status: '.($f['status'] ? Pedido::STATUS[$f['status']] : 'Todos')
            .' · Pagamento: '.($f['forma'] ? Pedido::FORMAS_PAGAMENTO[$f['forma']] : 'Todas');
    }

    public function rodape(): string
    {
        return 'Faturamento considera Pago, Em Separação, Enviado e Entregue. Aguardando Pagamento, Cancelado e estornos não entram.';
    }
}
