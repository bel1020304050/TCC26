<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD de Usuários internos (equipe) — stories 3.5 (cadastrar), 3.6 (consultar),
 * 3.7 (editar) e 3.8 (inativar). Só o Administrador acessa.
 */
class UsuarioController extends Controller
{
    private const PERFIS = [User::PERFIL_FUNCIONARIO, User::PERFIL_ADMIN];

    // 3.6 – Consultar: só equipe (clientes ficam no Relatório de Clientes)
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $busca = trim((string) $request->query('busca'));
        $perfil = $request->query('perfil');
        $status = $request->query('status');

        $usuarios = User::query()
            ->equipe()
            ->when($busca !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")))
            ->when(in_array($perfil, self::PERFIS, true), fn ($q) => $q->where('perfil', $perfil))
            ->when(in_array($status, Status::OPCOES, true), fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('painel.usuarios.index', compact('usuarios', 'busca', 'perfil', 'status'));
    }

    // 3.5 – Cadastrar
    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('painel.usuarios.form', ['usuario' => new User(['perfil' => User::PERFIL_FUNCIONARIO])]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $usuario = new User($request->safe()->only(['name', 'email', 'telefone', 'cargo', 'password']));
        $usuario->perfil = $request->validated('perfil');   // definido no servidor, nunca em massa
        $usuario->status = $request->validated('status');
        $usuario->save();

        Auditoria::registrar('criou', $usuario, ['perfil' => $usuario->perfil]);

        return redirect()->route('painel.usuarios.index')
            ->with('sucesso', "Usuário \"{$usuario->name}\" cadastrado como {$usuario->perfilLabel()}.");
    }

    // 3.7 – Editar
    public function edit(User $usuario): View
    {
        Gate::authorize('update', $usuario);

        return view('painel.usuarios.form', compact('usuario'));
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $novoPerfil = $request->validated('perfil');

        // Sempre deve existir pelo menos um Administrador ativo
        if ($usuario->isAdmin() && $novoPerfil !== User::PERFIL_ADMIN && ! $this->existeOutroAdminAtivo($usuario)) {
            return back()->withInput()->with('erro', 'O sistema precisa de pelo menos um Administrador ativo.');
        }

        $campos = ['name', 'email', 'telefone', 'cargo'];
        $antes = $usuario->only([...$campos, 'perfil']);

        $usuario->fill($request->safe()->only($campos));
        $usuario->perfil = $novoPerfil;
        $senhaAlterada = filled($request->validated('password'));
        if ($senhaAlterada) {
            $usuario->password = $request->validated('password');
        }
        $usuario->save();

        // RNF.01 da 3.7: mudança de perfil registrada com perfil anterior e novo (a senha nunca vai para a auditoria)
        Auditoria::registrar('editou', $usuario, [
            'antes' => $antes,
            'depois' => $usuario->only([...$campos, 'perfil']),
            'senha_alterada' => $senhaAlterada,
        ]);

        return redirect()->route('painel.usuarios.index')
            ->with('sucesso', "Usuário \"{$usuario->name}\" atualizado.");
    }

    // 3.8 – Inativar: bloqueia o login e encerra as sessões abertas
    public function inativar(User $usuario): RedirectResponse
    {
        Gate::authorize('alterarStatus', $usuario);

        if ($usuario->isAdmin() && ! $this->existeOutroAdminAtivo($usuario)) {
            return back()->with('erro', 'O sistema precisa de pelo menos um Administrador ativo.');
        }

        $usuario->status = User::STATUS_INATIVO;
        $usuario->save();

        // Derruba as sessões salvas no banco: o usuário sai na próxima página que abrir
        DB::table('sessions')->where('user_id', $usuario->id)->delete();

        Auditoria::registrar('inativou', $usuario);

        return back()->with('sucesso', "Usuário \"{$usuario->name}\" inativado. Ele não consegue mais entrar no sistema.");
    }

    public function reativar(User $usuario): RedirectResponse
    {
        Gate::authorize('alterarStatus', $usuario);

        $usuario->status = User::STATUS_ATIVO;
        $usuario->save();
        Auditoria::registrar('reativou', $usuario);

        return back()->with('sucesso', "Usuário \"{$usuario->name}\" reativado.");
    }

    private function existeOutroAdminAtivo(User $usuario): bool
    {
        return User::query()
            ->where('perfil', User::PERFIL_ADMIN)
            ->ativos()
            ->whereKeyNot($usuario->id)
            ->exists();
    }
}
