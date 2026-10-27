@extends('layouts.painel')

@section('titulo', 'Usuários')
@section('trilha', 'Painel › Usuários')

@section('conteudo')
{{-- Story 3.6 – Consultar Usuários (Figura 7) e 3.8 – Inativar Usuário (Figura 9) --}}
<form method="GET" action="{{ route('painel.usuarios.index') }}" class="d-flex flex-wrap gap-2 mb-3" role="search">
    <input type="search" name="busca" value="{{ $busca }}" class="form-control" style="flex: 1 1 240px;"
           placeholder="Pesquisar por nome ou e-mail" aria-label="Pesquisar por nome ou e-mail">
    <select name="perfil" class="form-select" style="width: 180px;" aria-label="Filtrar por perfil">
        <option value="">Perfil: Todos</option>
        <option value="admin" @selected($perfil === 'admin')>Administrador</option>
        <option value="funcionario" @selected($perfil === 'funcionario')>Funcionário</option>
    </select>
    <select name="status" class="form-select" style="width: 160px;" aria-label="Filtrar por status">
        <option value="">Status: Todos</option>
        <option value="ativo" @selected($status === 'ativo')>Ativos</option>
        <option value="inativo" @selected($status === 'inativo')>Inativos</option>
    </select>
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
    @if ($busca !== '' || $perfil || $status)
        <a href="{{ route('painel.usuarios.index') }}" class="btn btn-link text-decoration-none">Limpar</a>
    @endif
    <a href="{{ route('painel.usuarios.create') }}" class="btn btn-mm ms-auto"><i class="bi bi-plus-lg"></i> Novo usuário</a>
</form>

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Cargo</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    @php $souEu = $usuario->is(auth()->user()); @endphp
                    <tr>
                        <td class="fw-semibold">
                            {{ $usuario->name }}
                            @if ($souEu) <span class="badge badge-perfil ms-1">você</span> @endif
                        </td>
                        <td>{{ $usuario->email }}</td>
                        <td>{{ $usuario->cargo ?: '—' }}</td>
                        <td>{{ $usuario->perfilLabel() }}</td>
                        <td><x-status-badge :status="$usuario->status" /></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('painel.usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                            @if ($souEu)
                                {{-- Regra da 3.8: o Administrador não pode inativar a própria conta --}}
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Você não pode inativar a sua própria conta">Inativar</button>
                            @else
                                <x-modal-status :id="'usuario-'.$usuario->id" :ativo="$usuario->isAtivo()" tipo="usuário"
                                    :acao-inativar="route('painel.usuarios.inativar', $usuario)"
                                    :acao-reativar="route('painel.usuarios.reativar', $usuario)"
                                    :titulo="$usuario->name.' · '.$usuario->perfilLabel()">
                                    @if ($usuario->isAtivo())
                                        A pessoa perde o acesso ao sistema imediatamente. Os registros feitos por ela
                                        (pedidos atualizados, produtos editados) são mantidos, e a conta pode ser reativada depois.
                                    @else
                                        A pessoa volta a conseguir entrar no sistema com a mesma senha.
                                    @endif
                                </x-modal-status>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Nenhum usuário encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
    <span class="text-muted small">{{ $usuarios->total() }} registro(s) · clientes não aparecem aqui · senhas nunca são exibidas</span>
    {{ $usuarios->links() }}
</div>
@endsection
