<?php
require_once __DIR__ . '/../app/iniciar.php';

// Sair só por POST com token, para que um link externo não derrube a sessão.
if (eh_post() && csrf_valido()) {
    fazer_logout();
    iniciar_sessao();
    flash('sucesso', 'Você saiu da sua conta.');
}
redirecionar('public/index.php');
