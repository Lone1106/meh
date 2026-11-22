<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=60');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Only GET is supported.']);
    exit;
}

function clamped_int_param(string $name, int $default, int $min, int $max): int
{
    if (!isset($_GET[$name])) {
        return $default;
    }
    $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }
    return max($min, min($max, $value));
}

$limit = clamped_int_param('limit', 20, 1, 100);
$offset = clamped_int_param('offset', 0, 0, 1000000);

$pdo = get_db();

$stmt = $pdo->prepare(
    'SELECT id, name, rating, comment, relationship, created_at
     FROM feedback ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'rating' => (int) $row['rating'],
        'comment' => $row['comment'],
        'relationship' => $row['relationship'],
        'relationship_label' => RELATIONSHIPS[$row['relationship']] ?? RELATIONSHIPS['sonstiges'],
        'created_at' => gmdate('c', strtotime((string) $row['created_at'])),
    ];
}, $rows);

$statsRow = $pdo->query('SELECT COUNT(*) AS n, AVG(rating) AS avg_rating FROM feedback')->fetch(PDO::FETCH_ASSOC);
$count = (int) $statsRow['n'];
$average = $count > 0 ? round((float) $statsRow['avg_rating'], 1) : null;

echo json_encode([
    'count' => $count,
    'average' => $average,
    'limit' => $limit,
    'offset' => $offset,
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
