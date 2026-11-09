/**
 * Campo CEP: máscara 00000-000, aviso de CEP incompleto e preenchimento de Rua, Bairro,
 * Cidade e UF pela API pública ViaCEP.
 * Se a API falhar ou demorar mais que o tempo limite, os campos continuam livres
 * para preenchimento manual. O servidor valida o CEP de novo ao salvar.
 */
(function () {
    const campoCep = document.querySelector('[data-viacep]');
    if (!campoCep) return;

    const status = document.getElementById('cep-status');
    const textoPadrao = status ? status.textContent : '';
    const timeout = Number(campoCep.dataset.viacepTimeout || 5000);
    const campos = {
        rua: document.getElementById('rua'),
        bairro: document.getElementById('bairro'),
        cidade: document.getElementById('cidade'),
        uf: document.getElementById('uf'),
    };

    function aviso(texto, tipo) {
        if (!status) return;
        status.hidden = false;
        status.textContent = texto;
        status.className = 'form-text ' + (tipo === 'erro' ? 'text-danger' : 'text-muted');
        // O erro também deixa a borda do campo vermelha, como os erros do servidor
        campoCep.classList.toggle('is-invalid', tipo === 'erro');
    }

    // Máscara 00000-000. Ao digitar de novo, some o aviso de erro anterior.
    campoCep.addEventListener('input', function () {
        let v = campoCep.value.replace(/\D/g, '').slice(0, 8);
        if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
        campoCep.value = v;
        if (campoCep.classList.contains('is-invalid')) aviso(textoPadrao);
    });

    async function buscar() {
        const cep = campoCep.value.replace(/\D/g, '');
        if (cep.length === 0) return;

        // Validação: CEP tem 8 números. Não completamos com zero, porque não dá para saber qual número faltou.
        if (cep.length < 8) {
            aviso('CEP incompleto: digite os 8 números.', 'erro');
            return;
        }

        aviso('Buscando endereço...');
        const controle = new AbortController();
        const timer = setTimeout(() => controle.abort(), timeout);

        try {
            const resposta = await fetch('https://viacep.com.br/ws/' + cep + '/json/', { signal: controle.signal });
            const dados = await resposta.json();
            if (dados.erro) {
                aviso('CEP não encontrado. Confira os números ou preencha o endereço manualmente.', 'erro');
                return;
            }
            campos.rua.value = dados.logradouro || campos.rua.value;
            campos.bairro.value = dados.bairro || campos.bairro.value;
            campos.cidade.value = dados.localidade || campos.cidade.value;
            campos.uf.value = dados.uf || campos.uf.value;
            aviso('Endereço preenchido. Confira e informe o número.');
            document.getElementById('numero').focus();
        } catch (e) {
            aviso('Não foi possível consultar o CEP agora. Preencha o endereço manualmente.');
        } finally {
            clearTimeout(timer);
        }
    }

    campoCep.addEventListener('blur', buscar);
    const botao = document.getElementById('buscar-cep');
    if (botao) botao.addEventListener('click', buscar);
})();
