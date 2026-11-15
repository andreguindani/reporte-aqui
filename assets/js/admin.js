// Painel da prefeitura: painel lateral de atendimento.
document.addEventListener('DOMContentLoaded', function () {
  var lateralEl = document.getElementById('lateral');
  var lateral = new bootstrap.Offcanvas(lateralEl);
  var corpo = document.getElementById('lateral-corpo');
  var titulo = document.getElementById('lateral-titulo');
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var ordem = Object.keys(RA.situacoes);
  var ultimaLinha = null;
  var sujo = false; // há alterações não salvas no formulário de atendimento

  function esc(t) {
    var d = document.createElement('div');
    d.textContent = t == null ? '' : String(t);
    return d.innerHTML;
  }

  function abrir(id, linha) {
    ultimaLinha = linha || null;
    sujo = false;
    titulo.textContent = 'Carregando…';
    corpo.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando…</span></div></div>';
    lateral.show();
    fetch(RA.base + '/api/admin_solicitacao.php?id=' + encodeURIComponent(id))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.erro);
        desenhar(d.solicitacao);
      })
      .catch(function (e) {
        titulo.textContent = 'Solicitação';
        corpo.innerHTML = '<div class="alert alert-danger" role="alert">' + esc((e && e.message) || 'Não foi possível carregar a solicitação.') + '</div>';
      });
  }

  function opcoes(lista, selecionado) {
    return lista.map(function (o) {
      return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(selecionado) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
    }).join('');
  }

  function desenhar(s) {
    titulo.innerHTML = '<span class="ra-protocolo">' + esc(s.protocolo) + '</span>';
    var concluida = s.situacao === 'concluida';

    // Só a situação atual e as seguintes: a situação nunca volta.
    var situacoes = ordem.slice(ordem.indexOf(s.situacao)).map(function (k) {
      return [k, RA.situacoes[k] + (k === s.situacao ? ' (atual)' : '')];
    });
    var prioridades = Object.keys(RA.prioridades).map(function (k) { return [k, RA.prioridades[k]]; });
    var setores = RA.setores.map(function (x) { return [x.id, x.nome]; });

    var html =
      '<div class="d-flex flex-wrap gap-2 align-items-center mb-2">' +
        '<span class="badge sit sit-' + esc(s.situacao) + '">' + esc(RA.situacoes[s.situacao]) + '</span>' +
        '<span class="badge pri pri-' + esc(s.prioridade) + '">Prioridade ' + esc(RA.prioridades[s.prioridade]) + '</span>' +
        (s.atrasada ? '<span class="badge atrasada">Atrasada</span>' : '') +
        '<span class="ra-item-meta"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> ' + s.apoios +
          '<span class="visually-hidden"> ' + (s.apoios === 1 ? 'apoio' : 'apoios') + '</span></span>' +
      '</div>' +
      '<h3 class="h5 mb-1">' + esc(s.categoria) + ' <small class="text-body-secondary fw-normal">· ' + esc(s.subcategoria) + '</small></h3>' +
      '<p class="mb-1"><i class="bi bi-geo-alt" aria-hidden="true"></i> ' + esc(s.endereco) +
        (s.referencia ? '<br><span class="text-body-secondary small">Referência: ' + esc(s.referencia) + '</span>' : '') + '</p>' +
      '<p class="small mb-2"><a href="https://www.openstreetmap.org/?mlat=' + s.lat + '&mlon=' + s.lng + '#map=18/' + s.lat + '/' + s.lng +
        '" target="_blank" rel="noopener">Ver no mapa</a> · <a href="' + esc(s.link) + '" target="_blank" rel="noopener">Abrir página da solicitação</a></p>' +
      '<p class="ra-texto-livre border rounded p-2 bg-body-tertiary">' + esc(s.descricao) + '</p>';

    if (s.fotos.length) {
      html += '<div class="ra-previas mb-3">' + s.fotos.map(function (f, i) {
        return '<a href="' + esc(f) + '" target="_blank" rel="noopener"><img src="' + esc(f) + '" alt="Foto ' + (i + 1) + '"></a>';
      }).join('') + '</div>';
    }

    html +=
      '<dl class="ra-resumo row small mb-3">' +
        '<dt class="col-5">Solicitante</dt><dd class="col-7">' + esc(s.solicitante.nome) +
          (s.sigilo ? ' <span class="badge text-bg-secondary">pediu sigilo</span>' : '') +
          '<br>' + esc(s.solicitante.telefone) + '<br>' + esc(s.solicitante.email) + '</dd>' +
        '<dt class="col-5">Registrada em</dt><dd class="col-7">' + esc(s.registrada) + '</dd>' +
        (concluida
          ? '<dt class="col-5">Concluída em</dt><dd class="col-7">' + esc(s.concluida_em) + '</dd>'
          : '<dt class="col-5">Previsão</dt><dd class="col-7">' + esc(s.previsao) + '</dd>') +
        (s.avaliacao
          ? '<dt class="col-5">Avaliação</dt><dd class="col-7">' + s.avaliacao.nota + '/5' +
            (s.avaliacao.comentario ? ' — “' + esc(s.avaliacao.comentario) + '”' : '') + '</dd>'
          : '') +
      '</dl>' +

      '<form id="form-atender" class="border rounded p-3 mb-4 bg-white" novalidate>' +
        '<h3 class="h6">Atender</h3>' +
        '<div class="alert alert-danger py-2 small" id="erro-geral" role="alert" hidden></div>' +
        '<input type="hidden" name="id" value="' + s.id + '">' +
        '<div class="mb-2"><label class="form-label small mb-1" for="a-situacao">Situação</label>' +
          '<select class="form-select" id="a-situacao" name="situacao"' + (concluida ? ' disabled' : '') + '>' + opcoes(situacoes, s.situacao) + '</select>' +
          (concluida ? '<input type="hidden" name="situacao" value="concluida"><div class="form-text">Solicitação concluída: a situação não pode mais mudar.</div>'
                     : '<div class="form-text">A situação só avança, nunca volta.</div>') +
          '<div class="invalid-feedback" data-erro="situacao"></div></div>' +
        '<div class="row g-2 mb-2">' +
          '<div class="col-5"><label class="form-label small mb-1" for="a-prioridade">Prioridade</label>' +
            '<select class="form-select" id="a-prioridade" name="prioridade">' + opcoes(prioridades, s.prioridade) + '</select>' +
            '<div class="invalid-feedback" data-erro="prioridade"></div></div>' +
          '<div class="col-7"><label class="form-label small mb-1" for="a-setor_id">Setor</label>' +
            '<select class="form-select" id="a-setor_id" name="setor_id">' + opcoes(setores, s.setor_id) + '</select>' +
            '<div class="invalid-feedback" data-erro="setor_id"></div></div>' +
        '</div>' +
        '<div class="mb-2"><label class="form-label small mb-1" for="a-mensagem">Mensagem ao cidadão ' +
          '<span class="text-body-secondary" id="rotulo-mensagem">(opcional)</span></label>' +
          '<textarea class="form-control" id="a-mensagem" name="mensagem" rows="3" maxlength="500" ' +
            'placeholder="Aparece na linha do tempo da solicitação."></textarea>' +
          '<div class="invalid-feedback" data-erro="mensagem"></div></div>' +
        '<div class="mb-3"><label class="form-label small mb-1" for="a-nota"><i class="bi bi-eye-slash" aria-hidden="true"></i> Nota interna ' +
          '<span class="text-body-secondary">(opcional, só a prefeitura vê)</span></label>' +
          '<textarea class="form-control" id="a-nota" name="nota" rows="2" maxlength="1000"></textarea>' +
          '<div class="invalid-feedback" data-erro="nota"></div></div>' +
        '<button class="btn btn-primary w-100" type="submit">Salvar</button>' +
      '</form>';

    if (s.notas.length) {
      html += '<h3 class="h6"><i class="bi bi-eye-slash" aria-hidden="true"></i> Notas internas</h3><ul class="list-unstyled">' +
        s.notas.map(function (n) {
          return '<li class="ra-linha"><div class="interno mb-2"><div class="ra-texto-livre">' + esc(n.nota) + '</div>' +
            '<div class="quando">' + esc(n.quando) + ' · ' + esc(n.autor) + '</div></div></li>';
        }).join('') + '</ul>';
    }

    html += '<h3 class="h6 mt-3">Histórico</h3><ol class="ra-linha">' +
      s.historico.map(function (h) {
        return '<li style="--cor: var(--sit-' + esc(h.situacao) + ')"><div><strong>' + esc(RA.situacoes[h.situacao]) + '</strong> ' +
          '<span class="quando">' + esc(h.quando) + ' · ' + esc(h.autor) + '</span></div>' +
          (h.interno ? '<div class="interno mt-1"><i class="bi bi-eye-slash" aria-hidden="true"></i> ' + esc(h.mensagem) + '</div>'
                     : '<div class="ra-texto-livre">' + esc(h.mensagem) + '</div>') + '</li>';
      }).join('') + '</ol>';

    corpo.innerHTML = html;

    var form = document.getElementById('form-atender');
    var selSituacao = document.getElementById('a-situacao');
    var rotulo = document.getElementById('rotulo-mensagem');
    selSituacao.addEventListener('change', function () {
      rotulo.textContent = selSituacao.value !== s.situacao ? '(obrigatória ao mudar a situação)' : '(opcional)';
    });

    form.addEventListener('input', function (ev) {
      sujo = true;
      ev.target.classList.remove('is-invalid'); // some o erro assim que o campo é corrigido
    });

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      limparErros(form);
      if (!concluida && selSituacao.value !== s.situacao && !form.mensagem.value.trim()) {
        mostrarErros(form, { mensagem: 'Escreva uma mensagem ao cidadão explicando a mudança de situação.' });
        form.mensagem.focus();
        return;
      }
      // Concluir não tem volta: confirma antes de salvar.
      if (!concluida && selSituacao.value === 'concluida' &&
          !window.confirm('Concluir a solicitação ' + s.protocolo + '? Depois de concluída, a situação não pode mais ser alterada.')) {
        return;
      }
      var botao = form.querySelector('button[type="submit"]');
      botao.disabled = true;
      botao.textContent = 'Salvando…';
      var liberar = function () { botao.disabled = false; botao.textContent = 'Salvar'; };
      var dados = new URLSearchParams(new FormData(form));
      dados.append('csrf', csrf);
      fetch(RA.base + '/api/admin_atualizar.php', { method: 'POST', body: dados })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.ok) {
            // Recarrega sem o parâmetro "abrir" para o painel lateral não reabrir sozinho;
            // a confirmação aparece no topo da página.
            sujo = false;
            var destino = new URL(window.location.href);
            destino.searchParams.delete('abrir');
            if (destino.href === window.location.href) window.location.reload();
            else window.location.replace(destino.href);
            return;
          }
          liberar();
          mostrarErros(form, d.erros || {}, d.erro);
        })
        .catch(function () {
          liberar();
          mostrarErros(form, {}, 'Não foi possível salvar. Verifique a conexão e tente novamente.');
        });
    });
  }

  function limparErros(form) {
    form.querySelectorAll('.is-invalid').forEach(function (c) { c.classList.remove('is-invalid'); });
    document.getElementById('erro-geral').hidden = true;
  }

  function mostrarErros(form, erros, geral) {
    var algum = false;
    Object.keys(erros).forEach(function (nome) {
      var input = document.getElementById('a-' + nome);
      var alvo = form.querySelector('[data-erro="' + nome + '"]');
      if (input && alvo) {
        input.classList.add('is-invalid');
        alvo.textContent = erros[nome];
        algum = true;
      }
    });
    if (geral && (!algum || erros.geral)) {
      var g = document.getElementById('erro-geral');
      g.textContent = geral;
      g.hidden = false;
    }
  }

  // Abrir pelo clique na linha ou pelo link do protocolo (que cuida do teclado).
  // Ctrl/Cmd/Shift + clique seguem o link normalmente e abrem em outra aba.
  document.querySelectorAll('#tabela tbody tr[data-id]').forEach(function (tr) {
    var link = tr.querySelector('a[data-abrir]');
    tr.addEventListener('click', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) return;
      ev.preventDefault();
      abrir(tr.dataset.id, link);
    });
  });
  lateralEl.addEventListener('hide.bs.offcanvas', function (ev) {
    if (sujo && !window.confirm('Descartar as alterações não salvas desta solicitação?')) ev.preventDefault();
    else sujo = false;
  });
  lateralEl.addEventListener('hidden.bs.offcanvas', function () {
    if (ultimaLinha) ultimaLinha.focus();
  });

  if (RA.abrir) abrir(RA.abrir, null);
});
