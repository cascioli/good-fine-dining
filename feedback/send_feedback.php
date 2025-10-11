<?php
declare(strict_types=1);

/**
 * Endpoint PHP che riceve i feedback dal modulo pubblico
 * e li inoltra via email utilizzando il server SMTP di Aruba.
 */

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

# Configurazione SMTP - NON pubblicare questo file
$SMTP_HOST=smtps.aruba.it;
$SMTP_PORT=465;
$SMTP_SECURE=ssl;
$SMTP_USER="reclami@goodfoggia.it";
$SMTP_PASS=INSERISCI_LA_PASSWORD;
$SMTP_FROM="reclami@goodfoggia.it";
$SMTP_TO="reclami@goodfoggia.it";


/**
 * Invia una risposta JSON e termina l'esecuzione.
 *
 * @param int   $statusCode Codice di stato HTTP da restituire.
 * @param array $payload    Dati da serializzare in JSON.
 */
function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        echo '{"success":false,"error":"Errore di serializzazione."}';
        exit;
    }

    echo $json;
    exit;
}

// Consenti solo richieste POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'Metodo non consentito.']);
}

// Blocco honeypot contro gli spam bot automatizzati.
$honeypot = isset($_POST['website']) ? trim((string) $_POST['website']) : '';
if ($honeypot !== '') {
    respond(400, ['success' => false, 'error' => 'Richiesta non valida.']);
}

// Normalizzazione e validazione dei dati ricevuti.
$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
$email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

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

// Pulizia del messaggio per prevenire HTML indesiderato.
$messageClean = strip_tags($message);
$messageClean = preg_replace('/[[:cntrl:]]+/', ' ', $messageClean);
$messageClean = trim($messageClean);

if ($messageClean === '') {
    respond(400, ['success' => false, 'error' => 'Il messaggio non può essere vuoto.']);
}

// Autoload Composer (PHPMailer + Dotenv).
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadLoaded = true;
        break;
    }
}

if ($autoloadLoaded === false) {
    respond(500, ['success' => false, 'error' => 'Configurazione mancante: autoload Composer non trovato.']);
}

// Caricamento delle variabili d'ambiente dal file .env esterno alla cartella pubblica.
$envDirectories = [
    realpath(__DIR__ . '/../../'),
    realpath(__DIR__ . '/../'),
];

$envLoaded = false;
foreach ($envDirectories as $envDir) {
    if ($envDir === false) {
        continue;
    }

    $envFile = $envDir . DIRECTORY_SEPARATOR . '.env';
    if (is_file($envFile) && is_readable($envFile)) {
        $dotenv = Dotenv::createImmutable($envDir);
        $dotenv->load();
        $envLoaded = true;
        break;
    }
}

if ($envLoaded === false) {
    respond(500, ['success' => false, 'error' => 'Configurazione mancante: file .env non trovato.']);
}

// Recupero delle credenziali SMTP da ambiente.
$smtpHost = $SMTP_HOST ?? '';
$smtpPort = isset($SMTP_PORT) ? (int) $SMTP_PORT : 0;
$smtpSecure = strtolower($SMTP_SECURE ?? '');
$smtpUser = $SMTP_USER ?? '';
$smtpPass = $SMTP_PASS ?? '';
$smtpFrom = $SMTP_FROM ?? '';
$smtpTo = $SMTP_TO ?? '';

if ($smtpHost === '' || $smtpPort <= 0 || $smtpUser === '' || $smtpPass === '' || $smtpFrom === '' || $smtpTo === '') {
    respond(500, ['success' => false, 'error' => 'Configurazione SMTP non completa.']);
}

// Preparazione e invio dell'email tramite PHPMailer.
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->Port = $smtpPort;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->CharSet = 'UTF-8';
    $mail->setLanguage('it');

    if ($smtpSecure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtpSecure === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom($smtpFrom, 'GOOD FINE DINING Feedback');
    $mail->addAddress($smtpTo);

    if ($email !== '') {
        $replyName = $name !== '' ? $name : $email;
        $mail->addReplyTo($email, $replyName);
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
        'Indirizzo IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'Non disponibile'),
        'User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non disponibile'),
        'Data e ora (UTC): ' . gmdate('d/m/Y H:i:s'),
    ];

    $mail->Body = implode("\n", $bodyLines);
    $mail->AltBody = $mail->Body;

    $mail->send();
} catch (Exception $exception) {
    respond(500, ['success' => false, 'error' => 'Impossibile inviare il feedback. Riprova più tardi.']);
}

respond(200, ['success' => true]);
