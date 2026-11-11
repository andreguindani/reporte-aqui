<?php
// A aplicação fica em /public — este arquivo só encaminha quem acessa a raiz.
header('Location: public/index.php');
exit;
