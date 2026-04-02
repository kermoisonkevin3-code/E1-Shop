<?php
// php/search_suggest.php
require_once '../includes/config.php';
header('Content-Type: application/json');

$q = sanitize($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode(['results' => []]); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT id, nom FROM produits WHERE nom LIKE ? AND actif = 1 LIMIT 6");
$stmt->execute(["%$q%"]);
echo json_encode(['results' => $stmt->fetchAll()]);
