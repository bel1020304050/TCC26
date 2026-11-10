@extends('layouts.loja')

@section('titulo', 'Criar nova senha')

@section('conteudo')
{{-- Tela aberta pelo link do e-mail "Redefinir sua senha" --}}
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="mm-card p-4 mt-3">
            <h1 class="h4 mb-1">Criar nova senha</h1>
            <p class="text-muted small mb-4">Escolha uma senha nova com pelo menos 8 caracteres.</p>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                {{-- Segurança: o código (token) do link vai escondido; o servidor confere se é válido e se não venceu --}}
                <input type="hidden" name="token" value="{{ $token }}">
                <x-campo name="email" label="E-mail" type="email" obrigatorio :valor="$email" autocomplete="email" />
                <x-campo name="password" label="Nova senha" type="password" obrigatorio autocomplete="new-password" autofocus />
                <x-campo name="password_confirmation" label="Confirmar nova senha" type="password" obrigatorio autocomplete="new-password" />

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-mm px-4">Salvar nova senha</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
