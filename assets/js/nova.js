// Assistente de nova solicitação (4 etapas).
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('form-nova');
  var etapas = Array.prototype.slice.call(form.querySelectorAll('.etapa'));
  var indicadores = document.querySelectorAll('#etapas li');
  var btnVoltar = document.getElementById('btn-voltar');
  var btnAvancar = document.getElementById('btn-avancar');
  var btnEnviar = document.getElementById('btn-enviar');
  var btnCancelar = document.getElementById('btn-cancelar');
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var atual = 1;

  var campo = function (id) { return document.getElementById(id); };
  var categoriaMarcada = function () {
    var r = form.querySelector('input[name="categoria_id"]:checked');
    if (!r) return null;
    return RA.categorias.filter(function (c) { return c.id === parseInt(r.value, 10); })[0] || null;
  };

  // ------------------------------------------------------------ Erros
  function erro(nome, texto) {
    var alvo = form.querySelector('[data-erro="' + nome + '"]');
    if (alvo) alvo.textContent = texto || '';
    var input = campo(nome);
    if (input && input.classList) {
      input.classList.toggle('is-invalid', !!texto);
      if (texto && alvo && alvo.id) {
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', alvo.id);
      } else {
        input.removeAttribute('aria-invalid');
        input.removeAttribute('aria-describedby');
      }
    }
    return !texto;
  }

  // Leva o foco ao primeiro problema da etapa, na ordem em que aparece na tela.
  function focarErro(n) {
    var msgs = etapas[n - 1].querySelectorAll('[data-erro]');
    for (var i = 0; i < msgs.length; i++) {
      if (!msgs[i].textContent) continue;
      var nome = msgs[i].dataset.erro;
      var alvo = nome === 'categoria_id' ? form.querySelector('input[name="categoria_id"]')
        : nome === 'local' ? campo('btn-gps') : campo(nome);
      if (alvo) alvo.focus();
      return;
    }
  }

  // Alterações não enviadas: avisa antes de sair da página.
  var sujo = false, enviando = false;
  form.addEventListener('input', function () { sujo = true; });
  form.addEventListener('change', function () { sujo = true; });
  window.addEventListener('beforeunload', function (ev) {
    if (sujo && !enviando) { ev.preventDefault(); ev.returnValue = ''; }
  });

  // ------------------------------------------------------------ Etapa 1
  var selSub = campo('subcategoria_id');
  function montarSubcategorias(selecionada) {
    var cat = categoriaMarcada();
    campo('bloco-sub').hidden = !cat;
    selSub.innerHTML = '';
    if (!cat) return;
    selSub.appendChild(new Option('Selecione…', ''));
    cat.subcategorias.forEach(function (s) {
      selSub.appendChild(new Option(s.nome, s.id, false, s.id === selecionada));
    });
  }
  form.querySelectorAll('input[name="categoria_id"]').forEach(function (r) {
    r.addEventListener('change', function () {
      montarSubcategorias(0);
      erro('categoria_id', '');
      verificarProxima();
    });
  });
  montarSubcategorias(RA.subSelecionada);

  // ------------------------------------------------------------ Etapa 2
  var mapa = null, marcador = null, pedido = 0;
  var geoStatus = campo('geo-status');
  var aviso = campo('aviso-proxima');

  function iniciarMapa() {
    if (mapa) { mapa.invalidateSize(); return; }
    mapa = RA.criarMapa('mapa-local');
    mapa.on('click', function (ev) { marcar(ev.latlng.lat, ev.latlng.lng, true); });
    var lat = parseFloat(campo('latitude').value), lng = parseFloat(campo('longitude').value);
    if (!isNaN(lat) && !isNaN(lng)) {
      marcar(lat, lng, false);
      mapa.setView([lat, lng], 17);
    }
  }

  function marcar(lat, lng, buscarEndereco) {
    campo('latitude').value = lat.toFixed(7);
    campo('longitude').value = lng.toFixed(7);
    if (buscarEndereco) sujo = true;
    if (!marcador) {
      marcador = L.marker([lat, lng], { icon: RA.pino(null), draggable: true }).addTo(mapa);
      marcador.on('dragend', function () {
        var p = marcador.getLatLng();
        marcar(p.lat, p.lng, true);
      });
    } else {
      marcador.setLatLng([lat, lng]);
    }
    erro('local', '');
    if (buscarEndereco) geocodificar(lat, lng);
    verificarProxima();
  }

  function geocodificar(lat, lng) {
    var meu = ++pedido;
    geoStatus.className = 'small mt-2 text-body-secondary';
    geoStatus.textContent = 'Buscando o endereço…';
    fetch(RA.base + '/api/geocodificar.php?lat=' + lat + '&lng=' + lng)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (meu !== pedido) return; // chegou resposta de um clique antigo
        if (!d.ok) throw new Error(d.erro);
        if (d.endereco) { campo('endereco').value = d.endereco; erro('endereco', ''); }
        if (d.bairro) { campo('bairro').value = d.bairro; erro('bairro', ''); }
        if (d.numero && !campo('numero').value) campo('numero').value = d.numero;
        geoStatus.className = 'small mt-2 text-success';
        geoStatus.textContent = d.endereco
          ? 'Endereço preenchido automaticamente. Confira e ajuste se precisar.'
          : 'Local marcado. Não achamos o nome da rua — preencha abaixo.';
      })
      .catch(function (e) {
        if (meu !== pedido) return;
        geoStatus.className = 'small mt-2 text-warning-emphasis';
        geoStatus.textContent = (e && e.message && e.message.indexOf('JSON') < 0 && e.message !== 'Failed to fetch' ? e.message
          : 'Não foi possível obter o endereço automaticamente.') + ' O local foi marcado; preencha os campos abaixo.';
      });
  }

  campo('btn-gps').addEventListener('click', function () {
    if (!navigator.geolocation) {
      geoStatus.textContent = 'Seu navegador não oferece localização. Clique no mapa.';
      return;
    }
    geoStatus.className = 'small mt-2 text-body-secondary';
    geoStatus.textContent = 'Obtendo sua localização…';
    navigator.geolocation.getCurrentPosition(function (pos) {
      mapa.setView([pos.coords.latitude, pos.coords.longitude], 17);
      marcar(pos.coords.latitude, pos.coords.longitude, true);
    }, function () {
      geoStatus.className = 'small mt-2 text-warning-emphasis';
      geoStatus.textContent = 'Não foi possível obter sua localização. Clique no mapa para marcar o local.';
    }, { enableHighAccuracy: true, timeout: 10000 });
  });

  // Alternativa ao clique para quem usa só o teclado: as setas movem o mapa e este botão marca o centro.
  campo('btn-centro').addEventListener('click', function () {
    var c = mapa.getCenter();
    marcar(c.lat, c.lng, true);
  });

  // Aviso de solicitação parecida a menos de 50 m
  var pedidoProx = 0;
  function verificarProxima() {
    var cat = categoriaMarcada();
    var lat = campo('latitude').value, lng = campo('longitude').value;
    aviso.hidden = true;
    if (!cat || !lat || !lng) return;
    var meu = ++pedidoProx;
    fetch(RA.base + '/api/proximas.php?categoria_id=' + cat.id + '&lat=' + lat + '&lng=' + lng)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (meu !== pedidoProx || !d.ok || !d.proxima) return;
        mostrarAviso(d.proxima, cat);
      })
      .catch(function () { /* sem aviso: o registro segue normalmente */ });
  }

  function mostrarAviso(p, cat) {
    var link = RA.base + '/public/solicitacao.php?protocolo=' + encodeURIComponent(p.protocolo);
    var html = '<h3 class="h6"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> ';
    if (p.propria) {
      html += 'Você já registrou este problema</h3>' +
        '<p class="mb-2">Sua solicitação <strong>' + RA.esc(p.protocolo) + '</strong> (' + RA.esc(cat.nome.toLowerCase()) +
        ') está em aberto a ' + p.distancia + ' m deste ponto.</p>' +
        '<a class="btn btn-sm btn-primary" href="' + RA.esc(link) + '">Acompanhar a solicitação</a>';
    } else {
      html += 'Já existe uma solicitação parecida aqui perto</h3>' +
        '<p class="mb-2"><strong>' + RA.esc(p.protocolo) + '</strong> — ' + RA.esc(cat.nome) + ' em ' + RA.esc(p.endereco) +
        ', a ' + p.distancia + ' m deste ponto. Situação: ' + RA.esc(p.situacao) + ' · registrada em ' + RA.esc(p.data) +
        ' · ' + p.apoios + (p.apoios === 1 ? ' apoio' : ' apoios') + '.</p>';
      if (p.ja_apoia) {
        html += '<p class="mb-2"><i class="bi bi-check-lg" aria-hidden="true"></i> Você já apoia esta solicitação.</p>' +
          '<a class="btn btn-sm btn-primary" href="' + RA.esc(link) + '">Acompanhar a solicitação</a>';
      } else {
        html += '<div class="d-flex flex-wrap gap-2">' +
          '<button type="button" class="btn btn-sm btn-acao" id="btn-apoiar"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Apoiar a solicitação existente</button>' +
          '<a class="btn btn-sm btn-outline-primary" href="' + RA.esc(link) + '" target="_blank" rel="noopener">Ver detalhes</a></div>';
      }
      html += '<div class="small mt-2">Se for outro problema, é só continuar o registro.</div>';
    }
    html += '<div class="small text-danger mt-2" id="erro-apoiar"></div>';
    aviso.innerHTML = html;
    aviso.hidden = false;

    var btn = document.getElementById('btn-apoiar');
    if (btn) btn.addEventListener('click', function () {
      btn.disabled = true;
      var corpo = new URLSearchParams({ id: p.id, csrf: csrf });
      fetch(RA.base + '/api/apoiar.php', { method: 'POST', body: corpo })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) throw new Error(d.erro);
          enviando = true; // a pessoa escolheu apoiar em vez de registrar: sai sem aviso
          window.location.href = link;
        })
        .catch(function (e) {
          btn.disabled = false;
          document.getElementById('erro-apoiar').textContent = (e && e.message) || 'Não foi possível registrar o apoio.';
        });
    });
  }

  // ------------------------------------------------------------ Etapa 3
  var descricao = campo('descricao'), contador = campo('contador');
  function contar() { contador.textContent = descricao.value.trim().length; }
  descricao.addEventListener('input', contar);
  contar();

  var inputFotos = campo('fotos'), previas = campo('previas');
  function validarFotos() {
    var arquivos = Array.prototype.slice.call(inputFotos.files);
    previas.innerHTML = '';
    var msg = '';
    if (arquivos.length > RA.limites.fotos) {
      msg = 'Envie no máximo ' + RA.limites.fotos + ' fotos (você selecionou ' + arquivos.length + ').';
    }
    arquivos.forEach(function (a) {
      if (msg) return;
      if (['image/jpeg', 'image/png'].indexOf(a.type) < 0) msg = '"' + a.name + '" não é uma imagem JPG ou PNG.';
      else if (a.size > RA.limites.bytes) {
        msg = '"' + a.name + '" passa do limite de ' + Math.round(RA.limites.bytes / 1048576) + ' MB.';
      }
    });
    if (!msg) {
      arquivos.forEach(function (a) {
        var img = document.createElement('img');
        img.src = URL.createObjectURL(a);
        img.alt = 'Prévia de ' + a.name;
        img.onload = function () { URL.revokeObjectURL(img.src); };
        previas.appendChild(img);
      });
    }
    return erro('fotos', msg);
  }
  inputFotos.addEventListener('change', validarFotos);

  // ------------------------------------------------------------ Etapa 4
  function montarResumo() {
    var cat = categoriaMarcada();
    var sub = selSub.options[selSub.selectedIndex];
    var local = campo('endereco').value.trim();
    if (campo('numero').value.trim()) local += ', ' + campo('numero').value.trim();
    local += ' — ' + campo('bairro').value.trim();
    var linhas = [
      ['Tipo', cat.nome + ' · ' + (sub ? sub.text : '')],
      ['Local', local],
      ['Ponto de referência', campo('referencia').value.trim() || '—'],
      ['Descrição', descricao.value.trim()],
      ['Fotos', inputFotos.files.length ? inputFotos.files.length + (inputFotos.files.length === 1 ? ' foto' : ' fotos') : 'Nenhuma'],
      ['Identificação', campo('sigilo').checked ? 'Nome em sigilo (visível só para a prefeitura)' : 'Nome visível na solicitação']
    ];
    var resumo = campo('resumo');
    resumo.innerHTML = '';
    linhas.forEach(function (l) {
      var dt = document.createElement('dt'); dt.className = 'col-sm-4'; dt.textContent = l[0];
      var dd = document.createElement('dd'); dd.className = 'col-sm-8 ra-texto-livre'; dd.textContent = l[1];
      resumo.appendChild(dt); resumo.appendChild(dd);
    });
    var previsao = new Date();
    previsao.setDate(previsao.getDate() + cat.prazo);
    campo('resumo-prazo').innerHTML = '<i class="bi bi-info-circle" aria-hidden="true"></i> Setor responsável: <strong>' + RA.esc(cat.setor) +
      '</strong>. Previsão de atendimento: <strong>' + previsao.toLocaleDateString('pt-BR') + '</strong> (' + cat.prazo + ' dias).';
  }

  // ------------------------------------------------------------ Validação por etapa
  var validar = {
    1: function () {
      var ok = erro('categoria_id', categoriaMarcada() ? '' : 'Escolha o tipo de problema.');
      if (ok) ok = erro('subcategoria_id', selSub.value ? '' : 'Escolha o que melhor descreve o problema.');
      return ok;
    },
    2: function () {
      var a = erro('local', campo('latitude').value && campo('longitude').value ? '' : 'Marque o local do problema clicando no mapa.');
      var b = erro('endereco', campo('endereco').value.trim().length >= 3 ? '' : 'Informe o nome da rua ou avenida.');
      var c = erro('bairro', campo('bairro').value.trim().length >= 2 ? '' : 'Informe o bairro.');
      return a && b && c;
    },
    3: function () {
      var n = descricao.value.trim().length;
      var a = erro('descricao', n < 10 ? 'Descreva o problema com pelo menos 10 caracteres.'
        : n > 500 ? 'A descrição pode ter no máximo 500 caracteres.' : '');
      var b = validarFotos();
      return a && b;
    },
    4: function () { return true; }
  };

  // O erro some assim que a pessoa corrige o campo.
  ['endereco', 'bairro', 'descricao', 'subcategoria_id'].forEach(function (nome) {
    campo(nome).addEventListener('input', function () { erro(nome, ''); });
  });

  // ------------------------------------------------------------ Navegação
  function mostrar(n, focar) {
    atual = n;
    etapas.forEach(function (s) { s.hidden = parseInt(s.dataset.etapa, 10) !== n; });
    indicadores.forEach(function (li) {
      var i = parseInt(li.dataset.indicador, 10);
      li.classList.toggle('ativa', i === n);
      li.classList.toggle('feita', i < n);
      if (i === n) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
    btnVoltar.hidden = n === 1;
    btnCancelar.hidden = n !== 1;
    btnAvancar.hidden = n === 4;
    btnEnviar.hidden = n !== 4;
    if (n === 2) iniciarMapa();
    if (n === 4) montarResumo();
    if (focar) {
      var titulo = etapas[n - 1].querySelector('h2');
      titulo.setAttribute('tabindex', '-1');
      titulo.focus();
      var semMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: semMovimento ? 'auto' : 'smooth' });
    }
  }

  function avancar() {
    if (validar[atual]()) mostrar(atual + 1, true); else focarErro(atual);
  }

  btnAvancar.addEventListener('click', avancar);
  btnVoltar.addEventListener('click', function () { mostrar(atual - 1, true); });

  form.addEventListener('submit', function (ev) {
    // Enter no meio do formulário funciona como "Continuar".
    if (atual !== 4) {
      ev.preventDefault();
      avancar();
      return;
    }
    for (var n = 1; n <= 3; n++) {
      if (!validar[n]()) { ev.preventDefault(); mostrar(n, true); focarErro(n); return; }
    }
    enviando = true;
    btnEnviar.disabled = true;
    btnEnviar.textContent = 'Enviando…';
  });

  // Ao voltar pelo histórico do navegador, a página pode reaparecer como estava: destrava o botão.
  var rotuloEnviar = btnEnviar.innerHTML;
  window.addEventListener('pageshow', function (ev) {
    if (!ev.persisted) return;
    enviando = false;
    btnEnviar.disabled = false;
    btnEnviar.innerHTML = rotuloEnviar;
  });

  // Erros que vieram do servidor: liga cada mensagem ao seu campo para leitores de tela.
  form.querySelectorAll('[data-erro]').forEach(function (el) {
    if (el.textContent) erro(el.dataset.erro, el.textContent);
  });

  mostrar(RA.etapaInicial || 1, false);
});
