<?php
/**
 * Rodapé comum. Variáveis aceitas:
 *   $usa_mapa   carrega o Leaflet
 *   $scripts    lista de arquivos em assets/js
 *   $dados_js   dados PHP entregues ao JavaScript em window.RA
 */
$dados_js = array_merge([
    'base' => base_url(),
    'mapa' => ['lat' => MAPA_LAT, 'lng' => MAPA_LNG, 'zoom' => MAPA_ZOOM],
], $dados_js ?? []);
?>
</main>

<footer class="ra-rodape">
  <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
    <div>
      <strong>Reporte Aqui</strong> — a cidade cuidada por quem vive nela.<br>
      <span class="text-body-secondary">Projeto acadêmico · Ciência da Computação · UNOESC Videira</span>
    </div>
    <div class="text-md-end">
      <a href="<?= e(url('public/como-funciona.php')) ?>">Como funciona</a> ·
      <a href="<?= e(url('public/consulta.php')) ?>">Consultar protocolo</a><br>
      <span class="text-body-secondary">Emergências: ligue 193 (Bombeiros) ou 199 (Defesa Civil)</span>
    </div>
  </div>
</footer>

<script>window.RA = <?= json_script($dados_js) ?>;</script>
<script src="<?= e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<?php if (!empty($usa_mapa)): ?>
<script src="<?= e(url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(url('assets/js/mapa.js')) ?>"></script>
<?php endif; ?>
<script>
// Links com data-voltar retornam à página anterior quando ela é do próprio site
// (mantém filtros e posição); caso contrário seguem o href normal.
// Formulários (nova solicitação, login, cadastro) não contam: voltar para eles reabriria o formulário já enviado.
document.querySelectorAll('[data-voltar]').forEach(function (link) {
  link.addEventListener('click', function (ev) {
    var veioDoSite = false;
    try {
      var origem = new URL(document.referrer);
      veioDoSite = origem.origin === location.origin && origem.pathname !== location.pathname
        && !/\/(nova|login|cadastro)\.php$/.test(origem.pathname);
    } catch (e) {}
    if (veioDoSite && history.length > 1) { ev.preventDefault(); history.back(); }
  });
});
</script>
<?php foreach (($scripts ?? []) as $arquivo): ?>
<script src="<?= e(url('assets/js/' . $arquivo)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
