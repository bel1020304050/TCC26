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
 * Cadastro da equipe (usuários do painel): listar, cadastrar, editar, inativar, reativar e redefinir senha.
 * Segurança: o Administrador faz tudo; o Gerente só vê a lista e redefine a senha dos Atendentes (UserPolicy).
 *
 * Regra: ninguém digita a senha de outra pessoa. No cadastro e no "Redefinir senha" o sistema gera uma
 * senha provisória, mostrada uma única vez, e a pessoa cria a própria senha no primeiro login.
 */
class UsuarioController extends Controller
{

    // Lista só a equipe; clientes não aparecem aqui (eles ficam no Relatório de Clientes)
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
            ->when(in_array($perfil, User::PERFIS_EQUIPE, true), fn ($q) => $q->where('perfil', $perfil))
            ->when(in_array($status, Status::OPCOES, true), fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('painel.usuarios.index', compact('usuarios', 'busca', 'perfil', 'status'));
    }

    // Formulário de cadastro
    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('painel.usuarios.form', ['usuario' => new User(['perfil' => User::PERFIL_ATENDENTE])]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $senha = self::gerarSenhaProvisoria();

        $usuario = new User($request->safe()->only(['name', 'email', 'telefone']));
        $usuario->password = $senha;
        $usuario->senha_provisoria = true;
        $usuario->perfil = $request->validated('perfil');   // definido no servidor, nunca em massa
        $usuario->status = $request->validated('status');
        $usuario->email_verified_at = now();                // e-mail da loja: não precisa confirmar
        $usuario->save();

        Auditoria::registrar('criou', $usuario, ['perfil' => $usuario->perfil]);

        return redirect()->route('painel.usuarios.index')
            ->with('sucesso', "Usuário \"{$usuario->name}\" cadastrado como {$usuario->perfilLabel()}.")
            ->with('senha_gerada', ['nome' => $usuario->name, 'email' => $usuario->email, 'senha' => $senha]);
    }

    // Formulário de edição
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

        $campos = ['name', 'email', 'telefone'];
        $antes = $usuario->only([...$campos, 'perfil']);

        $usuario->fill($request->safe()->only($campos));
        $usuario->perfil = $novoPerfil;
        $usuario->save();

        // Auditoria: registra o perfil anterior e o novo
        Auditoria::registrar('editou', $usuario, [
            'antes' => $antes,
            'depois' => $usuario->only([...$campos, 'perfil']),
        ]);

        return redirect()->route('painel.usuarios.index')
            ->with('sucesso', "Usuário \"{$usuario->name}\" atualizado.");
    }

    // Inativar: bloqueia o login e derruba as sessões que a pessoa tiver abertas
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

    /**
     * Redefinir senha de quem esqueceu: gera uma senha provisória e mostra uma vez só na tela.
     * Segurança: as sessões abertas da pessoa são encerradas, e a senha nunca vai para a auditoria.
     */
    public function redefinirSenha(User $usuario): RedirectResponse
    {
        Gate::authorize('redefinirSenha', $usuario);

        $senha = self::gerarSenhaProvisoria();
        $usuario->password = $senha;
        $usuario->senha_provisoria = true;
        $usuario->save();

        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        Auditoria::registrar('redefiniu_senha', $usuario);

        return back()
            ->with('sucesso', "Senha de \"{$usuario->name}\" redefinida. Passe a senha provisória para a pessoa.")
            ->with('senha_gerada', ['nome' => $usuario->name, 'email' => $usuario->email, 'senha' => $senha]);
    }

    // 10 letras e números sorteados, sem os que se confundem (0/O, 1/l/I), para ditar ou digitar sem erro
    private static function gerarSenhaProvisoria(): string
    {
        $caracteres = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $senha = '';
        for ($i = 0; $i < 10; $i++) {
            $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)]; // random_int: sorteio seguro
        }

        return $senha;
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
