@extends('layouts.loja')

@section('titulo', 'Pagamento do pedido '.$pedido->numero())

@section('conteudo')
{{-- Tela de pagamento: escolher PIX, cartão ou boleto --}}
<x-etapas-compra :atual="3" />
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Pagamento do pedido {{ $pedido->numero() }}</h1>
    <x-status-pedido :status="$pedido->status" />
</div>

@if ($asaas)
    <div class="alert alert-info small py-2">
        <i class="bi bi-shield-check me-1"></i>
        <strong>Pagamento pelo Asaas</strong>
    </div>
@else
    <div class="alert alert-secondary small py-2">
        <i class="bi bi-cone-striped me-1"></i>
        <strong>Ambiente de pagamento simulado (MVP do TCC).</strong> Nenhum valor é cobrado de verdade.
        Cartões de teste: final <code>0000</code> = recusado · final <code>9999</code> = provedor fora do ar · qualquer outro = aprovado.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        {{-- Cobrança já gerada (PIX, boleto ou cartão no Asaas), aguardando confirmação --}}
        @if ($pendente)
            <div class="mm-card p-4 mb-3 border-warning">
                @if ($pendente->forma === 'pix')
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-qr-code"></i> PIX gerado · aguardando pagamento</h2>
                    <div class="d-flex flex-wrap gap-4 align-items-start">
                        @if ($pendente->pix_qr)
                            {{-- QR Code real devolvido pelo Asaas (imagem PNG em base64) --}}
                            <img src="data:image/png;base64,{{ $pendente->pix_qr }}" alt="QR Code do PIX" width="168" height="168" class="border rounded">
                        @elseif ($pendente->pix_codigo)
                            <x-qr-ficticio :codigo="$pendente->pix_codigo" />
                        @endif
                        <div class="flex-grow-1" style="min-width: 220px;">
                            @if ($pendente->pix_codigo)
                                <label class="form-label" for="pix-codigo">PIX copia e cola</label>
                                <div class="input-group mb-2">
                                    <input id="pix-codigo" class="form-control form-control-sm font-monospace" value="{{ $pendente->pix_codigo }}" readonly>
                                    <button class="btn btn-outline-secondary btn-sm" type="button"
                                            onclick="navigator.clipboard.writeText(document.getElementById('pix-codigo').value); this.textContent='Copiado!'">Copiar</button>
                                </div>
                            @elseif ($pendente->link_pagamento)
                                <p class="small"><a href="{{ $pendente->link_pagamento }}" target="_blank" rel="noopener">Abrir a fatura do Asaas para ver o QR Code <i class="bi bi-box-arrow-up-right"></i></a></p>
                            @endif
                            <p class="small text-muted">
                                Valor: <strong>{{ $pendente->valorFormatado() }}</strong>.
                                @unless ($asaas) No sistema real, o banco avisaria a loja quando o PIX fosse pago. @endunless
                            </p>
                            @if ($asaas) <div class="mb-2"><p class="small text-muted mb-0" data-aguardando><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Aguardando o pagamento… esta página atualiza sozinha.</p></div> @endif
                            <div class="d-flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('loja.pagamento.simular-pix', [$pedido, $pendente]) }}" onsubmit="this.querySelector('button').disabled = true;">
                                    @csrf
                                    <button type="submit" class="btn {{ $asaas ? 'btn-outline-secondary' : 'btn-mm' }}">
                                        <i class="bi bi-check2-circle"></i> {{ $asaas ? 'Simular pagamento (sandbox)' : 'Simular pagamento recebido' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @elseif ($pendente->forma === 'boleto')
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-upc"></i> Boleto gerado · aguardando compensação</h2>
                    @if ($pendente->boleto_linha)
                        <label class="form-label" for="boleto-linha">Linha digitável</label>
                        <input id="boleto-linha" class="form-control font-monospace mb-2" value="{{ $pendente->boleto_linha }}" readonly>
                    @endif
                    <p class="small text-muted">
                        Valor {{ $pendente->valorFormatado() }}@if ($pendente->boleto_vencimento) · vence em {{ $pendente->boleto_vencimento->format('d/m/Y') }}@endif.
                        @if ($asaas)
                            O pedido fica <strong>Aguardando Pagamento</strong> até o Asaas confirmar a compensação.
                        @else
                            O pedido continua <strong>Aguardando Pagamento</strong> até a compensação, que no MVP é confirmada pelo Administrador no painel.
                        @endif
                    </p>
                    @if ($asaas)
                        @if ($pendente->link_pagamento)
                            <a href="{{ $pendente->link_pagamento }}" target="_blank" rel="noopener" class="btn btn-outline-secondary mb-2"><i class="bi bi-file-earmark-pdf"></i> Abrir boleto (PDF)</a>
                        @endif
                        <p class="small text-muted mb-0" data-aguardando><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Aguardando o pagamento… esta página atualiza sozinha.</p>
                    @endif
                @else
                    {{-- Cartão no Asaas: o cliente paga na página segura deles; esta tela percebe sozinha (consulta automática) --}}
                    <h2 class="h6 fw-semibold mb-3"><i class="bi bi-credit-card"></i> Cartão · aguardando pagamento no Asaas</h2>
                    <p class="small text-muted">
                        Valor {{ $pendente->valorFormatado() }}. A página do Asaas abre em uma nova aba; depois de pagar, esta tela mostra o resultado sozinha.
                    </p>
                    @if ($pendente->link_pagamento)
                        <a href="{{ $pendente->link_pagamento }}" target="_blank" rel="noopener" class="btn btn-mm mb-2"><i class="bi bi-box-arrow-up-right"></i> Pagar com cartão no Asaas</a>
                    @endif
                    <p class="small text-muted mb-0" data-aguardando><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Aguardando o pagamento… esta página atualiza sozinha.</p>
                @endif
            </div>
            <p class="small text-muted">Prefere outra forma? Escolha abaixo; a cobrança acima deixa de valer.</p>
        @endif

        <div class="mm-card p-4">
            <h2 class="h6 fw-semibold mb-3">Forma de pagamento</h2>
            @php $aba = old('forma', session('aba', $pendente?->forma ?? 'pix')); @endphp
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
                    @if ($asaas)
                        {{-- Segurança: no Asaas o número do cartão é digitado na página deles e nunca passa pelo nosso servidor --}}
                        <p class="text-muted">O cartão é digitado na <strong>página segura do Asaas</strong>, que abre em uma nova aba. A loja não vê nem guarda o número do cartão.</p>
                        <p class="small text-muted mb-2">Cartão de teste do sandbox: <code>4444 4444 4444 4444</code>, CVV <code>123</code>, qualquer validade futura.</p>
                        <form method="POST" action="{{ route('loja.pagamento.store', $pedido) }}" onsubmit="this.querySelector('button').disabled = true;">
                            @csrf <input type="hidden" name="forma" value="cartao">
                            <button type="submit" class="btn btn-mm">Pagar {{ $pedido->valor('total') }} com cartão</button>
                        </form>
                    @else
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
                    @endif
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
@if ($asaas && $pendente)
<script>
    /**
     * Consulta automática: pergunta ao servidor (que pergunta ao Asaas) se a cobrança já foi paga.
     * A cada 5 segundos, por no máximo 15 minutos. Quando muda, vai para a tela de resultado.
     */
    (function () {
        const url = @json(route('loja.pagamento.status', [$pedido, $pendente]));
        const limite = Date.now() + 15 * 60 * 1000;

        function consultar() {
            if (Date.now() > limite) {
                document.querySelectorAll('[data-aguardando]').forEach(function (el) {
                    el.textContent = 'Paramos de consultar automaticamente. Se você já pagou, atualize a página (F5).';
                });
                return;
            }
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : { status: 'pendente' }; })
                .then(function (dados) {
                    if (dados.ir_para) { window.location.href = dados.ir_para; return; }
                    setTimeout(consultar, 5000);
                })
                .catch(function () { setTimeout(consultar, 5000); }); // sem internet por um instante: tenta de novo
        }
        setTimeout(consultar, 5000);
    })();
</script>
@endif
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
