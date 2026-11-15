// Página inicial: mapa com todas as ocorrências e filtros por tipo e situação.
document.addEventListener('DOMContentLoaded', function () {
  var mapa = RA.criarMapa('mapa-geral', { rolagem: false });
  var camada = L.layerGroup().addTo(mapa);
  var form = document.getElementById('filtros-mapa');
  var contagem = document.getElementById('mapa-contagem');

  // Os filtros ficam no endereço da página, para compartilhar o link ou voltar ao mesmo recorte.
  var inicial = new URLSearchParams(window.location.search);
  ['categoria', 'situacao'].forEach(function (nome) {
    var sel = form.elements[nome], valor = inicial.get(nome);
    var existe = valor && Array.prototype.some.call(sel.options, function (o) { return o.value === valor; });
    if (existe) sel.value = valor;
  });

  function atualizarEndereco() {
    var params = new URLSearchParams();
    new FormData(form).forEach(function (valor, nome) { if (valor) params.set(nome, valor); });
    var consulta = params.toString();
    history.replaceState(null, '', window.location.pathname + (consulta ? '?' + consulta : ''));
  }

  function carregar() {
    var params = new URLSearchParams(new FormData(form));
    contagem.textContent = 'Carregando ocorrências…';
    fetch(RA.base + '/api/ocorrencias.php?' + params.toString())
      .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
      .then(function (dados) {
        camada.clearLayers();
        dados.ocorrencias.forEach(function (o) {
          var sit = RA.situacoes[o.situacao] || { nome: o.situacao };
          var link = RA.base + '/public/solicitacao.php?protocolo=' + encodeURIComponent(o.protocolo);
          L.marker([o.lat, o.lng], { icon: RA.pino(o.situacao), title: o.categoria })
            .bindPopup(
              '<strong>' + RA.esc(o.categoria) + '</strong>' +
              RA.esc(o.endereco) + '<br>' +
              '<span class="ra-ponto sit-' + RA.esc(o.situacao) + '"></span>' + RA.esc(sit.nome) +
              ' · ' + RA.esc(o.data) + '<br>' +
              '<a href="' + RA.esc(link) + '">Ver ' + RA.esc(o.protocolo) + '</a>'
            )
            .addTo(camada);
        });
        var n = dados.ocorrencias.length;
        contagem.textContent = n === 0 ? 'Nenhuma ocorrência encontrada com esses filtros.'
          : n === 1 ? '1 ocorrência no mapa.' : n + ' ocorrências no mapa.';
      })
      .catch(function () {
        contagem.textContent = 'Não foi possível carregar as ocorrências. Atualize a página para tentar de novo.';
      });
  }

  form.addEventListener('change', function () { atualizarEndereco(); carregar(); });
  form.addEventListener('submit', function (ev) { ev.preventDefault(); });
  carregar();
});
