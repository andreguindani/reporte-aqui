// Máscaras simples de CPF e telefone (apenas visual; a validação é feita no servidor).
(function () {
  function cpf(v) {
    v = v.replace(/\D/g, '').slice(0, 11);
    return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  function telefone(v) {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    if (v.length > 2) return v.replace(/(\d{2})(\d*)/, '($1) $2');
    return v;
  }
  var mascaras = { cpf: cpf, telefone: telefone };
  document.querySelectorAll('[data-mascara]').forEach(function (campo) {
    var f = mascaras[campo.dataset.mascara];
    campo.addEventListener('input', function () { campo.value = f(campo.value); });
    if (campo.value) campo.value = f(campo.value);
  });
})();
