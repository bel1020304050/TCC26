@extends('layouts.loja')

@section('titulo', 'Pagamento do pedido '.$pedido->numero())

@section('conteudo')
{{-- Tela de pagamento: escolher PIX, cartão ou boleto --}}
<x-etapas-compra :atual="3" />
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Pagamento do pedido {{ $pedido->numero() }}</h1>
    <x-status-pedido :status="$pedido->status" />
</div>

<div class="alert alert-secondary small py-2">
    <i class="bi bi-cone-striped me-1"></i>
    <strong>Ambiente de pagamento simulado (MVP do TCC).</strong> Nenhum valor é cobrado de verdade.
    Cartões de teste: final <code>0000</code> = recusado · final <code>9999</code> = provedor fora do ar · qualquer outro = aprovado.
</div>

<div class="row g-3">
    <div class="col-lg-7">
        {{-- PIX ou boleto já gerado, aguardando confirmação --}}
        @if ($pendente)
            <div class="mm-card p-4 mb-3 border-warning">
                @if ($pendente->forma === 'pix')
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-qr-code"></i> PIX gerado · aguardando pagamento</h2>
                    <div class="d-flex flex-wrap gap-4 align-items-start">
                        <x-qr-ficticio :codigo="$pendente->pix_codigo" />
                        <div class="flex-grow-1" style="min-width: 220px;">
                            <label class="form-label" for="pix-codigo">PIX copia e cola</label>
                            <div class="input-group mb-2">
                                <input id="pix-codigo" class="form-control form-control-sm font-monospace" value="{{ $pendente->pix_codigo }}" readonly>
                                <button class="btn btn-outline-secondary btn-sm" type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('pix-codigo').value); this.textContent='Copiado!'">Copiar</button>
                            </div>
                            <p class="small text-muted">Valor: <strong>{{ $pendente->valorFormatado() }}</strong>. No sistema real, o banco avisaria a loja quando o PIX fosse pago.</p>
                            <form method="POST" action="{{ route('loja.pagamento.simular-pix', [$pedido, $pendente]) }}" onsubmit="this.querySelector('button').disabled = true;">
                                @csrf
                                <button type="submit" class="btn btn-mm"><i class="bi bi-check2-circle"></i> Simular pagamento recebido</button>
                            </form>
                        </div>
                    </div>
                @else
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-upc"></i> Boleto gerado · aguardando compensação</h2>
                    <label class="form-label" for="boleto-linha">Linha digitável</label>
                    <input id="boleto-linha" class="form-control font-monospace mb-2" value="{{ $pendente->boleto_linha }}" readonly>
                    <p class="small text-muted mb-0">
                        Valor {{ $pendente->valorFormatado() }} · vence em {{ $pendente->boleto_vencimento->format('d/m/Y') }}.
                        O pedido continua <strong>Aguardando Pagamento</strong> até a compensação, que no MVP é confirmada pelo Administrador no painel.
                    </p>
                @endif
            </div>
            <p class="small text-muted">Prefere outra forma? Escolha abaixo; o {{ $pendente->forma === 'pix' ? 'PIX' : 'boleto' }} acima deixa de valer.</p>
        @endif

        <div class="mm-card p-4">
            <h2 class="h6 fw-semibold mb-3">Forma de pagamento</h2>
            @php $aba = old('forma', session('aba', 'pix')); @endphp
            <ul class="nav nav-pills mb-3" role="tablist">
                @foreach (['pix' => ['PIX', 'qr-code'], 'cartao' => ['Cartão de Crédito', 'credit-card'], 'boleto' => ['Boleto Bancário', 'upc']] as $forma => [$rotulo, $icone])
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $aba === $forma ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#forma-{{ $forma }}" type="button" role="tab">
                            <i class="bi bi-{{ $icone }}"></i> {{ $rotulo }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade {{ $aba === 'pix' ? 'show active' : '' }}" id="forma-pix" role="tabpanel">
                    <p class="text-muted">Gera um QR Code e um código "copia e cola". A aprovação é imediata após o pagamento.</p>
                    <form method="POST" action="{{ route('loja.pagamento.store', $pedido) }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf <input type="hidden" name="forma" value="pix">
                        <button type="submit" class="btn btn-mm">Gerar PIX de {{ $pedido->valor('total') }}</button>
                    </form>
                </div>

                <div class="tab-pane fade {{ $aba === 'cartao' ? 'show active' : '' }}" id="forma-cartao" role="tabpanel">
                    {{-- Os dados do cartão não são salvos: só bandeira e 4 últimos dígitos ficam no histórico --}}
                    <form method="POST" action="{{ route('loja.pagamento.store', $pedido) }}" autocomplete="off" novalidate onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                        @csrf <input type="hidden" name="forma" value="cartao">
                        <div class="row g-2">
                            <div class="col-12">
                                <x-campo name="cartao_numero" label="Número do cartão" placeholder="4111 1111 1111 1111" inputmode="numeric" maxlength="23" data-mascara="cartao" obrigatorio />
                            </div>
                            <div class="col-12">
                                <x-campo name="cartao_nome" label="Nome impresso no cartão" placeholder="ANA SOUZA" obrigatorio />
                            </div>
                            <div class="col-6">
                                <x-campo name="cartao_validade" label="Validade" placeholder="MM/AA" inputmode="numeric" maxlength="5" data-mascara="validade" obrigatorio />
                            </div>
                            <div class="col-6">
                                <x-campo name="cartao_cvv" label="CVV" placeholder="123" inputmode="numeric" maxlength="4" obrigatorio />
                            </div>
                        </div>
                        <button type="submit" class="btn btn-mm mt-2">Pagar {{ $pedido->valor('total') }}</button>
                    </form>
                </div>

                <div class="tab-pane fade {{ $aba === 'boleto' ? 'show active' : '' }}" id="forma-boleto" role="tabpanel">
                    <p class="text-muted">Gera a linha digitável com vencimento em 3 dias. O pedido é confirmado quando o pagamento compensar.</p>
                    <form method="POST" action="{{ route('loja.pagamento.store', $pedido) }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf <input type="hidden" name="forma" value="boleto">
                        <button type="submit" class="btn btn-mm">Gerar boleto de {{ $pedido->valor('total') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="mm-card p-4 mb-3">
            <h2 class="h6 fw-semibold mb-3">Resumo do pedido</h2>
            @foreach ($pedido->itens as $item)
                <div class="d-flex justify-content-between gap-3 mb-1 small">
                    <span>{{ $item->descricao() }} · {{ $item->quantidade }}×</span>
                    <span class="text-nowrap">{{ $item->valor('subtotal') }}</span>
                </div>
            @endforeach
            <hr>
            <div class="d-flex justify-content-between small"><span>Subtotal</span><span>{{ $pedido->valor('subtotal') }}</span></div>
            <div class="d-flex justify-content-between small text-success"><span>Desconto</span><span>- {{ $pedido->valor('desconto') }}</span></div>
            <div class="d-flex justify-content-between fs-5 fw-bold mt-2"><span>Total</span><span class="mm-preco">{{ $pedido->valor('total') }}</span></div>
            <hr>
            <div class="small text-muted">Entrega: {{ $pedido->enderecoLinha1() }} · {{ $pedido->enderecoLinha2() }}</div>
        </div>

        @if ($pedido->pagamentos->isNotEmpty())
            <div class="mm-card p-4">
                <h2 class="h6 fw-semibold mb-2">Tentativas de pagamento</h2>
                @include('partials.pagamentos-lista', ['pagamentos' => $pedido->pagamentos])
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Máscaras simples: "4111 1111 1111 1111" e "MM/AA"
    document.querySelectorAll('[data-mascara]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            const d = campo.value.replace(/\D/g, '');
            campo.value = campo.dataset.mascara === 'cartao'
                ? d.slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ')
                : d.slice(0, 4).replace(/^(\d{2})(\d)/, '$1/$2');
        });
    });
</script>
@endpush
