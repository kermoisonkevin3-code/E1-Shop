<?php
require_once 'includes/config.php';
startSession();
$db = getDB();

$clientId  = isLoggedIn() ? (int)$_SESSION['client_id'] : null;
$sessionId = session_id();

// Récupérer le panier
if ($clientId) {
    $stmt = $db->prepare("
        SELECT pa.*, p.nom, p.prix, p.stock, p.image_url
        FROM panier pa
        JOIN produits p ON pa.produit_id = p.id
        WHERE pa.client_id = ?
    ");
    $stmt->execute([$clientId]);
} else {
    $stmt = $db->prepare("
        SELECT pa.*, p.nom, p.prix, p.stock, p.image_url
        FROM panier pa
        JOIN produits p ON pa.produit_id = p.id
        WHERE pa.session_id = ? AND pa.client_id IS NULL
    ");
    $stmt->execute([$sessionId]);
}
$items = $stmt->fetchAll();

$total = array_sum(array_map(fn($i) => $i['prix'] * $i['quantite'], $items));
$flash = getFlash();

// Traitement commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['passer_commande'])) {
    if (!isLoggedIn()) { redirect('login.php'); }
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) { setFlash('error', 'Session invalide.'); redirect('panier.php'); }
    if (empty($items)) { setFlash('error', 'Votre panier est vide.'); redirect('panier.php'); }

    $db->beginTransaction();
    try {
        $adresse = sanitize($_POST['adresse'] ?? '');
        $ville   = sanitize($_POST['ville'] ?? '');
        $cp      = sanitize($_POST['code_postal'] ?? '');

        $ins = $db->prepare("INSERT INTO commandes (client_id, total, adresse_livraison, ville_livraison, code_postal_livraison) VALUES (?,?,?,?,?)");
        $ins->execute([$clientId, $total, $adresse, $ville, $cp]);
        $commandeId = $db->lastInsertId();

        foreach ($items as $item) {
            $db->prepare("INSERT INTO commande_lignes (commande_id, produit_id, quantite, prix_unit) VALUES (?,?,?,?)")
               ->execute([$commandeId, $item['produit_id'], $item['quantite'], $item['prix']]);
            $db->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?")
               ->execute([$item['quantite'], $item['produit_id'], $item['quantite']]);
        }

        $db->prepare("DELETE FROM panier WHERE client_id = ?")->execute([$clientId]);
        $db->commit();
        setFlash('success', "Commande #$commandeId passée avec succès ! Merci pour votre achat.");
        redirect('commandes.php');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Erreur lors du traitement de la commande.');
        redirect('panier.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Panier – <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 – Critère 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo">E<span>1</span></a>
        <div class="nav-actions">
            <?php if (isLoggedIn()): ?>
                <a href="compte.php" class="nav-link">👤 Mon compte</a>
                <a href="php/logout.php" class="nav-link">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
            <?php endif; ?>
            <a href="panier.php" class="nav-cart">🛒 <span class="cart-count"><?= count($items) ?></span></a>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="container" style="padding: 48px 24px">
    <h1 style="font-family:'Playfair Display',serif; font-size:2rem; margin-bottom:32px">Mon <em style="color:var(--accent)">Panier</em></h1>

    <?php if (empty($items)): ?>
        <div style="text-align:center; padding:80px 0">
            <p style="font-size:4rem">🛒</p>
            <p style="font-size:1.3rem; margin:16px 0; color:var(--gris)">Votre panier est vide</p>
            <a href="produits.php" class="btn-hero" style="display:inline-block; margin-top:8px">Voir les produits</a>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:1fr 360px; gap:40px; align-items:start">
            <div>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Sous-total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:12px">
                                    <div style="width:60px;height:60px;background:var(--gris-clair);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
                                        <?= $item['image_url'] ? '<img src="'.sanitize($item['image_url']).'" style="width:100%;height:100%;object-fit:cover;border-radius:8px">' : '📦' ?>
                                    </div>
                                    <strong><?= sanitize($item['nom']) ?></strong>
                                </div>
                            </td>
                            <td><?= formatPrice($item['prix']) ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px">
                                    <button onclick="updateQty(<?= $item['produit_id'] ?>, -1)" style="width:28px;height:28px;border:1px solid #ddd;border-radius:6px;background:white;cursor:pointer;font-size:1rem">−</button>
                                    <span style="min-width:24px;text-align:center;font-weight:600"><?= $item['quantite'] ?></span>
                                    <button onclick="updateQty(<?= $item['produit_id'] ?>, 1)" style="width:28px;height:28px;border:1px solid #ddd;border-radius:6px;background:white;cursor:pointer;font-size:1rem">+</button>
                                </div>
                            </td>
                            <td><strong><?= formatPrice($item['prix'] * $item['quantite']) ?></strong></td>
                            <td>
                                <button onclick="removeFromCart(<?= $item['produit_id'] ?>)"
                                    style="border:none;background:none;color:var(--danger);cursor:pointer;font-size:1.1rem" title="Supprimer">✕</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Résumé commande -->
            <div class="cart-summary">
                <h3 style="font-family:'Playfair Display',serif; font-size:1.3rem; margin-bottom:20px">Récapitulatif</h3>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;color:var(--gris)">
                    <span>Sous-total</span><span><?= formatPrice($total) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;color:var(--gris)">
                    <span>Livraison</span><span style="color:var(--succes)">Gratuite</span>
                </div>
                <hr style="border:none;border-top:1px solid #ddd;margin:16px 0">
                <div style="display:flex;justify-content:space-between;margin-bottom:24px">
                    <span style="font-weight:700">Total</span>
                    <span class="cart-total"><?= formatPrice($total) ?></span>
                </div>

                <?php if (isLoggedIn()): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                    <h4 style="margin-bottom:12px">Adresse de livraison</h4>
                    <div class="form-group">
                        <input type="text" name="adresse" placeholder="Adresse" required style="margin-bottom:8px">
                    </div>
                    <div style="display:grid;grid-template-columns:120px 1fr;gap:8px;margin-bottom:16px">
                        <input class="form-group" type="text" name="code_postal" placeholder="Code postal" required style="padding:10px 12px;border:1.5px solid #e0dcd5;border-radius:8px;font-family:inherit">
                        <input class="form-group" type="text" name="ville" placeholder="Ville" required style="padding:10px 12px;border:1.5px solid #e0dcd5;border-radius:8px;font-family:inherit">
                    </div>
                    <button type="submit" name="passer_commande" class="btn-primary">Passer la commande</button>
                </form>
                <?php else: ?>
                <a href="login.php" class="btn-primary" style="display:block;text-align:center">Se connecter pour commander</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
