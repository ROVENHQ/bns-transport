<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

function s(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// Honeypot anti-spam
if (!empty($_POST['_gotcha'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// Champs requis
$prenom  = trim($_POST['prenom'] ?? '');
$nom     = trim($_POST['nom'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$prenom || !$nom || !$email || !$message) {
    http_response_code(400);
    echo json_encode(['error' => 'Champs requis manquants.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Adresse email invalide.']);
    exit;
}

// Champs optionnels
$telephone = trim($_POST['telephone'] ?? '');

// Fichiers requis
$fileFields = ['cv' => 'CV', 'permis' => 'Permis', 'fco' => 'Carte FCO', 'fimo' => 'FIMO'];
$maxSize    = 2 * 1024 * 1024; // 2 Mo
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];

$attachments = [];

foreach ($fileFields as $field => $label) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        echo json_encode(['error' => "Fichier manquant : $label."]);
        exit;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => "Erreur lors de l'upload : $label."]);
        exit;
    }

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => "$label dépasse la taille maximale de 2 Mo."]);
        exit;
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes, true)) {
        http_response_code(400);
        echo json_encode(['error' => "Format non accepté pour $label (PDF, JPG, PNG uniquement)."]);
        exit;
    }

    $content = file_get_contents($file['tmp_name']);
    $attachments[] = [
        'filename' => $field . '_' . s($nom) . '_' . s($prenom) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION),
        'content'  => base64_encode($content),
    ];
}

// Tableau des champs
$rows = [
    ['Prénom',    s($prenom)],
    ['Nom',       s($nom)],
    ['Email',     s($email)],
];
if ($telephone) $rows[] = ['Téléphone', s($telephone)];

$tableRows = '';
foreach ($rows as [$label, $value]) {
    $tableRows .= sprintf(
        '<tr>
          <td style="padding:10px 16px;background:#f1f5f9;font-weight:600;color:#374151;font-size:13px;width:160px;border-bottom:1px solid #e2e8f0;">%s</td>
          <td style="padding:10px 16px;color:#1e293b;font-size:14px;border-bottom:1px solid #e2e8f0;">%s</td>
        </tr>',
        $label,
        $value
    );
}

$messageHtml = nl2br(s($message));
$receivedAt  = date('d/m/Y à H:i');
$nomComplet  = s($prenom) . ' ' . s($nom);

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Nouvelle candidature BNS Transport</title></head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Inter,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

          <tr>
            <td style="background:#1B4B8A;padding:28px 32px;">
              <p style="margin:0;color:#ffffff;font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;opacity:0.6;">BNS Transport — Recrutement</p>
              <h1 style="margin:6px 0 0;color:#ffffff;font-size:22px;font-weight:700;">Nouvelle candidature</h1>
            </td>
          </tr>

          <tr>
            <td style="padding:0;">
              <table width="100%" cellpadding="0" cellspacing="0">
                $tableRows
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:24px 32px;">
              <p style="margin:0 0 10px;font-size:11px;font-weight:700;color:#1B4B8A;text-transform:uppercase;letter-spacing:1.5px;">Présentation</p>
              <div style="background:#F8FAFC;border-left:3px solid #1B4B8A;padding:16px 20px;border-radius:0 6px 6px 0;color:#1e293b;font-size:14px;line-height:1.8;">
                $messageHtml
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:0 32px 24px;">
              <p style="margin:0 0 8px;font-size:11px;font-weight:700;color:#1B4B8A;text-transform:uppercase;letter-spacing:1.5px;">Documents joints</p>
              <p style="margin:0;font-size:13px;color:#6B7280;">CV, Permis, Carte FCO, FIMO — voir pièces jointes.</p>
            </td>
          </tr>

          <tr>
            <td style="background:#F1F5F9;padding:16px 32px;border-top:1px solid #E8EEF6;">
              <p style="margin:0;font-size:12px;color:#6B7280;">Reçu le $receivedAt · bns-transport.fr</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$apiKey = getenv('RESEND_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration serveur manquante.']);
    exit;
}

$payload = json_encode([
    'from'        => 'BNS Transport <contact@bns-transport.fr>',
    'to'          => ['recrutement@bns-transport.fr'],
    'reply_to'    => $email,
    'subject'     => 'Nouvelle candidature — ' . $nomComplet,
    'html'        => $html,
    'attachments' => $attachments,
]);

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
]);

$resBody  = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => "Échec de l'envoi. Veuillez réessayer."]);
}
