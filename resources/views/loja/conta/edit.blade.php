@extends('layouts.loja')

@section('titulo', 'Editar meus dados')

@section('conteudo')
{{-- "Editar meus dados": dados pessoais, endereço e troca de senha --}}
<h1 class="h3 mb-3">Minha conta</h1>

<div class="row g-3">
    <div class="col-lg-3">@include('loja.conta._menu')</div>

    <div class="col-lg-9 d-flex flex-column gap-3">
        <form method="POST" action="{{ route('loja.conta.update') }}" class="mm-card p-4" novalidate>
            @csrf @method('PUT')
            <h2 class="h6 fw-semibold mb-3">Dados pessoais</h2>
            <div class="row">
                <x-campo class="col-md-6" name="name" label="Nome completo" obrigatorio :valor="$cliente->name" autocomplete="name" />
                <x-campo class="col-md-6" name="email" label="E-mail" type="email" obrigatorio :valor="$cliente->email" autocomplete="email" dica="Deve ser único no sistema" />
                <x-campo class="col-md-6" name="cpf" label="CPF" obrigatorio :valor="$cliente->cpfFormatado()" placeholder="000.000.000-00"
                         inputmode="numeric" maxlength="14" data-mascara-documento="cpf" dica="Usado na emissão da cobrança" />
                <x-campo class="col-md-6" name="telefone" label="Telefone" opcional :valor="$cliente->telefone" placeholder="(44) 99999-0000" />
            </div>

            <h2 class="h6 fw-semibold mb-3 mt-2" id="endereco">Endereço de entrega</h2>
            @include('partials.endereco-campos', ['dono' => $cliente])
            <p class="text-muted small">Alterar o endereço não muda pedidos já criados, que guardam uma cópia do endereço de entrega.</p>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('loja.conta') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-mm">Salvar dados</button>
            </div>
        </form>

        <form method="POST" action="{{ route('loja.conta.senha') }}" class="mm-card p-4" id="senha" novalidate>
            @csrf @method('PUT')
            <h2 class="h6 fw-semibold mb-3">Alterar senha</h2>
            <div class="row">
                @foreach ([['senha_atual', 'Senha atual', 'current-password', null], ['password', 'Nova senha', 'new-password', 'Mínimo de 8 caracteres'], ['password_confirmation', 'Confirmar nova senha', 'new-password', null]] as [$campo, $rotulo, $auto, $dica])
                    <div class="col-md-4 mb-3">
                        <label for="{{ $campo }}" class="form-label">{{ $rotulo }} <span class="obrigatorio">*</span></label>
                        <input type="password" id="{{ $campo }}" name="{{ $campo }}" autocomplete="{{ $auto }}"
                               class="form-control {{ $errors->senha->has($campo) ? 'is-invalid' : '' }}">
                        @if ($errors->senha->has($campo))
                            <div class="invalid-feedback">{{ $errors->senha->first($campo) }}</div>
                        @elseif ($dica)
                            <div class="form-text">{{ $dica }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-mm">Alterar senha</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/documento.js') }}"></script>
    <script src="{{ asset('js/viacep.js') }}"></script>
@endpush
