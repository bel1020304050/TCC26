@extends('layouts.loja')

@section('titulo', 'Esqueci minha senha')

@section('conteudo')
{{-- "Esqueci minha senha" do cliente: pede o link por e-mail --}}
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="mm-card p-4 mt-3">
            <h1 class="h4 mb-1">Esqueci minha senha</h1>
            <p class="text-muted small mb-4">Informe o e-mail da sua conta. Vamos enviar um link para você criar uma nova senha.</p>

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf
                <x-campo name="email" label="E-mail" type="email" obrigatorio autofocus autocomplete="email" />

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary">Voltar para o login</a>
                    <button type="submit" class="btn btn-mm px-4">Enviar link</button>
                </div>
            </form>

            <p class="text-muted small mt-4 mb-0">
                É da equipe da loja? Peça uma senha provisória ao Administrador ou ao Gerente.
            </p>
        </div>
    </div>
</div>
@endsection
