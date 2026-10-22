@extends('layouts.loja')

@section('titulo', 'Criar conta')

@section('conteudo')
{{-- Story 3.9 – Criar Conta (Figura 10) --}}
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
                <x-campo name="telefone" label="Telefone" opcional placeholder="(44) 99999-0000" autocomplete="tel" />
                <div class="row">
                    <x-campo class="col-sm-6" name="password" label="Senha" type="password" obrigatorio dica="Mínimo de 8 caracteres" autocomplete="new-password" />
                    <x-campo class="col-sm-6" name="password_confirmation" label="Confirmar senha" type="password" obrigatorio autocomplete="new-password" />
                </div>
            </div>

            {{-- Coluna 2: endereço de entrega (ViaCEP preenche ao sair do campo CEP) --}}
            <div class="col-lg-7">
                <h2 class="h6 fw-semibold mb-3">Endereço de entrega</h2>
                <div class="row align-items-start">
                    <div class="col-sm-6 mb-3">
                        <label for="cep" class="form-label">CEP <span class="obrigatorio">*</span></label>
                        <input type="text" id="cep" name="cep" value="{{ old('cep') }}" inputmode="numeric" maxlength="9" placeholder="00000-000"
                               class="form-control @error('cep') is-invalid @enderror" required
                               data-viacep data-viacep-timeout="{{ config('mary.viacep_timeout_ms') }}">
                        @error('cep')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div id="cep-status" class="form-text">Ao sair do campo, Rua, Bairro, Cidade e UF são preenchidos pela ViaCEP.</div>
                        @enderror
                    </div>
                    <div class="col-sm-6 mb-3 pt-sm-4 mt-sm-2">
                        <button type="button" id="buscar-cep" class="btn btn-outline-secondary">Buscar CEP</button>
                    </div>
                </div>
                <div class="row">
                    <x-campo class="col-sm-8" name="rua" label="Rua/Avenida" obrigatorio />
                    <x-campo class="col-sm-4" name="numero" label="Número" obrigatorio />
                    <x-campo class="col-sm-4" name="complemento" label="Complemento" opcional />
                    <x-campo class="col-sm-8" name="bairro" label="Bairro" obrigatorio />
                    <x-campo class="col-sm-8" name="cidade" label="Cidade" obrigatorio />
                    <div class="col-sm-4 mb-3">
                        <label for="uf" class="form-label">UF <span class="obrigatorio">*</span></label>
                        <select id="uf" name="uf" class="form-select @error('uf') is-invalid @enderror" required>
                            <option value=""></option>
                            @foreach ($ufs as $uf)
                                <option value="{{ $uf }}" @selected(old('uf') === $uf)>{{ $uf }}</option>
                            @endforeach
                        </select>
                        @error('uf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
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
    <script src="{{ asset('js/viacep.js') }}"></script>
@endpush
