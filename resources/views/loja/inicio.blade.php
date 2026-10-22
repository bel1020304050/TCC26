@extends('layouts.loja')

@section('titulo', 'Início')

@section('conteudo')
<div class="mm-card p-5 text-center">
    <h1 class="h3 mb-2">Bem-vinda(o) à Mary Modas</h1>
    <p class="text-muted mb-4">Moda feminina em Umuarama, agora também online.</p>

    @guest
        <a href="{{ route('register') }}" class="btn btn-mm px-4 me-2">Criar conta</a>
        <a href="{{ route('login') }}" class="btn btn-outline-secondary px-4">Entrar</a>
    @else
        <p class="mb-0">Olá, <strong>{{ auth()->user()->name }}</strong>! Você está conectada(o) como <span class="badge badge-perfil">{{ auth()->user()->perfilLabel() }}</span>.</p>
    @endguest
</div>

{{-- A vitrine com produtos, pesquisa e filtros entra na Fase 3 (story 3.2) --}}
<div class="alert alert-light border mt-4 small mb-0">
    <i class="bi bi-cone-striped me-1"></i> Vitrine em construção — os produtos aparecem aqui na <strong>Fase 3</strong>.
</div>
@endsection
