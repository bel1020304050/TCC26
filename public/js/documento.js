/**
 * Máscara de CPF e CNPJ nos campos marcados com data-mascara-documento="cpf" ou "cnpj".
 * - CPF: só números, 000.000.000-00
 * - CNPJ: as 12 primeiras posições aceitam letras e números (CNPJ alfanumérico, desde 07/2026),
 *   os 2 dígitos finais só números: 00.000.000/0000-00 ou 12.ABC.345/01DE-35
 * A máscara só ajuda na digitação: quem confere se o documento é válido é o servidor.
 *
 * No fornecedor, os botões "Pessoa Física / Pessoa Jurídica" (name="tipo_pessoa") liberam o campo
 * e trocam o tipo da máscara.
 */
(function () {
    function mascaraCpf(valor) {
        const d = valor.replace(/\D/g, '').slice(0, 11);
        return d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    function mascaraCnpj(valor) {
        const limpo = valor.toUpperCase().replace(/[^0-9A-Z]/g, '');
        // Validação: posições 13 e 14 (dígitos verificadores) só aceitam números
        const base = limpo.slice(0, 12);
        const dv = limpo.slice(12).replace(/\D/g, '').slice(0, 2);
        const c = base + dv;
        let r = c.slice(0, 2);
        if (c.length > 2) r += '.' + c.slice(2, 5);
        if (c.length > 5) r += '.' + c.slice(5, 8);
        if (c.length > 8) r += '/' + c.slice(8, 12);
        if (c.length > 12) r += '-' + c.slice(12, 14);
        return r;
    }

    function aplicar(campo) {
        const tipo = campo.dataset.mascaraDocumento;
        if (tipo === 'cpf') campo.value = mascaraCpf(campo.value);
        if (tipo === 'cnpj') campo.value = mascaraCnpj(campo.value);
    }

    document.querySelectorAll('[data-mascara-documento]').forEach(function (campo) {
        campo.addEventListener('input', function () { aplicar(campo); });
    });

    // Fornecedor: escolher o tipo de pessoa libera o campo do documento
    const radios = document.querySelectorAll('input[name="tipo_pessoa"]');
    const documento = document.getElementById('documento');
    if (!radios.length || !documento) return;

    const rotulo = document.querySelector('label[for="documento"] [data-rotulo]');
    const config = {
        fisica: { rotulo: 'CPF', placeholder: '000.000.000-00', tamanho: 14, teclado: 'numeric' },
        juridica: { rotulo: 'CNPJ', placeholder: '00.000.000/0000-00 ou 12.ABC.345/01DE-35', tamanho: 18, teclado: 'text' },
    };

    function atualizar(trocouTipo) {
        const marcado = document.querySelector('input[name="tipo_pessoa"]:checked');
        if (!marcado) {
            documento.disabled = true;
            return;
        }
        const c = config[marcado.value];
        documento.disabled = false;
        documento.dataset.mascaraDocumento = marcado.value === 'fisica' ? 'cpf' : 'cnpj';
        documento.placeholder = c.placeholder;
        documento.maxLength = c.tamanho;
        documento.inputMode = c.teclado;
        if (rotulo) rotulo.textContent = c.rotulo;
        // Trocar de Física para Jurídica (ou o contrário) limpa o número digitado para o outro tipo
        if (trocouTipo) documento.value = '';
        aplicar(documento);
    }

    radios.forEach(function (r) { r.addEventListener('change', function () { atualizar(true); }); });
    atualizar(false);
})();
