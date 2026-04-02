<?php
// E1 – php/cart_action.php
// Gestion AJAX du panier (ajouter, retirer, modifier quantité)

require_once '../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$action  = $_POST['action'] ?? '';
$payload = $_POST['payload'] ?? '';

// Décodage payload (base64 JSON)
$data = null;
if ($payload) {
    $decoded = base64_decode(strtr($payload, '-_', '+/'));
    if ($decoded) $data = json_decode($decoded, true);
}

if (!$data || !isset($data['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$productId = (int)$data['product_id'];
$db = getDB();

// Vérification produit
$stmt = $db->prepare("SELECT id, stock FROM produits WHERE id = ? AND actif = 1");
$stmt->execute([$productId]);
$produit = $stmt->fetch();

if (!$produit) {
    echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
    exit;
}

$clientId = isLoggedIn() ? (int)$_SESSION['client_id'] : null;
$sessionId = session_id();

function getCartCount(PDO $db, ?int $clientId, string $sessionId): int {
    if ($clientId) {
        $s = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM panier WHERE client_id = ?");
        $s->execute([$clientId]);
    } else {
        $s = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM panier WHERE session_id = ? AND client_id IS NULL");
        $s->execute([$sessionId]);
    }
    return (int)$s->fetchColumn();
}

switch ($action) {
    case 'add':
        $qty = max(1, (int)($data['qty'] ?? 1));

        // Vérifier le stock
        if ($produit['stock'] < $qty) {
            echo json_encode(['success' => false, 'message' => 'Stock insuffisant']);
            exit;
        }

        // Vérifier si déjà dans le panier
        if ($clientId) {
            $check = $db->prepare("SELECT id, quantite FROM panier WHERE client_id = ? AND produit_id = ?");
            $check->execute([$clientId, $productId]);
        } else {
            $check = $db->prepare("SELECT id, quantite FROM panier WHERE session_id = ? AND produit_id = ? AND client_id IS NULL");
            $check->execute([$sessionId, $productId]);
        }
        $existing = $check->fetch();

        if ($existing) {
            $newQty = min($existing['quantite'] + $qty, $produit['stock']);
            $upd = $db->prepare("UPDATE panier SET quantite = ? WHERE id = ?");
            $upd->execute([$newQty, $existing['id']]);
        } else {
            if ($clientId) {
                $ins = $db->prepare("INSERT INTO panier (client_id, produit_id, quantite) VALUES (?,?,?)");
                $ins->execute([$clientId, $productId, $qty]);
            } else {
                $ins = $db->prepare("INSERT INTO panier (session_id, produit_id, quantite) VALUES (?,?,?)");
                $ins->execute([$sessionId, $productId, $qty]);
            }
        }

        echo json_encode(['success' => true, 'cart_count' => getCartCount($db, $clientId, $sessionId)]);
        break;

    case 'remove':
        if ($clientId) {
            $del = $db->prepare("DELETE FROM panier WHERE client_id = ? AND produit_id = ?");
            $del->execute([$clientId, $productId]);
        } else {
            $del = $db->prepare("DELETE FROM panier WHERE session_id = ? AND produit_id = ? AND client_id IS NULL");
            $del->execute([$sessionId, $productId]);
        }
        echo json_encode(['success' => true, 'cart_count' => getCartCount($db, $clientId, $sessionId)]);
        break;

    case 'update_qty':
        $delta = (int)($data['delta'] ?? 0);
        if ($clientId) {
            $check = $db->prepare("SELECT id, quantite FROM panier WHERE client_id = ? AND produit_id = ?");
            $check->execute([$clientId, $productId]);
        } else {
            $check = $db->prepare("SELECT id, quantite FROM panier WHERE session_id = ? AND produit_id = ? AND client_id IS NULL");
            $check->execute([$sessionId, $productId]);
        }
        $item = $check->fetch();
        if ($item) {
            $newQty = $item['quantite'] + $delta;
            if ($newQty <= 0) {
                $db->prepare("DELETE FROM panier WHERE id = ?")->execute([$item['id']]);
            } else {
                $newQty = min($newQty, $produit['stock']);
                $db->prepare("UPDATE panier SET quantite = ? WHERE id = ?")->execute([$newQty, $item['id']]);
            }
        }
        echo json_encode(['success' => true, 'cart_count' => getCartCount($db, $clientId, $sessionId)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}
