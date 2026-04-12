<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.']);
    exit;
}

require_once __DIR__ . '/lib/config.php';

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrf_seguros']) || !hash_equals($_SESSION['csrf_seguros'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sessão expirou. Atualize a página e tente novamente.']);
    exit;
}

$name = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['telefone'] ?? ''));
$need = trim((string) ($_POST['necessidade'] ?? ''));
$address = trim((string) ($_POST['endereco_condominio'] ?? ''));
$unit = trim((string) ($_POST['numero_unidade'] ?? ''));

if ($name === '' || $email === '' || $phone === '' || $need === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'E-mail inválido.']);
    exit;
}

$config = load_config();
$mailCfg = $config['mail'] ?? [];
$to = (string) ($mailCfg['to'] ?? 'condominio@nuvipama.pt');
$from = (string) ($mailCfg['from'] ?? 'noreply@nuvipama.pt');

$subject = 'Seguros — novo contacto: ' . mb_substr($name, 0, 60);
$body = "Novo pedido de informações (Seguros)\r\n\r\n";
$body .= "Nome: {$name}\r\n";
$body .= "E-mail: {$email}\r\n";
$body .= "Telefone: {$phone}\r\n";
$body .= "Necessidade:\r\n{$need}\r\n";
if ($address !== '') {
    $body .= "\r\nEndereço do condomínio: {$address}\r\n";
}
if ($unit !== '') {
    $body .= "Número da unidade: {$unit}\r\n";
}
$body .= "\r\n— Enviado a partir do site de gestão de condomínio.\r\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: ' . $from,
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . PHP_VERSION,
];

$ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível enviar a mensagem. Contacte-nos por telefone ou e-mail.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Mensagem enviada com sucesso.']);
