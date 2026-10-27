@extends('layouts.painel')

@php
    $editando = $usuario->exists;
    $souEu = $editando && $usuario->is(auth()->user());
@endphp

@section('titulo', $editando ? 'Editar usuário' : 'Novo usuário')
@section('trilha', 'Painel › Usuários › '.($editando ? $usuario->name.' › Editar' : 'Novo usuário'))

@section('conteudo')
{{-- Story 3.5 – Cadastrar (Figura 6) e 3.7 – Editar Usuário (Figura 8) --}}
<div class="mm-card p-4" style="max-width: 820px;">
    <form method="POST" novalidate
          action="{{ $editando ? route('painel.usuarios.update', $usuario) : route('painel.usuarios.store') }}">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="row">
            <x-campo class="col-md-6" name="name" label="Nome" obrigatorio maxlength="120" :valor="$usuario->name" />
            <x-campo class="col-md-6" name="email" label="E-mail" type="email" obrigatorio maxlength="150" :valor="$usuario->email"
                     dica="Deve ser único no sistema" />
            <x-campo class="col-md-6" name="telefone" label="Telefone" opcional maxlength="20" :valor="$usuario->telefone" placeholder="(44) 99999-0000" />
            <x-campo class="col-md-6" name="cargo" label="Cargo" opcional maxlength="60" :valor="$usuario->cargo"
                     dica="Apenas informativo (ex.: Subgerente, Vendedora)" />

            <div class="col-md-6 mb-3">
                <label for="perfil" class="form-label">Perfil <span class="obrigatorio">*</span></label>
                @if ($souEu)
                    {{-- Regra da 3.7: não pode alterar o próprio perfil --}}
                    <select id="perfil" class="form-select" disabled>
                        <option>{{ $usuario->perfilLabel() }}</option>
                    </select>
                    <input type="hidden" name="perfil" value="{{ $usuario->perfil }}">
                    <div class="form-text">Não é possível alterar o próprio perfil.</div>
                @else
                    <select id="perfil" name="perfil" class="form-select @error('perfil') is-invalid @enderror" required>
                        <option value="funcionario" @selected(old('perfil', $usuario->perfil) === 'funcionario')>Funcionário</option>
                        <option value="admin" @selected(old('perfil', $usuario->perfil) === 'admin')>Administrador</option>
                    </select>
                    @error('perfil')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="form-text">Funcionário: operação do dia a dia. Administrador: acesso total.</div>
                    @enderror
                @endif
            </div>

            <div class="col-md-6 mb-3">
                @if ($editando)
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" value="{{ \App\Support\Status::label($usuario->status) }}" disabled>
                    <div class="form-text">Alterado só pela ação Inativar/Reativar.</div>
                @else
                    <label for="status" class="form-label">Status inicial <span class="obrigatorio">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="ativo" @selected(old('status', 'ativo') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(old('status') === 'inativo')>Inativo</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @endif
            </div>

            @if ($editando)
                <x-campo class="col-md-6" name="password" label="Nova senha" type="password" opcional autocomplete="new-password"
                         dica="Deixe em branco para manter a senha atual" />
                <x-campo class="col-md-6" name="password_confirmation" label="Confirmar nova senha" type="password" opcional autocomplete="new-password" />
            @else
                <x-campo class="col-md-6" name="password" label="Senha inicial" type="password" obrigatorio autocomplete="new-password"
                         dica="Mínimo de 8 caracteres. A pessoa pode trocá-la em “Minha conta”." />
                <x-campo class="col-md-6" name="password_confirmation" label="Confirmar senha" type="password" obrigatorio autocomplete="new-password" />
            @endif
        </div>

        @unless ($editando)
            <p class="text-muted small mb-0">Clientes não são cadastrados aqui: eles criam a própria conta pela loja.</p>
        @endunless

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('painel.usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-mm px-4">{{ $editando ? 'Salvar alterações' : 'Cadastrar' }}</button>
        </div>
    </form>
</div>
@endsection
