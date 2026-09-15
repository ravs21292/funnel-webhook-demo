<?php
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
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$errors = [];

$lead = $data['lead'] ?? null;
$order = $data['order'] ?? null;

if (!is_array($lead)) {
    $errors[] = 'Missing lead object.';
} else {
    $name = trim((string) ($lead['name'] ?? ''));
    $email = trim((string) ($lead['email'] ?? ''));

    if (strlen($name) < 2) {
        $errors[] = 'lead.name must be at least 2 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'lead.email is invalid.';
    }

    if (isset($lead['phone']) && $lead['phone'] !== null && $lead['phone'] !== '') {
        $digits = preg_replace('/\D+/', '', (string) $lead['phone']);
        if ($digits === null || strlen($digits) < 7 || strlen($digits) > 15) {
            $errors[] = 'lead.phone must contain 7–15 digits.';
        }
    }
}

$allowedProducts = ['starter' => 29, 'growth' => 79, 'pro' => 149];

if (!is_array($order)) {
    $errors[] = 'Missing order object.';
} else {
    $product = (string) ($order['product'] ?? '');
    if (!isset($allowedProducts[$product])) {
        $errors[] = 'order.product is not a known SKU.';
    } else {
        $amount = $order['amount'] ?? null;
        if (!is_numeric($amount) || (float) $amount !== (float) $allowedProducts[$product]) {
            $errors[] = 'order.amount does not match the selected product.';
        }
    }
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

$leadId = 'lead_' . bin2hex(random_bytes(6));
$receivedAt = gmdate('c');

$logDir = __DIR__ . '/storage';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

$record = [
    'id' => $leadId,
    'received_at' => $receivedAt,
    'payload' => $data,
];

file_put_contents(
    $logDir . '/leads.jsonl',
    json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

http_response_code(201);
echo json_encode([
    'ok' => true,
    'message' => 'Lead accepted by webhook.',
    'lead_id' => $leadId,
    'received_at' => $receivedAt,
    'echo' => [
        'event' => $data['event'] ?? null,
        'email' => $lead['email'] ?? null,
        'product' => $order['product'] ?? null,
        'amount' => $order['amount'] ?? null,
    ],
]);
