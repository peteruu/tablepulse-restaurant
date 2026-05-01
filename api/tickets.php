<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storagePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tickets.json';

function readTickets(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function writeTickets(string $path, array $tickets): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $encoded = json_encode(array_values($tickets), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        respond(['error' => 'Unable to write ticket storage'], 500);
    }
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cleanString(mixed $value, int $limit = 500): string
{
    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return mb_substr($text, 0, $limit);
}

$method = $_SERVER['REQUEST_METHOD'];
$tickets = readTickets($storagePath);

if ($method === 'GET') {
    usort($tickets, fn ($a, $b) => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));
    respond(['tickets' => $tickets]);
}

if ($method === 'POST') {
    $input = body();
    $allowedTypes = ['service', 'payment', 'cleaning', 'order_issue', 'feedback'];
    $type = cleanString($input['type'] ?? 'service', 40);

    if (!in_array($type, $allowedTypes, true)) {
        respond(['error' => 'Invalid ticket type'], 422);
    }

    $ticket = [
        'id' => cleanString($input['id'] ?? bin2hex(random_bytes(8)), 80),
        'table' => cleanString($input['table'] ?? '1', 20),
        'type' => $type,
        'message' => cleanString($input['message'] ?? '', 500),
        'status' => 'new',
        'createdAt' => cleanString($input['createdAt'] ?? gmdate('c'), 40),
        'updatedAt' => gmdate('c'),
    ];

    array_unshift($tickets, $ticket);
    writeTickets($storagePath, $tickets);
    respond(['ticket' => $ticket], 201);
}

if ($method === 'PATCH') {
    $id = cleanString($_GET['id'] ?? '', 80);
    $input = body();
    $status = cleanString($input['status'] ?? '', 20);
    $allowedStatuses = ['new', 'doing', 'done'];

    if ($id === '' || !in_array($status, $allowedStatuses, true)) {
        respond(['error' => 'Invalid id or status'], 422);
    }

    $found = false;
    foreach ($tickets as &$ticket) {
        if (($ticket['id'] ?? '') === $id) {
            $ticket['status'] = $status;
            $ticket['updatedAt'] = gmdate('c');
            $found = true;
            break;
        }
    }
    unset($ticket);

    if (!$found) {
        respond(['error' => 'Ticket not found'], 404);
    }

    writeTickets($storagePath, $tickets);
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
