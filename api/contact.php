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
$nom     = trim($_POST['nom'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$nom || !$email || !$message) {
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
$entreprise = trim($_POST['entreprise'] ?? '');
$telephone  = trim($_POST['telephone'] ?? '');
$service    = trim($_POST['service'] ?? '');

$servicesLabels = [
    'frigo'   => 'Transport frigorifique',
    'fret'    => 'Transport de fret',
    'express' => 'Livraison express',
    'longue'  => 'Longue distance',
    'autre'   => 'Autre demande',
];
$serviceLabel = $servicesLabels[$service] ?? $service;

// Sujet
$subject = 'Nouveau devis BNS Transport';
if ($serviceLabel) {
    $subject .= ' — ' . $serviceLabel;
}
$subject .= ' — ' . s($nom);

// Tableau des champs
$rows = [
    ['Nom', s($nom)],
    ['Email', s($email)],
];
if ($entreprise) $rows[] = ['Entreprise',  s($entreprise)];
if ($telephone)  $rows[] = ['Téléphone',   s($telephone)];
if ($serviceLabel) $rows[] = ['Prestation', s($serviceLabel)];

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

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Nouveau devis BNS Transport</title></head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:Inter,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC;padding:40px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

          <tr>
            <td style="background:#1B4B8A;padding:28px 32px;">
              <p style="margin:0;color:#ffffff;font-size:11px;font-weight:600;letter-spacing:2px;text-transform:uppercase;opacity:0.6;">BNS Transport</p>
              <h1 style="margin:6px 0 0;color:#ffffff;font-size:22px;font-weight:700;">Nouvelle demande de devis</h1>
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
              <p style="margin:0 0 10px;font-size:11px;font-weight:700;color:#1B4B8A;text-transform:uppercase;letter-spacing:1.5px;">Message</p>
              <div style="background:#F8FAFC;border-left:3px solid #1B4B8A;padding:16px 20px;border-radius:0 6px 6px 0;color:#1e293b;font-size:14px;line-height:1.8;">
                $messageHtml
              </div>
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
    'from'     => 'BNS Transport <contact@bns-transport.fr>',
    'to'       => ['contact@bns-transport.fr'],
    'reply_to' => $email,
    'subject'  => $subject,
    'html'     => $html,
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
    CURLOPT_TIMEOUT => 10,
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
