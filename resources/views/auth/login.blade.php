@extends('layouts.loja')

@section('titulo', 'Entrar')

@section('conteudo')
{{-- Story 3.13 – Login (Figura 14) --}}
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="mm-card p-4 mt-3">
            <h1 class="h4 mb-3">Entrar</h1>

            {{-- Em caso de erro, a mensagem é genérica e não revela se o e-mail existe --}}
            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf
                <x-campo name="email" label="E-mail" type="email" obrigatorio autofocus autocomplete="email" />
                <x-campo name="password" label="Senha" type="password" obrigatorio autocomplete="current-password" />

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="{{ route('register') }}" class="btn btn-outline-secondary">Criar conta</a>
                    <button type="submit" class="btn btn-mm px-4">Entrar</button>
                </div>
            </form>

            <p class="text-muted small mt-4 mb-0">
                Login único para clientes, funcionários e administradores: após entrar, cada perfil é levado à sua área.
            </p>
        </div>
    </div>
</div>
@endsection
