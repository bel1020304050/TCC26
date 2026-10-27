@extends('layouts.painel')

@section('titulo', 'Minha conta')
@section('trilha', 'Painel › Minha conta')

@section('conteudo')
{{-- Minha conta da equipe (seção 2.2): só nome, telefone e senha --}}
<div class="row g-4" style="max-width: 980px;">
    <div class="col-lg-6">
        <div class="mm-card p-4 h-100">
            <h2 class="h6 fw-semibold mb-3">Meus dados</h2>
            <form method="POST" action="{{ route('painel.minha-conta.update') }}" novalidate>
                @csrf
                @method('PUT')
                <x-campo name="name" label="Nome" obrigatorio maxlength="120" :valor="$usuario->name" />
                <x-campo name="telefone" label="Telefone" opcional maxlength="20" :valor="$usuario->telefone" />
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="text" class="form-control" value="{{ $usuario->email }}" disabled>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Perfil</label>
                        <input type="text" class="form-control" value="{{ $usuario->perfilLabel() }}{{ $usuario->cargo ? ' · '.$usuario->cargo : '' }}" disabled>
                    </div>
                </div>
                <p class="text-muted small">E-mail, perfil e cargo são alterados pelo Administrador em Usuários.</p>
                <div class="text-end">
                    <button type="submit" class="btn btn-mm px-4">Salvar dados</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="mm-card p-4 h-100">
            <h2 class="h6 fw-semibold mb-3">Alterar senha</h2>
            <form method="POST" action="{{ route('painel.minha-conta.senha') }}" novalidate>
                @csrf
                @method('PUT')
                @foreach ([['senha_atual', 'Senha atual', 'current-password', null], ['password', 'Nova senha', 'new-password', 'Mínimo de 8 caracteres'], ['password_confirmation', 'Confirmar nova senha', 'new-password', null]] as [$campo, $rotulo, $auto, $dica])
                    <div class="mb-3">
                        <label for="{{ $campo }}" class="form-label">{{ $rotulo }} <span class="obrigatorio">*</span></label>
                        <input type="password" id="{{ $campo }}" name="{{ $campo }}" autocomplete="{{ $auto }}" required
                               class="form-control @error($campo, 'senha') is-invalid @enderror">
                        @error($campo, 'senha')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            @if ($dica) <div class="form-text">{{ $dica }}</div> @endif
                        @enderror
                    </div>
                @endforeach
                <div class="text-end">
                    <button type="submit" class="btn btn-mm px-4">Alterar senha</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
