@extends('layouts.loja')

@section('titulo', 'Crie sua senha')

@section('conteudo')
{{-- Troca obrigatória da senha provisória (equipe). Só esta tela e o "Sair" ficam liberados. --}}
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="mm-card p-4 mt-3">
            <h1 class="h4 mb-1">Crie a sua senha</h1>
            <p class="text-muted small mb-4">
                Olá, {{ auth()->user()->name }}! Você entrou com uma <strong>senha provisória</strong>.
                Para continuar, crie uma senha só sua, com pelo menos 8 caracteres.
            </p>

            <form method="POST" action="{{ route('senha-provisoria.update') }}" novalidate>
                @csrf @method('PUT')
                <x-campo name="password" label="Nova senha" type="password" obrigatorio autocomplete="new-password" autofocus />
                <x-campo name="password_confirmation" label="Confirmar nova senha" type="password" obrigatorio autocomplete="new-password" />

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-mm px-4">Salvar e continuar</button>
                </div>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-link p-0 small">Sair sem trocar agora</button>
            </form>
        </div>
    </div>
</div>
@endsection
