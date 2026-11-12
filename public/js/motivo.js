/**
 * Campo "Motivo" das janelas de inativar/reativar:
 * - o botão de confirmar só libera com pelo menos 10 caracteres (sem contar espaços nas pontas);
 * - mostra o contador "7/10";
 * - se o servidor recusou o motivo, reabre a mesma janela com a mensagem de erro.
 * É só uma ajuda na tela: o servidor confere a mesma regra ao salvar.
 */
(function () {
    const MINIMO = 10;

    document.querySelectorAll('[data-motivo]').forEach(function (campo) {
        const form = campo.closest('form');
        const botao = form.querySelector('[data-motivo-botao]');
        const contador = form.querySelector('[data-motivo-contador]');

        function atualizar() {
            const tamanho = campo.value.trim().length;
            if (botao) botao.disabled = tamanho < MINIMO;
            if (contador) {
                contador.textContent = tamanho >= MINIMO ? '✓' : tamanho + '/' + MINIMO;
                contador.classList.toggle('text-success', tamanho >= MINIMO);
            }
        }

        campo.addEventListener('input', atualizar);
        atualizar();
    });

    // Reabre a janela que o servidor devolveu com erro
    const reabrir = document.querySelector('.modal[data-reabrir]');
    if (reabrir && window.bootstrap) bootstrap.Modal.getOrCreateInstance(reabrir).show();

    // Ao abrir uma janela, o cursor já vai para o campo do motivo
    document.addEventListener('shown.bs.modal', function (e) {
        const campo = e.target.querySelector('[data-motivo]');
        if (campo) campo.focus();
    });
})();
