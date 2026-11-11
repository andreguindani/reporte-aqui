<?php
require_once __DIR__ . '/../app/iniciar.php';

$categorias = listar_categorias();

$passos = [
    ['bi-geo-alt', 'Você registra', 'Escolha o tipo de problema, marque o local no mapa, descreva e envie até 3 fotos.'],
    ['bi-inbox', 'A prefeitura recebe', 'A solicitação ganha um protocolo e vai direto para o setor responsável.'],
    ['bi-tools', 'A equipe atende', 'O setor analisa, programa o serviço e informa cada passo por mensagens.'],
    ['bi-check2-circle', 'Você acompanha e avalia', 'Veja a situação a qualquer momento e dê uma nota ao atendimento quando for concluído.'],
];

$perguntas = [
    ['Preciso de conta para registrar um problema?',
     'Sim. A conta evita registros falsos e permite que a prefeitura envie as respostas a você. Para apenas consultar um protocolo ou ver o mapa não é preciso entrar.'],
    ['Meu nome aparece para outras pessoas?',
     'Só se você quiser. Ao registrar, marque "manter meu nome em sigilo" e o seu nome ficará visível apenas para a equipe da prefeitura. CPF, e-mail e telefone nunca são exibidos publicamente.'],
    ['Já existe uma solicitação igual à minha. O que eu faço?',
     'Apoie a solicitação existente. Quando você marca um local a menos de 50 metros de um problema do mesmo tipo ainda em aberto, o sistema avisa e oferece o apoio. Os apoios mostram à prefeitura quantas pessoas são afetadas.'],
    ['O que significa cada situação?',
     'Recebida: chegou à prefeitura e aguarda triagem. Em análise: o setor está avaliando e programando o serviço. Em execução: a equipe está trabalhando no local. Concluída: o problema foi resolvido.'],
    ['O que acontece se o prazo passar?',
     'A solicitação aparece como "atrasada" para você e é destacada no painel da prefeitura, que acompanha tudo o que está fora do prazo.'],
    ['Posso enviar fotos?',
     'Sim, até 3 fotos em JPG ou PNG, com no máximo 5 MB cada. Fotos ajudam a equipe a localizar e avaliar o problema mais rápido.'],
    ['E em caso de emergência?',
     'O Reporte Aqui não é um canal de emergência. Em situações de risco imediato, ligue 193 (Bombeiros), 199 (Defesa Civil) ou 190 (Polícia Militar).'],
];

$titulo = 'Como funciona';
$pagina_ativa = 'como';
require __DIR__ . '/../app/layout/cabecalho.php';
?>
<div class="container ra-medio py-5">
  <h1 class="h2">Como funciona</h1>
  <p class="lead text-body-secondary">Do registro à solução, em quatro passos.</p>

  <div class="row g-3 my-3">
<?php foreach ($passos as $i => $p): ?>
    <div class="col-sm-6">
      <div class="card ra-card h-100">
        <div class="card-body d-flex gap-3">
          <div class="ra-item-icone"><i class="bi <?= e($p[0]) ?>"></i></div>
          <div>
            <h2 class="h6 mb-1"><?= $i + 1 ?>. <?= e($p[1]) ?></h2>
            <p class="mb-0 text-body-secondary"><?= e($p[2]) ?></p>
          </div>
        </div>
      </div>
    </div>
<?php endforeach; ?>
  </div>

  <h2 class="h4 mt-5">Prazos de atendimento</h2>
  <p class="text-body-secondary">Cada tipo de problema tem um setor responsável e um prazo previsto, contado a partir do registro.</p>
  <div class="card ra-card">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead>
          <tr><th scope="col">Tipo de problema</th><th scope="col">Setor responsável</th><th scope="col" class="text-end">Prazo</th></tr>
        </thead>
        <tbody>
<?php foreach ($categorias as $c): ?>
          <tr>
            <td><i class="bi <?= e($c['icone']) ?> me-2 text-primary"></i><?= e($c['nome']) ?></td>
            <td><?= e($c['setor']) ?></td>
            <td class="text-end text-nowrap"><strong><?= (int) $c['prazo_dias'] ?> dias</strong></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <h2 class="h4 mt-5 mb-3">Perguntas frequentes</h2>
  <div class="accordion" id="faq">
<?php foreach ($perguntas as $i => $p): ?>
    <div class="accordion-item">
      <h3 class="accordion-header">
        <button class="accordion-button<?= $i ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse"
                data-bs-target="#faq-<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq-<?= $i ?>">
          <?= e($p[0]) ?>
        </button>
      </h3>
      <div id="faq-<?= $i ?>" class="accordion-collapse collapse<?= $i ? '' : ' show' ?>" data-bs-parent="#faq">
        <div class="accordion-body"><?= e($p[1]) ?></div>
      </div>
    </div>
<?php endforeach; ?>
  </div>

  <div class="text-center mt-5">
    <a class="btn btn-acao btn-lg" href="<?= e(url('public/nova.php')) ?>">Registrar um problema</a>
  </div>
</div>
<?php require __DIR__ . '/../app/layout/rodape.php'; ?>
