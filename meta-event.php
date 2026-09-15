<?php
/**
 * Sample server-side Meta (Facebook) Conversions API–style event payload.
 *
 * This demo builds and validates a Purchase / Lead event shape similar to Meta CAPI.
 * It does not call Meta's live Graph API unless META_ACCESS_TOKEN + META_PIXEL_ID are set.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$email = strtolower(trim((string) ($input['email'] ?? '')));
$phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';
$eventName = (string) ($input['event_name'] ?? 'Lead');
$value = isset($input['value']) ? (float) $input['value'] : 0.0;
$currency = strtoupper((string) ($input['currency'] ?? 'USD'));
$eventId = (string) ($input['event_id'] ?? ('evt_' . bin2hex(random_bytes(8))));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Valid email is required for Meta user_data.']);
    exit;
}

$allowedEvents = ['Lead', 'Purchase', 'CompleteRegistration', 'AddToCart'];
if (!in_array($eventName, $allowedEvents, true)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'event_name must be one of: ' . implode(', ', $allowedEvents),
    ]);
    exit;
}

/** Meta expects hashed PII (SHA-256) for email/phone in user_data. */
$userData = [
    'em' => [hash('sha256', $email)],
];
if ($phone !== '') {
    $userData['ph'] = [hash('sha256', $phone)];
}

$customData = [
    'currency' => $currency,
    'value' => $value,
];

if (!empty($input['content_name'])) {
    $customData['content_name'] = (string) $input['content_name'];
}

$eventTime = time();
$metaPayload = [
    'data' => [
        [
            'event_name' => $eventName,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => (string) ($input['event_source_url'] ?? 'http://localhost:8080/'),
            'user_data' => $userData,
            'custom_data' => $customData,
        ],
    ],
];

$pixelId = getenv('META_PIXEL_ID') ?: '';
$accessToken = getenv('META_ACCESS_TOKEN') ?: '';
$sentToMeta = false;
$metaResponse = null;

if ($pixelId !== '' && $accessToken !== '') {
    $url = sprintf(
        'https://graph.facebook.com/v19.0/%s/events?access_token=%s',
        rawurlencode($pixelId),
        rawurlencode($accessToken)
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($metaPayload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $responseBody = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $sentToMeta = $curlError === '';
    $metaResponse = [
        'http_status' => $httpCode,
        'body' => $responseBody !== false ? json_decode($responseBody, true) : null,
        'curl_error' => $curlError !== '' ? $curlError : null,
    ];
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'message' => $sentToMeta
        ? 'Meta event payload built and POSTed to Graph API.'
        : 'Meta event payload built (dry-run). Set META_PIXEL_ID and META_ACCESS_TOKEN to send live.',
    'hashed' => [
        'email_sha256' => $userData['em'][0],
        'phone_sha256' => $userData['ph'][0] ?? null,
    ],
    'meta_payload' => $metaPayload,
    'meta_api' => $metaResponse,
]);
