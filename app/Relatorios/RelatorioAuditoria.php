<?php

namespace App\Relatorios;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use App\Support\Auditoria as Registrar;
use App\Support\TextoAuditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Relatório de Auditoria (só o Administrador): quem mudou o quê na loja, quando e por quê.
 * Somente leitura: não existe tela nem rota para alterar ou apagar um registro.
 */
class RelatorioAuditoria extends Relatorio
{
    // Nome de cada registro afetado (ex.: "Vestido Floral (M / Preto)"), buscado de uma vez só por área
    private array $nomes = [];

    public function titulo(): string
    {
        return 'Relatório de auditoria';
    }

    public function permissao(): string
    {
        return 'relatorio-auditoria';
    }

    protected function lerFiltros(Request $request): array
    {
        [$de, $ate] = $this->periodo($request);
        $usuario = (string) $request->query('usuario');
        $acao = (string) $request->query('acao');
        $area = (string) $request->query('area');

        return [
            'de' => $de, 'ate' => $ate,
            // id de alguém da equipe, "clientes" (todos os clientes) ou "sistema"
            'usuario' => ctype_digit($usuario) || in_array($usuario, ['clientes', 'sistema'], true) ? $usuario : '',
            'acao' => array_key_exists($acao, TextoAuditoria::ACOES) ? $acao : '',
            'area' => array_key_exists($area, TextoAuditoria::AREAS) ? $area : '',
        ];
    }

    protected function consulta(): Builder
    {
        $f = $this->filtros;

        return Auditoria::query()
            ->with('usuario:id,name,perfil,status')
            ->whereBetween('created_at', [$f['de'], $f['ate']])
            ->when($f['usuario'] === 'sistema', fn ($q) => $q->where('perfil', Registrar::SISTEMA))
            ->when($f['usuario'] === 'clientes', fn ($q) => $q->where('perfil', User::PERFIL_CLIENTE))
            ->when(ctype_digit($f['usuario']), fn ($q) => $q->where('user_id', (int) $f['usuario']))
            ->when($f['acao'], fn ($q) => $q->where('acao', $f['acao']))
            ->when($f['area'], fn ($q) => $q->where('entidade', $f['area']))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    // Busca os nomes dos registros afetados antes de montar as linhas (evita uma consulta por linha)
    public function registros(): Collection
    {
        return tap(parent::registros(), fn ($lista) => $this->carregarNomes($lista));
    }

    public function paginar(int $porPagina = 50)
    {
        return tap(parent::paginar($porPagina), fn ($pagina) => $this->carregarNomes($pagina->getCollection()));
    }

    public function colunas(): array
    {
        return ['Data/hora', 'Quem', 'Ação', 'Área', 'Registro', 'O que mudou', 'Motivo'];
    }

    public function linha(Model $registro): array
    {
        return [
            $registro->created_at->format('d/m/Y H:i'),
            TextoAuditoria::quem($registro),
            TextoAuditoria::acao($registro->acao),
            TextoAuditoria::area($registro->entidade),
            $this->nomes[$registro->entidade][$registro->entidade_id] ?? '#'.$registro->entidade_id,
            TextoAuditoria::mudancas($registro),
            $registro->motivo ?: '—',
        ];
    }

    public function indicadores(): array
    {
        $base = $this->consulta()->reorder();

        return [
            'Registros no período' => (string) (clone $base)->count(),
            'Ações da equipe' => (string) (clone $base)->whereIn('perfil', User::PERFIS_EQUIPE)->count(),
            'Inativações e reativações' => (string) (clone $base)->whereIn('acao', ['inativou', 'reativou'])->count(),
            'Cancelamentos' => (string) (clone $base)->where('acao', 'cancelou')->count(),
        ];
    }

    public function descricaoFiltros(): string
    {
        $f = $this->filtros;
        $quem = match (true) {
            $f['usuario'] === 'sistema' => 'Sistema',
            $f['usuario'] === 'clientes' => 'Clientes',
            $f['usuario'] !== '' => User::find((int) $f['usuario'])?->name ?? 'Usuário',
            default => 'Todos',
        };

        return 'Período: '.$f['de']->format('d/m/Y').' a '.$f['ate']->format('d/m/Y')
            .' · Quem: '.$quem
            .' · Ação: '.($f['acao'] ? TextoAuditoria::acao($f['acao']) : 'Todas')
            .' · Área: '.($f['area'] ? TextoAuditoria::area($f['area']) : 'Todas');
    }

    public function rodape(): string
    {
        return 'Somente leitura: os registros não podem ser alterados nem apagados. Senhas nunca são registradas; '
            .'de clientes, só o nome (contas encerradas aparecem como "Cliente removido").';
    }

    private function carregarNomes(Collection $registros): void
    {
        $buscar = [
            'produtos' => fn ($ids) => Produto::whereIn('id', $ids)->get()->mapWithKeys(fn ($p) => [$p->id => "{$p->nome} ({$p->variacao()})"]),
            'categorias' => fn ($ids) => Categoria::whereIn('id', $ids)->pluck('nome', 'id'),
            'fornecedores' => fn ($ids) => Fornecedor::whereIn('id', $ids)->pluck('nome', 'id'),
            'promocoes' => fn ($ids) => Promocao::whereIn('id', $ids)->pluck('nome', 'id'),
            'pedidos' => fn ($ids) => Pedido::whereIn('id', $ids)->get()->mapWithKeys(fn ($p) => [$p->id => 'Pedido '.$p->numero()]),
            'users' => fn ($ids) => User::whereIn('id', $ids)->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->perfilLabel()})"]),
        ];

        foreach ($registros->groupBy('entidade') as $entidade => $grupo) {
            if (isset($buscar[$entidade])) {
                $this->nomes[$entidade] = ($this->nomes[$entidade] ?? []) + $buscar[$entidade]($grupo->pluck('entidade_id')->unique())->all();
            }
        }
    }
}
