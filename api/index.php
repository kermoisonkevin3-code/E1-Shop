<?php
// ============================================================
// CRITÈRE 6 – API REST JSON – Échanges entre applications
// Fichier : E1/api/index.php
// Usage   : GET  /E1/api/?route=produits
//           GET  /E1/api/?route=produit&id=1
//           GET  /E1/api/?route=categories
//           GET  /E1/api/?route=stats
//           POST /E1/api/?route=prevente
// BTS SIO SLAM · KERMOISON Kevin · INGETIS Paris
// ============================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';

// Headers CORS + JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Helpers réponse ──
function ok(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data, 'timestamp' => date('c')],
                     JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg, 'code' => $code],
                     JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Auth API par token (critère sécurité) ──
function requireToken(): void {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h !== 'Bearer e1_api_secret_2026') err('Token invalide', 401);
}

$route  = sanitize($_GET['route'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

switch ($route) {

    // ── GET produits ──────────────────────────────────────
    case 'produits':
        if ($method !== 'GET') err('Méthode non autorisée', 405);
        $limit  = min(50, max(1, (int)($_GET['limit'] ?? 12)));
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $catId  = (int)($_GET['categorie'] ?? 0);

        $where  = ['p.actif = 1'];
        $params = [];
        if ($catId) { $where[] = 'p.categorie_id = ?'; $params[] = $catId; }
        $sql = "SELECT p.id, p.nom, p.prix, p.stock, p.stock_min,
                       p.image_url, c.nom AS categorie,
                       CASE WHEN p.stock = 0 THEN 'rupture'
                            WHEN p.stock < p.stock_min THEN 'faible'
                            ELSE 'ok' END AS statut_stock
                FROM produits p
                LEFT JOIN categories c ON c.id = p.categorie_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $total = $db->prepare("SELECT COUNT(*) FROM produits p WHERE " . implode(' AND ', $where));
        $total->execute($params);
        $totalRows = (int)$total->fetchColumn();

        ok(['produits' => $stmt->fetchAll(),
            'pagination' => ['page' => $page, 'limit' => $limit,
                             'total' => $totalRows,
                             'pages' => (int)ceil($totalRows / $limit)]]);

    // ── GET produit/:id ───────────────────────────────────
    case 'produit':
        if ($method !== 'GET') err('Méthode non autorisée', 405);
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err('Paramètre id requis', 400);
        $stmt = $db->prepare("
            SELECT p.*, c.nom AS categorie,
                   ROUND(AVG(a.note), 1) AS note_moy,
                   COUNT(DISTINCT a.id) AS nb_avis
            FROM produits p
            LEFT JOIN categories c ON c.id = p.categorie_id
            LEFT JOIN avis_produits a ON a.produit_id = p.id AND a.approuve = 1
            WHERE p.id = ? GROUP BY p.id");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) err('Produit introuvable', 404);
        ok(['produit' => $p]);

    // ── GET categories ────────────────────────────────────
    case 'categories':
        if ($method !== 'GET') err('Méthode non autorisée', 405);
        $cats = $db->query("SELECT c.*, COUNT(p.id) AS nb_produits
            FROM categories c
            LEFT JOIN produits p ON p.categorie_id = c.id AND p.actif = 1
            GROUP BY c.id ORDER BY c.nom")->fetchAll();
        ok(['categories' => $cats]);

    // ── GET stats ─────────────────────────────────────────
    case 'stats':
        if ($method !== 'GET') err('Méthode non autorisée', 405);
        ok(['stats' => [
            'produits_actifs'   => (int)$db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn(),
            'ruptures'          => (int)$db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn(),
            'stock_faible'      => (int)$db->query("SELECT COUNT(*) FROM produits WHERE stock>0 AND stock<stock_min AND actif=1")->fetchColumn(),
            'total_clients'     => (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
            'commandes_total'   => (int)$db->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
            'commandes_attente' => (int)$db->query("SELECT COUNT(*) FROM commandes WHERE statut='en_attente'")->fetchColumn(),
        ]]);

    // ── POST commande (authentifié) ───────────────────────
    case 'commande':
        if ($method !== 'POST') err('Méthode non autorisée', 405);
        requireToken();
        $body = json_decode(file_get_contents('php://input'), true);
        $clientId = (int)($body['client_id'] ?? 0);
        $produits = $body['produits'] ?? [];
        if (!$clientId || empty($produits)) err('client_id et produits requis', 400);

        $db->beginTransaction();
        try {
            $total = 0;
            foreach ($produits as $item) {
                $p = $db->prepare("SELECT prix, stock FROM produits WHERE id = ? AND actif = 1");
                $p->execute([$item['produit_id']]);
                $prod = $p->fetch();
                if (!$prod || $prod['stock'] < $item['quantite'])
                    throw new Exception("Stock insuffisant pour produit #{$item['produit_id']}");
                $total += $prod['prix'] * $item['quantite'];
            }
            $db->prepare("INSERT INTO commandes (client_id, total, statut) VALUES (?,?,'en_attente')")
               ->execute([$clientId, $total]);
            $cmdId = (int)$db->lastInsertId();
            foreach ($produits as $item) {
                $p = $db->prepare("SELECT prix FROM produits WHERE id = ?");
                $p->execute([$item['produit_id']]);
                $prix = $p->fetchColumn();
                $db->prepare("INSERT INTO commande_lignes (commande_id, produit_id, quantite, prix_unit) VALUES (?,?,?,?)")
                   ->execute([$cmdId, $item['produit_id'], $item['quantite'], $prix]);
                $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ?")
                   ->execute([$item['quantite'], $item['produit_id']]);
            }
            $db->commit();
            ok(['commande_id' => $cmdId, 'total' => $total], 201);
        } catch (Exception $e) {
            $db->rollBack();
            err($e->getMessage(), 422);
        }

    default:
        err("Route '$route' inconnue. Disponibles : produits, produit, categories, stats, commande", 404);
}
