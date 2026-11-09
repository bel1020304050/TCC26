<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $relatorio->titulo() }} · Mary Modas</title>
    {{-- Versão para impressão: no diálogo do navegador, escolha "Salvar como PDF".
         Mostra exatamente as mesmas colunas da tela. --}}
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; margin: 0; }
        .topo { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #B0707C; padding-bottom: 6px; margin-bottom: 8px; }
        .marca { font-size: 18px; font-weight: bold; color: #B0707C; }
        h1 { font-size: 16px; margin: 2px 0 0; }
        .meta { color: #666; font-size: 10px; text-align: right; }
        .filtros { color: #444; margin-bottom: 8px; }
        .indicadores { display: flex; gap: 8px; margin-bottom: 10px; }
        .indicadores div { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 6px 8px; }
        .indicadores small { color: #666; display: block; }
        .indicadores strong { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F6E9EB; text-align: left; padding: 5px 6px; font-size: 10px; text-transform: uppercase; }
        td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        tr.destaque td { background: #fff4e5; font-weight: bold; }
        .rodape { color: #666; margin-top: 8px; font-size: 10px; }
        .aviso { background: #fff8e1; border: 1px solid #f0d58c; padding: 6px 10px; margin-bottom: 10px; border-radius: 6px; }
        @media screen { body { padding: 16px 24px; max-width: 1200px; margin: 0 auto; } }
        @media print { .aviso { display: none; } }
    </style>
</head>
<body>
    <div class="aviso">No diálogo de impressão, escolha <strong>Salvar como PDF</strong>. <a href="#" onclick="window.print(); return false;">Abrir de novo</a></div>
    <div class="topo">
        <div>
            <div class="marca">Mary Modas</div>
            <h1>{{ $relatorio->titulo() }}</h1>
        </div>
        <div class="meta">Gerado em {{ now()->format('d/m/Y H:i') }}<br>por {{ auth()->user()->name }}</div>
    </div>
    <div class="filtros">{{ $relatorio->descricaoFiltros() }}</div>

    <div class="indicadores">
        @foreach ($relatorio->indicadores() as $rotulo => $valor)
            <div><small>{{ $rotulo }}</small><strong>{{ $valor }}</strong></div>
        @endforeach
    </div>

    <table>
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
    <div class="rodape">{{ $registros->count() }} registro(s) · {{ $relatorio->rodape() }}</div>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
