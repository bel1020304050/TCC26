<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $relatorio->titulo() }} · Mary Modas</title>
    {{-- Modelo do PDF (gerado pelo dompdf no servidor). O dompdf entende CSS simples:
         por isso o cabeçalho e os indicadores são tabelas, e não "flex". --}}
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #222; }
        .topo { width: 100%; border-bottom: 2px solid #B0707C; margin-bottom: 6px; }
        .topo td { vertical-align: bottom; padding-bottom: 4px; }
        .marca { font-size: 15px; font-weight: bold; color: #B0707C; }
        h1 { font-size: 13px; margin: 2px 0 0; }
        .meta { color: #666; font-size: 8px; text-align: right; }
        .filtros { color: #444; margin: 0 0 6px; }
        .indicadores { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin: 0 -4px 8px; }
        .indicadores td { border: 1px solid #ddd; border-radius: 4px; padding: 4px 6px; }
        .indicadores small { color: #666; display: block; font-size: 8px; }
        .indicadores strong { font-size: 12px; }
        table.dados { width: 100%; border-collapse: collapse; }
        /* thead é repetido automaticamente no topo de cada folha */
        table.dados thead { display: table-header-group; }
        table.dados th { background: #F6E9EB; text-align: left; padding: 4px 5px; font-size: 8px; text-transform: uppercase; }
        table.dados td { padding: 3px 5px; border-bottom: 1px solid #eee; }
        table.dados tr { page-break-inside: avoid; }
        tr.destaque td { background: #fff4e5; font-weight: bold; }
        .rodape { color: #666; margin-top: 6px; font-size: 8px; }
        .gerado { position: fixed; bottom: -9mm; left: 0; color: #888; font-size: 8px; }
    </style>
</head>
<body>
    {{-- Rodapé fixo em todas as folhas (a numeração "Página X de Y" é colocada pelo App\Support\Pdf) --}}
    <div class="gerado">Mary Modas · {{ $relatorio->titulo() }} · gerado em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}</div>

    <table class="topo">
        <tr>
            <td>
                <div class="marca">Mary Modas</div>
                <h1>{{ $relatorio->titulo() }}</h1>
            </td>
            <td class="meta">Gerado em {{ now()->format('d/m/Y H:i') }}<br>por {{ auth()->user()->name }} ({{ auth()->user()->perfilLabel() }})</td>
        </tr>
    </table>

    @if ($relatorio->descricaoFiltros())
        <p class="filtros">{{ $relatorio->descricaoFiltros() }}</p>
    @endif

    <table class="indicadores">
        <tr>
            @foreach ($relatorio->indicadores() as $rotulo => $valor)
                <td><small>{{ $rotulo }}</small><strong>{{ $valor }}</strong></td>
            @endforeach
        </tr>
    </table>

    <table class="dados">
        <thead><tr>@foreach ($relatorio->colunas() as $coluna) <th>{{ $coluna }}</th> @endforeach</tr></thead>
        <tbody>
            @forelse ($registros as $registro)
                <tr class="{{ $relatorio->destaque($registro) ? 'destaque' : '' }}">
                    @foreach ($relatorio->linha($registro) as $celula) <td>{{ $celula }}</td> @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($relatorio->colunas()) }}">Nenhum resultado para os filtros escolhidos.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="rodape">{{ $registros->count() }} registro(s) · {{ $relatorio->rodape() }}</p>
</body>
</html>
