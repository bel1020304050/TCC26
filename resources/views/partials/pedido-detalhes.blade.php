{{-- Detalhes do pedido (3.19): itens, valores, endereço, pagamento e histórico de status.
     Usado na área do cliente e no painel. $comResponsavel = mostra quem fez cada mudança (painel). --}}
@php $comResponsavel = $comResponsavel ?? false; @endphp
<div class="row g-3">
    <div class="col-lg-7">
        <div class="mm-card p-4 h-100">
            <h2 class="h6 fw-semibold mb-3">Itens</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Produto</th><th class="text-center">Qtd.</th><th class="text-end">Preço</th><th class="text-end">Subtotal</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($pedido->itens as $item)
                            <tr>
                                <td>{{ $item->descricao() }}</td>
                                <td class="text-center">{{ $item->quantidade }}</td>
                                <td class="text-end text-nowrap">{{ $item->valor('preco_unitario') }}</td>
                                <td class="text-end text-nowrap">{{ $item->valor('subtotal') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end text-nowrap">{{ $pedido->valor('subtotal') }}</td></tr>
                        <tr>
                            <td colspan="3" class="text-end">Desconto @if ($pedido->cupom) (cupom {{ $pedido->cupom }}) @endif</td>
                            <td class="text-end text-nowrap text-success">- {{ $pedido->valor('desconto') }}</td>
                        </tr>
                        <tr class="fw-bold"><td colspan="3" class="text-end border-0">Total</td><td class="text-end text-nowrap mm-preco border-0">{{ $pedido->valor('total') }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5 d-flex flex-column gap-3">
        <div class="mm-card p-4">
            <h2 class="h6 fw-semibold mb-2">Entrega e pagamento</h2>
            <div>{{ $pedido->enderecoLinha1() }}</div>
            <div class="text-muted small mb-2">{{ $pedido->enderecoLinha2() }}</div>
            <div class="small"><span class="text-muted">Pagamento:</span> {{ $pedido->formaPagamentoLabel() }}</div>
            <div class="small text-muted">Frete: não calculado nesta versão.</div>
        </div>
        <div class="mm-card p-4">
            <h2 class="h6 fw-semibold mb-3">Histórico de status</h2>
            <ul class="mm-linha-tempo list-unstyled mb-0">
                @foreach ($pedido->historico as $registro)
                    <li>
                        <span class="text-muted small">{{ $registro->created_at->format('d/m/Y H:i') }}</span>
                        · {{ $registro->statusLabel() }}
                        @if ($comResponsavel)
                            <span class="text-muted small">— {{ $registro->responsavel?->name ?? 'Sistema' }}</span>
                        @endif
                        @if ($registro->observacao)
                            <div class="small text-muted">{{ $registro->observacao }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
