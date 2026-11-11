<?php
/**
 * Recebimento seguro das fotos: valida o tipo real, redimensiona e salva
 * com nome aleatório em /uploads (sempre regravada como JPEG pelo GD,
 * o que descarta qualquer conteúdo estranho embutido no arquivo).
 */

function pasta_uploads(): string
{
    return dirname(__DIR__) . '/uploads';
}

/**
 * Valida os arquivos de $_FILES[campo] (enviado como campo[]).
 * Devolve a lista de arquivos válidos (caminho temporário) e preenche $erros.
 */
function validar_fotos(?array $arquivos, array &$erros): array
{
    $validos = [];
    if (!$arquivos || !isset($arquivos['name']) || !is_array($arquivos['name'])) {
        return $validos;
    }

    $total = 0;
    foreach ($arquivos['name'] as $i => $nome) {
        $erro = $arquivos['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($erro === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $total++;
        $rotulo = 'Foto "' . mb_strimwidth((string) $nome, 0, 40, '…') . '"';

        if ($total > FOTO_MAX_QTD) {
            $erros[] = 'Envie no máximo ' . FOTO_MAX_QTD . ' fotos.';
            break;
        }
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            $erros[] = "$rotulo: o arquivo passa do limite de 5 MB.";
            continue;
        }
        if ($erro !== UPLOAD_ERR_OK) {
            $erros[] = "$rotulo: falha no envio. Tente novamente.";
            continue;
        }
        $tmp = $arquivos['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) {
            $erros[] = "$rotulo: envio inválido.";
            continue;
        }
        if (filesize($tmp) > FOTO_MAX_BYTES) {
            $erros[] = "$rotulo: o arquivo passa do limite de 5 MB.";
            continue;
        }

        // Tipo real, pelo conteúdo — a extensão e o tipo informados pelo navegador são ignorados.
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)
            || !$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $erros[] = "$rotulo: formato não aceito. Envie apenas imagens JPG ou PNG.";
            continue;
        }
        if ($info[0] * $info[1] > 50_000_000) {
            $erros[] = "$rotulo: a imagem é grande demais (dimensões).";
            continue;
        }
        $validos[] = ['tmp' => $tmp, 'tipo' => $info[2]];
    }
    return $validos;
}

/**
 * Redimensiona e grava uma foto validada. Devolve o nome do arquivo salvo.
 */
function salvar_foto(array $foto): string
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('A extensão GD do PHP não está ativa (veja o README).');
    }
    $origem = $foto['tipo'] === IMAGETYPE_PNG
        ? @imagecreatefrompng($foto['tmp'])
        : @imagecreatefromjpeg($foto['tmp']);
    if (!$origem) {
        throw new RuntimeException('Não foi possível ler uma das imagens enviadas.');
    }

    // Corrige a rotação de fotos tiradas com o celular.
    if ($foto['tipo'] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($foto['tmp']);
        $giro = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
        if ($giro) {
            $girada = imagerotate($origem, $giro, 0);
            if ($girada) {
                imagedestroy($origem);
                $origem = $girada;
            }
        }
    }

    $w = imagesx($origem);
    $h = imagesy($origem);
    $escala = min(1, FOTO_MAX_LADO / max($w, $h));
    $nw = max(1, (int) round($w * $escala));
    $nh = max(1, (int) round($h * $escala));

    $destino = imagecreatetruecolor($nw, $nh);
    imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255)); // fundo branco para PNG transparente
    imagecopyresampled($destino, $origem, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $nome = bin2hex(random_bytes(16)) . '.jpg';
    $ok = imagejpeg($destino, pasta_uploads() . '/' . $nome, 85);
    imagedestroy($origem);
    imagedestroy($destino);
    if (!$ok) {
        throw new RuntimeException('Não foi possível salvar a foto. Verifique a permissão de escrita da pasta uploads.');
    }
    return $nome;
}

function apagar_fotos(array $nomes): void
{
    foreach ($nomes as $n) {
        @unlink(pasta_uploads() . '/' . basename($n));
    }
}
