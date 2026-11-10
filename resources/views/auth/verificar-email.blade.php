@extends('layouts.loja')

@section('titulo', 'Confirme seu e-mail')

@section('conteudo')
{{-- Aparece quando o cliente tenta finalizar a compra sem ter confirmado o e-mail --}}
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="mm-card p-4 mt-3 text-center">
            <i class="bi bi-envelope-check fs-1 text-secondary"></i>
            <h1 class="h4 mt-2 mb-2">Confirme seu e-mail para finalizar a compra</h1>
            <p class="text-muted mb-1">Enviamos um link de confirmação para</p>
            <p class="fw-semibold">{{ $email }}</p>
            <p class="text-muted small">
                Abra o e-mail e clique em <strong>Confirmar e-mail</strong>. Seu carrinho continua salvo.
                Não chegou? Confira a caixa de spam ou peça um novo link.
            </p>

            <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-mm">Reenviar e-mail de confirmação</button>
            </form>
            <a href="{{ route('loja.carrinho') }}" class="btn btn-outline-secondary ms-1">Voltar ao carrinho</a>

            <p class="text-muted small mt-4 mb-0">
                E-mail errado? Corrija em <a href="{{ route('loja.conta.edit') }}">Minha conta › Editar dados</a> e enviaremos um novo link.
            </p>
        </div>
    </div>
</div>
@endsection
