<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gera um PDF a partir de uma view Blade, no servidor, com a biblioteca dompdf, e devolve para download.
 * É como o CSV: o arquivo já sai pronto, sem passar pelo "Imprimir" do navegador.
 * A view é HTML comum; o dompdf "desenha" esse HTML numa folha A4.
 */
final class Pdf
{
    public static function baixar(string $view, array $dados, string $nomeArquivo, string $orientacao = 'landscape'): Response
    {
        $opcoes = new Options;
        $opcoes->set('defaultFont', 'DejaVu Sans'); // fonte que vem com o dompdf e tem todos os acentos
        $opcoes->setIsRemoteEnabled(false);          // Segurança: o PDF nunca busca nada na internet
        $opcoes->setChroot(base_path());             // Segurança: só lê arquivos de dentro do projeto

        $dompdf = new Dompdf($opcoes);
        $dompdf->loadHtml(view($view, $dados)->render(), 'UTF-8');
        $dompdf->setPaper('A4', $orientacao);
        $dompdf->render();

        // Numeração em todas as folhas (o dompdf troca {PAGE_NUM} e {PAGE_COUNT} depois de montar o documento)
        $canvas = $dompdf->getCanvas();
        $fonte = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 95, $canvas->get_height() - 30, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 6.5, [0.5, 0.5, 0.5]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomeArquivo.'"',
        ]);
    }
}
