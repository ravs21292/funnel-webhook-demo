<?php
/**
 * Example Stripe webhook signature verification (demo).
 *
 * In production you would use the official Stripe PHP SDK:
 *   \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret)
 *
 * This file shows the HMAC-SHA256 pattern Stripe documents, without a live secret.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

/** Demo secret — override with STRIPE_WEBHOOK_SECRET env var in Docker. */
$secret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_demo_replace_me';

/**
 * Parse Stripe-Signature header: t=timestamp,v1=signature[,v1=...]
 */
function parseStripeSignature(string $header): array
{
    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $header) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $part, 2);
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1') {
            $signatures[] = $value;
        }
    }

    return [$timestamp, $signatures];
}

[$timestamp, $signatures] = parseStripeSignature($sigHeader);

if ($timestamp === null || $signatures === []) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing or malformed Stripe-Signature header.',
        'hint' => 'Send header: Stripe-Signature: t=<unix>,v1=<hmac>',
    ]);
    exit;
}

$tolerance = 300; // 5 minutes
$now = time();
if (abs($now - (int) $timestamp) > $tolerance) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Timestamp outside tolerance window.']);
    exit;
}

$signedPayload = $timestamp . '.' . $payload;
$expected = hash_hmac('sha256', $signedPayload, $secret);

$valid = false;
foreach ($signatures as $sig) {
    if (hash_equals($expected, $sig)) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Signature verification failed.',
        'demo' => [
            'how_to_sign' => 'HMAC-SHA256 of "{timestamp}.{raw_body}" with your webhook secret',
            'secret_source' => 'STRIPE_WEBHOOK_SECRET env or default whsec_demo_replace_me',
        ],
    ]);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON event body.']);
    exit;
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'message' => 'Stripe signature verified (demo).',
    'event_type' => $event['type'] ?? null,
    'event_id' => $event['id'] ?? null,
]);
