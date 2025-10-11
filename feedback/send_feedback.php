<?php
declare(strict_types=1);

/**
 * Good Foggia - Feedback endpoint
 * - Legge credenziali da /feedback/private/mail_config.php
 * - Tenta invio con PHPMailer (Composer o include manuale)
 * - Fallback automatico su mail() se PHPMailer non è disponibile
 */

header('Content-Type: application/json; charset=UTF-8');

/* ============ Helper JSON ============ */
function respond(int $status, array $payload): void {
  http_response_code($status);
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  echo $json !== false ? $json : '{"success":false,"error":"Errore di serializzazione."}';
  exit;
}

/* ============ Consenti solo POST ============ */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  respond(405, ['success' => false, 'error' => 'Metodo non consentito.']);
}

/* ============ Honeypot ============ */
$honeypot = isset($_POST['website']) ? trim((string)$_POST['website']) : '';
if ($honeypot !== '') {
  respond(400, ['success' => false, 'error' => 'Richiesta non valida.']);
}

/* ============ Input & validazione ============ */
$name    = isset($_POST['name'])    ? trim((string)$_POST['name'])    : '';
$email   = isset($_POST['email'])   ? trim((string)$_POST['email'])   : '';
$message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';

if ($name !== '' && mb_strlen($name) > 120) {
  respond(400, ['success' => false, 'error' => 'Il nome può contenere al massimo 120 caratteri.']);
}
if ($email !== '' && mb_strlen($email) > 160) {
  respond(400, ['success' => false, 'error' => "L'email può contenere al massimo 160 caratteri."]);
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
  respond(400, ['success' => false, 'error' => 'Inserisci un indirizzo email valido oppure lascia il campo vuoto.']);
}
if ($message === '') {
  respond(400, ['success' => false, 'error' => 'Il messaggio è obbligatorio.']);
}
if (mb_strlen($message) > 1000) {
  respond(400, ['success' => false, 'error' => 'Il messaggio non può superare 1000 caratteri.']);
}

$messageClean = trim(preg_replace('/[[:cntrl:]]+/', ' ', strip_tags($message)));
if ($messageClean === '') {
  respond(400, ['success' => false, 'error' => 'Il messaggio non può essere vuoto.']);
}

/* ============ Config privata ============ */
$configPath = __DIR__ . '/private/mail_config.php';
if (!is_file($configPath)) {
  respond(500, ['success' => false, 'error' => 'Configurazione email mancante.']);
}
$config = require $configPath;

$SMTP_HOST   = $config['host']   ?? '';
$SMTP_PORT   = (int)($config['port']   ?? 465);
$SMTP_SECURE = $config['secure'] ?? 'ssl'; // 'ssl' | 'tls'
$SMTP_USER   = $config['user']   ?? '';
$SMTP_PASS   = $config['pass']   ?? '';
$SMTP_FROM   = $config['from']   ?? $SMTP_USER;
$SMTP_TO     = $config['to']     ?? $SMTP_USER;

if ($SMTP_FROM === '' || $SMTP_TO === '') {
  respond(500, ['success' => false, 'error' => 'Configurazione SMTP non valida.']);
}

/* ============ Prova a caricare PHPMailer ============ */
$hasPHPMailer = false;

// 1) Composer autoload
$autoloadPaths = [
  __DIR__ . '/../vendor/autoload.php',          // /feedback/../vendor
  __DIR__ . '/vendor/autoload.php',             // /feedback/vendor
  $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php', // /public_html/vendor
];
foreach ($autoloadPaths as $path) {
  if (is_file($path)) { require_once $path; $hasPHPMailer = true; break; }
}

// 2) Include manuale (senza Composer) se non trovato
if (!$hasPHPMailer) {
  $base = __DIR__ . '/libs/phpmailer/src';
  if (is_file($base.'/PHPMailer.php') && is_file($base.'/SMTP.php') && is_file($base.'/Exception.php')) {
    require_once $base.'/PHPMailer.php';
    require_once $base.'/SMTP.php';
    require_once $base.'/Exception.php';
    $hasPHPMailer = true;
  }
}

/* ============ Se c'è PHPMailer → usa SMTP Aruba ============ */
if ($hasPHPMailer) {
  try {
    // Import dopo include
    $PHPMailer = 'PHPMailer\\PHPMailer\\PHPMailer';
    $Exception = 'PHPMailer\\PHPMailer\\Exception';

    /** @var \PHPMailer\PHPMailer\PHPMailer $mail */
    $mail = new $PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST ?: 'smtps.aruba.it';
    $mail->SMTPAuth   = true;
    $mail->Username   = $SMTP_USER;
    $mail->Password   = $SMTP_PASS;
    $mail->CharSet    = 'UTF-8';
    if (($SMTP_SECURE ?? 'ssl') === 'ssl') {
      $mail->SMTPSecure = constant('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS');
      $mail->Port       = $SMTP_PORT ?: 465;
    } else {
      $mail->SMTPSecure = constant('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS');
      $mail->Port       = $SMTP_PORT ?: 587;
    }

    $mail->setFrom($SMTP_FROM, 'GOOD FINE DINING Feedback');
    $mail->addAddress($SMTP_TO);
    if ($email !== '') {
      $mail->addReplyTo($email, $name !== '' ? $name : $email);
    } else {
      $mail->addReplyTo($SMTP_FROM);
    }

    $mail->Subject = 'Nuovo feedback dal sito GOOD FINE DINING';
    $bodyLines = [
      'Hai ricevuto un nuovo feedback dal sito goodfoggia.it.',
      '',
      'Nome: ' . ($name !== '' ? $name : 'Non specificato'),
      'Email: ' . ($email !== '' ? $email : 'Non specificata'),
      '',
      'Messaggio:',
      $messageClean,
      '',
      'Indirizzo IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'ND'),
      'User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'ND'),
      'Data e ora (UTC): ' . gmdate('d/m/Y H:i:s'),
    ];
    $mail->Body = implode("\n", $bodyLines);
    $mail->AltBody = $mail->Body;

    $mail->send();
    respond(200, ['success' => true]);

  } catch (\Throwable $e) {
    // Se SMTP fallisce per qualunque motivo → prova fallback su mail()
    // (non riveliamo l’errore al client in produzione)
  }
}

/* ============ Fallback su mail() nativa ============ */
/**
 * NOTE:
 * - Aruba inoltra via sendmail e spesso è affidabile se il FROM è del dominio.
 * - Usiamo header corretti e forziamo il Return-Path con -f$SMTP_FROM.
 */
$subject = 'Nuovo feedback dal sito GOOD FINE DINING';
$nl = "\r\n";
$fromNameEnc = 'GOOD FINE DINING Feedback';
if (function_exists('mb_encode_mimeheader')) {
  $fromNameEnc = mb_encode_mimeheader($fromNameEnc, 'UTF-8', 'B');
}

$headers = [];
$headers[] = "From: {$fromNameEnc} <{$SMTP_FROM}>";
if ($email !== '') {
  $replyName = $name !== '' ? $name : $email;
  $replyEnc  = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($replyName, 'UTF-8', 'B') : $replyName;
  $headers[] = "Reply-To: {$replyEnc} <{$email}>";
}
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "X-Mailer: PHP/" . PHP_VERSION;

$body = "Hai ricevuto un nuovo feedback dal sito goodfoggia.it.\n\n"
      . "Nome: " . ($name !== '' ? $name : 'Non specificato') . "\n"
      . "Email: " . ($email !== '' ? $email : 'Non specificata') . "\n\n"
      . "Messaggio:\n{$messageClean}\n\n"
      . "Indirizzo IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'ND') . "\n"
      . "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'ND') . "\n"
      . "Data e ora (UTC): " . gmdate('d/m/Y H:i:s') . "\n";

$ok = @mail($SMTP_TO, $subject, $body, implode($nl, $headers), "-f{$SMTP_FROM}");
if ($ok) {
  respond(200, ['success' => true]);
} else {
  respond(500, ['success' => false, 'error' => 'Impossibile inviare il feedback al momento.']);
}