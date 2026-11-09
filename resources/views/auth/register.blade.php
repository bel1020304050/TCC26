@extends('layouts.loja')

@section('titulo', 'Criar conta')

@section('conteudo')
{{-- Cadastro público de clientes ("Criar conta") --}}
<div class="mm-card p-4 mx-auto" style="max-width: 980px;">
    <h1 class="h4 mb-1">Criar conta</h1>
    <p class="text-muted small mb-4">Crie sua conta para comprar e acompanhar seus pedidos.</p>

    <form method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <div class="row g-4">
            {{-- Coluna 1: dados de acesso --}}
            <div class="col-lg-5">
                <h2 class="h6 fw-semibold mb-3">Dados de acesso</h2>
                <x-campo name="name" label="Nome completo" obrigatorio autocomplete="name" />
                <x-campo name="email" label="E-mail" type="email" obrigatorio autocomplete="email" />
                <x-campo name="cpf" label="CPF" obrigatorio placeholder="000.000.000-00" inputmode="numeric" maxlength="14"
                         data-mascara-documento="cpf" dica="Usado na emissão da cobrança" />
                <x-campo name="telefone" label="Telefone" opcional placeholder="(44) 99999-0000" autocomplete="tel" />
                <div class="row">
                    <x-campo class="col-sm-6" name="password" label="Senha" type="password" obrigatorio dica="Mínimo de 8 caracteres" autocomplete="new-password" />
                    <x-campo class="col-sm-6" name="password_confirmation" label="Confirmar senha" type="password" obrigatorio autocomplete="new-password" />
                </div>
            </div>

            {{-- Coluna 2: endereço de entrega (ViaCEP preenche ao sair do campo CEP) --}}
            <div class="col-lg-7">
                <h2 class="h6 fw-semibold mb-3">Endereço de entrega</h2>
                @include('partials.endereco-campos', ['dono' => null])
                <p class="text-muted small mb-0">Se a ViaCEP não responder, preencha os campos manualmente.</p>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('login') }}" class="btn btn-outline-secondary">Já tenho conta</a>
            <button type="submit" class="btn btn-mm px-4">Criar conta</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/documento.js') }}"></script>
    <script src="{{ asset('js/viacep.js') }}"></script>
@endpush
