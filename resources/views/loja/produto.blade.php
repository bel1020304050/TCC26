@extends('layouts.loja')

@section('titulo', $produto->nome.' ('.$produto->variacao().')')

@section('conteudo')
{{-- Página do produto: foto, preço (com promoção automática), tamanhos/cores, estoque baixo e dica de cupom --}}
@php
    $comPreco = $promocao && $promocao->tipo === \App\Models\Promocao::PERCENTUAL;
    $esgotado = $produto->estoque <= 0;
    $poucas = ! $esgotado && $produto->estoque <= \App\Http\Controllers\Loja\ProdutoController::ESTOQUE_BAIXO;
@endphp

<nav aria-label="Trilha" class="small mb-3">
    <a href="{{ route('loja.inicio') }}" class="text-decoration-none">Início</a> ›
    <a href="{{ route('loja.inicio', ['categoria' => $produto->categoria_id]) }}" class="text-decoration-none">{{ $produto->categoria->nome }}</a> ›
    <span class="text-muted">{{ $produto->nome }}</span>
</nav>

<div class="row g-4">
    <div class="col-md-6">
        <div class="position-relative">
            <img src="{{ $produto->imagemUrl() }}" alt="{{ $produto->nome }} — {{ $produto->variacao() }}" class="mm-produto-foto">
            @if ($promocao)
                <span class="badge rounded-pill mm-selo position-absolute top-0 start-0 m-3 fs-6" title="{{ $promocao->nome }}">{{ $promocao->seloLabel() }}</span>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <div class="text-muted small text-uppercase">{{ $produto->categoria->nome }}</div>
        <h1 class="h3 mb-3">{{ $produto->nome }}</h1>

        {{-- Preço "de … por …" quando há promoção automática percentual --}}
        <div class="mb-3">
            @if ($comPreco)
                <div class="text-muted">de <span class="text-decoration-line-through">{{ $produto->precoFormatado() }}</span></div>
                <div class="fs-2 mm-preco">por R$ {{ number_format($promocao->precoCom($produto), 2, ',', '.') }}</div>
                <div class="small text-muted">{{ $promocao->nome }}</div>
            @else
                <div class="fs-2 mm-preco">{{ $produto->precoFormatado() }}</div>
                @if ($promocao) <div class="small text-muted">{{ $promocao->nome }}: {{ $promocao->descontoLabel() }} de desconto no carrinho</div> @endif
            @endif
        </div>

        {{-- Cada botão leva à página da outra variação (o preço aparece na própria página dela) --}}
        @foreach (['Tamanho' => [$tamanhos, 'tamanho'], 'Cor' => [$cores, 'cor']] as $rotulo => [$opcoes, $campo])
            @if ($opcoes->count() > 0)
                <div class="mb-3">
                    <div class="small fw-semibold mb-1">{{ $rotulo }}: {{ $produto->{$campo} }}</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($opcoes as $variacao)
                            @php $atual = $variacao->id === $produto->id; @endphp
                            <a href="{{ route('loja.produto', $variacao->id) }}"
                               class="btn btn-sm mm-opcao {{ $atual ? 'btn-dark' : 'btn-outline-secondary' }} {{ $variacao->estoque <= 0 ? 'esgotada' : '' }}"
                               @if ($atual) aria-current="true" @endif
                               title="{{ $variacao->estoque <= 0 ? 'Esgotado' : $variacao->tamanho.' / '.$variacao->cor }}">{{ $variacao->{$campo} }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        @if ($esgotado)
            <div class="alert alert-secondary"><i class="bi bi-x-circle me-1"></i> Produto indisponível no momento. Veja outro tamanho ou cor.</div>
        @elseif ($poucas)
            <div class="fw-semibold text-danger mb-2">Últimas {{ $produto->estoque }} unidade(s)!</div>
        @endif

        @unless ($esgotado)
            {{-- Adiciona 1 unidade; a quantidade se ajusta no carrinho --}}
            @auth
                @if (auth()->user()->isCliente())
                    <form method="POST" action="{{ route('loja.carrinho.adicionar') }}" class="mb-3">
                        @csrf
                        <input type="hidden" name="produto_id" value="{{ $produto->id }}">
                        <button type="submit" class="btn btn-mm btn-lg w-100"><i class="bi bi-bag-plus"></i> Adicionar ao carrinho</button>
                    </form>
                @endif
            @else
                <a href="{{ route('login', ['voltar' => '/produtos/'.$produto->id]) }}" class="btn btn-outline-secondary btn-lg w-100 mb-3">Entrar para comprar</a>
            @endauth
        @endunless

        @if ($cupom)
            <div class="alert alert-light border small">
                <i class="bi bi-ticket-perforated me-1"></i>
                Use o cupom <strong class="font-monospace">{{ $cupom->codigo }}</strong> no carrinho e ganhe {{ $cupom->descontoLabel() }} de desconto
                (válido até {{ $cupom->data_fim->format('d/m') }}).
            </div>
        @endif

        @if ($produto->descricao)
            <h2 class="h6 fw-semibold mt-4">Descrição</h2>
            <p class="text-muted" style="white-space: pre-line">{{ $produto->descricao }}</p>
        @endif
    </div>
</div>
@endsection
