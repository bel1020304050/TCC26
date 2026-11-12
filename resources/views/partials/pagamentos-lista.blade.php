{{-- Tentativas de pagamento do pedido (cada uma com status: Pendente, Aprovado, Recusado ou Estornado) --}}
@php $podeConfirmar = $podeConfirmar ?? false; @endphp
<ul class="list-unstyled mb-0">
    @foreach ($pagamentos as $pagamento)
        <li class="d-flex flex-wrap align-items-center gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
            <span class="badge rounded-pill {{ $pagamento->corStatus() }}">{{ $pagamento->statusLabel() }}</span>
            <span class="fw-semibold small">{{ $pagamento->formaLabel() }}</span>
            @if ($pagamento->detalhe()) <span class="small text-muted">{{ $pagamento->detalhe() }}</span> @endif
            <span class="small text-muted ms-auto">{{ $pagamento->created_at->format('d/m/Y H:i') }}</span>
            @if ($pagamento->mensagem)
                <div class="w-100 small text-muted">
                    {{ $pagamento->mensagem }}
                    @if ($pagamento->confirmadoPor) · confirmado por {{ $pagamento->confirmadoPor->name }} @endif
                </div>
            @endif
            @if ($pagamento->isPendente() && $pagamento->link_pagamento)
                <div class="w-100 small">
                    <a href="{{ $pagamento->link_pagamento }}" target="_blank" rel="noopener">
                        {{ $pagamento->forma === 'boleto' ? 'Abrir boleto (PDF)' : 'Abrir fatura no Asaas' }} <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            @endif
            @if ($podeConfirmar && auth()->user()->can('confirmarBoleto', $pagamento))
                <form method="POST" action="{{ route('painel.pagamentos.confirmar-boleto', $pagamento) }}" class="w-100"
                      onsubmit="return confirm('Confirmar a compensação deste boleto? O pedido ficará Pago e o estoque será baixado.');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-mm"><i class="bi bi-check2-circle"></i> Confirmar compensação do boleto</button>
                </form>
            @endif
        </li>
    @endforeach
</ul>
