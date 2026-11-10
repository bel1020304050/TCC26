/**
 * Campos de e-mail: ao sair do campo, tira os espaços e passa para minúsculas
 * ("Ana@Email.COM " vira "ana@email.com"). O servidor faz o mesmo ao salvar e no login;
 * aqui é só para a pessoa ver como o e-mail vai ficar.
 */
document.querySelectorAll('input[type="email"]').forEach(function (campo) {
    campo.addEventListener('blur', function () {
        campo.value = campo.value.trim().toLowerCase();
    });
});
