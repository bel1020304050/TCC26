<?php

namespace App\Relatorios;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Base dos 3 relatórios (3.32 a 3.34). Cada relatório diz:
 * quais filtros aceita, qual consulta faz, quais colunas mostra e quais indicadores calcula.
 * A tela, o CSV e o PDF usam exatamente as mesmas colunas (RNF.01 da 3.34).
 */
abstract class Relatorio
{
    protected array $filtros;

    public function __construct(Request $request)
    {
        $this->filtros = $this->lerFiltros($request);
    }

    abstract public function titulo(): string;

    abstract public function permissao(): string;

    /** @return array<int, string> */
    abstract public function colunas(): array;

    abstract protected function lerFiltros(Request $request): array;

    abstract protected function consulta(): Builder;

    /** @return array<int, string> células da linha, já formatadas */
    abstract public function linha(Model $registro): array;

    /** @return array<string, string> rótulo => valor */
    abstract public function indicadores(): array;

    abstract public function rodape(): string;

    public function filtros(): array
    {
        return $this->filtros;
    }

    // Texto dos filtros usados, para o cabeçalho do PDF
    public function descricaoFiltros(): string
    {
        return '';
    }

    // Índice da coluna que vira selo colorido na tela (null = nenhuma)
    public function colunaStatus(): ?int
    {
        return null;
    }

    // Linha em destaque (ex.: estoque baixo)
    public function destaque(Model $registro): bool
    {
        return false;
    }

    public function registros(): \Illuminate\Support\Collection
    {
        return $this->consulta()->get();
    }

    public function paginar(int $porPagina = 50)
    {
        return $this->consulta()->paginate($porPagina)->withQueryString();
    }

    public function nomeArquivo(string $extensao): string
    {
        return 'relatorio-'.str($this->titulo())->after('Relatório de ')->slug().'-'.now()->format('Y-m-d').'.'.$extensao;
    }

    // ---------- Ajudantes ----------

    // Datas do filtro (AAAA-MM-DD); padrão: últimos 30 dias
    protected function periodo(Request $request, string $de = 'de', string $ate = 'ate'): array
    {
        $ler = function (?string $valor) {
            if (! $valor || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('Y-m-d', $valor);
            } catch (\Throwable) {
                return null;
            }
        };

        $inicio = $ler($request->query($de)) ?? today()->subDays(29);
        $fim = $ler($request->query($ate)) ?? today();
        if ($fim->lt($inicio)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return [$inicio->copy()->startOfDay(), $fim->copy()->endOfDay()];
    }

    protected static function dinheiro(float|string|null $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }
}
