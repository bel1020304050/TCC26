{{-- Etapas da compra (Figuras 19, 31 e 32): Carrinho → Pedido → Pagamento → Concluído --}}
@props(['atual' => 1])
@php $etapas = ['Carrinho', 'Pedido', 'Pagamento', 'Concluído']; @endphp
<ol class="mm-etapas list-unstyled d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Etapas da compra">
    @foreach ($etapas as $i => $nome)
        @php $n = $i + 1; @endphp
        <li class="d-flex align-items-center gap-2 {{ $n <= $atual ? 'feita' : '' }} {{ $n === $atual ? 'atual' : '' }}"
            @if ($n === $atual) aria-current="step" @endif>
            <span class="mm-etapa-num">{{ $n }}</span>
            <span class="mm-etapa-nome">{{ $nome }}</span>
            @if (! $loop->last) <span class="mm-etapa-linha d-none d-sm-inline-block"></span> @endif
        </li>
    @endforeach
</ol>
