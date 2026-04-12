<?php

declare(strict_types=1);

/**
 * Mensagens para o utilizador final (sem detalhes técnicos).
 *
 * @param 'nif_invalid'|'year_invalid'|'year_not_available'|'nif_not_found'|'file_not_found'|'download_failed'|'config_error'|'local_storage_missing'|'ftp_error' $code
 */
function documento_error_messages(): array
{
    $indisponivel = [
        'title' => 'Consulta temporariamente indisponível',
        'body'  => 'De momento não foi possível mostrar o documento. Por favor tente mais tarde. Se precisar de ajuda, contacte a Nuvipama.',
    ];

    return [
        'nif_invalid' => [
            'title' => 'Dados incorretos',
            'body'  => 'O NIF do condomínio deve ter exatamente 9 algarismos. Verifique o que escreveu e tente novamente.',
        ],
        'year_invalid' => [
            'title' => 'Ano inválido',
            'body'  => '',
        ],
        'year_not_available' => [
            'title' => 'Ano não disponível',
            'body'  => '',
        ],
        'nif_not_found' => [
            'title' => 'Documento não encontrado',
            'body'  => 'Não encontrámos um documento para os dados que indicou. Confirme o NIF e o ano e tente novamente, ou contacte a Nuvipama.',
        ],
        'file_not_found' => [
            'title' => 'Documento não encontrado',
            'body'  => 'Não encontrámos um documento para os dados que indicou. Confirme o NIF e o ano e tente novamente, ou contacte a Nuvipama.',
        ],
        'download_failed' => [
            'title' => 'Não foi possível mostrar o documento',
            'body'  => 'Ocorreu um problema ao abrir o documento. Tente novamente dentro de alguns minutos. Se continuar sem resultado, contacte a Nuvipama.',
        ],
        'config_error' => $indisponivel,
        'local_storage_missing' => $indisponivel,
        'ftp_error' => $indisponivel,
    ];
}

/**
 * @param 'nif_invalid'|'year_invalid'|'year_not_available'|'nif_not_found'|'file_not_found'|'download_failed'|'config_error'|'local_storage_missing'|'ftp_error' $code
 * @param array{year_min?: int, year_max?: int, year?: int, current_year?: int} $context
 */
function documento_render_error(string $code, int $httpStatus, array $context = []): void
{
    $map = documento_error_messages();
    $entry = $map[$code] ?? $map['ftp_error'];
    $title = htmlspecialchars($entry['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $bodyRaw = $entry['body'];
    if ($code === 'year_invalid') {
        $min = (int) ($context['year_min'] ?? 2000);
        $max = (int) ($context['year_max'] ?? 2050);
        $bodyRaw = sprintf(
            'Indique um ano com quatro algarismos, entre %d e %d. Por exemplo: %d.',
            $min,
            $max,
            min(max((int) date('Y'), $min), $max)
        );
    } elseif ($code === 'year_not_available') {
        $asked = (int) ($context['year'] ?? 0);
        $current = (int) ($context['current_year'] ?? (int) date('Y'));
        if ($asked > 0) {
            $bodyRaw = sprintf(
                'Só pode consultar documentos até ao ano em curso (%d). O ano %d ainda não está disponível — escolha o ano atual ou um ano já terminado.',
                $current,
                $asked
            );
        } else {
            $bodyRaw = 'Só pode consultar documentos até ao ano em curso. Escolha o ano atual ou um ano já concluído.';
        }
    }

    $body = htmlspecialchars($bodyRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $cssHref = 'assets/css/style.css';

    http_response_code($httpStatus);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');

    echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') . '">';
    echo '<title>' . $title . '</title></head><body class="doc-error-body">';
    echo '<div class="doc-error-page"><div class="doc-error-page__icon" aria-hidden="true">!</div>';
    echo '<h1 class="doc-error-page__title">' . $title . '</h1>';
    echo '<p class="doc-error-page__text">' . $body . '</p></div></body></html>';
}
