@extends('layouts.painel')

@section('titulo', 'Início')
@section('trilha', 'Painel')

@section('conteudo')
<div class="mm-card p-4 mb-4">
    <h2 class="h5 mb-1">Olá, {{ $usuario->name }}!</h2>
    <p class="text-muted mb-0">
        Você está conectada(o) como <span class="badge badge-perfil">{{ $usuario->perfilLabel() }}</span>
        @if ($usuario->cargo) · {{ $usuario->cargo }} @endif
    </p>
</div>

{{-- Acompanhamento das fases de desenvolvimento (ordem combinada no grupo) --}}
@php
    $fases = [
        ['Fase 0 e 1', 'Base do projeto, login e perfis', true],
        ['Fase 2', 'Categorias e Fornecedores', true],
        ['Fase 3', 'Produtos e vitrine', true],
        ['Fase 4', 'Usuários internos e permissões', true],
        ['Fase 5', 'Carrinho e Pedidos', false],
        ['Fase 6', 'Processo de Pagamento', false],
        ['Fase 7', 'Andamento do pedido', false],
        ['Fase 8', 'Promoções', false],
        ['Fase 9', 'Conta do cliente', false],
        ['Fase 10', 'Relatórios', false],
    ];
@endphp
<div class="mm-card p-4">
    <h2 class="h6 fw-semibold mb-3">Andamento do sistema</h2>
    <ul class="list-group list-group-flush">
        @foreach ($fases as [$fase, $descricao, $pronta])
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><strong>{{ $fase }}</strong> — {{ $descricao }}</span>
                @if ($pronta)
                    <span class="badge text-bg-success">Pronta</span>
                @else
                    <span class="badge text-bg-light border">A fazer</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endsection
