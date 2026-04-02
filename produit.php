<?php
require_once 'includes/config.php';
startSession();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect('produits.php'); }

$stmt = $db->prepare("
    SELECT p.*, c.nom as categorie
    FROM produits p
    LEFT JOIN categories c ON p.categorie_id = c.id
    WHERE p.id = ? AND p.actif = 1
");
$stmt->execute([$id]);
$produit = $stmt->fetch();
if (!$produit) { setFlash('error', 'Produit introuvable.'); redirect('produits.php'); }

// Avis
$avisStmt = $db->prepare("
    SELECT a.*, CONCAT(c.prenom, ' ', c.nom) as client_nom
    FROM avis_produits a
    JOIN clients c ON a.client_id = c.id
    WHERE a.produit_id = ? AND a.approuve = 1
    ORDER BY a.created_at DESC
");
$avisStmt->execute([$id]);
$avis = $avisStmt->fetchAll();

$moyenneNote = count($avis) > 0 ? round(array_sum(array_column($avis, 'note')) / count($avis), 1) : 0;

// Produits similaires
$similStmt = $db->prepare("
    SELECT * FROM produits
    WHERE categorie_id = ? AND id != ? AND actif = 1
    LIMIT 4
");
$similStmt->execute([$produit['categorie_id'], $id]);
$similaires = $similStmt->fetchAll();

// Traitement avis
$avisErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['avis_submit'])) {
    if (!isLoggedIn()) {
        setFlash('error', 'Connectez-vous pour laisser un avis.');
        redirect("produit.php?id=$id");
    }
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $avisErrors[] = 'Session invalide.';
    } else {
        $note = (int)($_POST['note'] ?? 0);
        $commentaire = sanitize($_POST['commentaire'] ?? '');
        if ($note < 1 || $note > 5) $avisErrors[] = 'Note invalide.';
        if (strlen($commentaire) < 5) $avisErrors[] = 'Commentaire trop court.';
        if (empty($avisErrors)) {
            $ins = $db->prepare("INSERT INTO avis_produits (produit_id, client_id, note, commentaire) VALUES (?,?,?,?)");
            $ins->execute([$id, $_SESSION['client_id'], $note, $commentaire]);
            setFlash('success', 'Votre avis a été soumis et sera publié après modération.');
            redirect("produit.php?id=$id");
        }
    }
}

$flash = getFlash();
$panierCount = 0;
if (isLoggedIn()) {
    $s = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM panier WHERE client_id = ?");
    $s->execute([$_SESSION['client_id']]);
    $panierCount = (int)$s->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($produit['nom']) ?> – <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 – Critère 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .product-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; padding: 56px 0; }
        .product-image-big { aspect-ratio: 1; background: var(--gris-clair); border-radius: 20px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-image-big img { width: 100%; height: 100%; object-fit: cover; }
        .product-image-placeholder { font-size: 8rem; opacity: 0.2; }
        .stars { color: #f5a623; font-size: 1.2rem; letter-spacing: 2px; }
        .stars-empty { color: #ddd; }
        .qty-selector { display: flex; align-items: center; gap: 12px; }
        .qty-btn { width: 40px; height: 40px; border: 1.5px solid #ddd; border-radius: 10px; background: white; font-size: 1.2rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .qty-btn:hover { border-color: var(--accent); }
        .qty-input { width: 60px; text-align: center; border: 1.5px solid #ddd; border-radius: 10px; padding: 10px; font-size: 1rem; font-family: inherit; }
        .avis-card { background: var(--gris-clair); border-radius: var(--radius); padding: 20px; margin-bottom: 16px; }
        .avis-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .avis-stars { color: #f5a623; font-size: 0.9rem; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo">E<span>1</span></a>
        <div class="nav-search">
            <form action="produits.php" method="GET">
                <input type="text" name="q" placeholder="Rechercher…">
                <button type="submit">🔍</button>
            </form>
        </div>
        <div class="nav-actions">
            <?php if (isLoggedIn()): ?>
                <a href="compte.php" class="nav-link">👤 Mon compte</a>
                <a href="panier.php" class="nav-cart">🛒 <span class="cart-count"><?= $panierCount ?></span></a>
                <a href="php/logout.php" class="nav-link">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
                <a href="panier.php" class="nav-cart">🛒</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="container">
    <!-- Fil d'Ariane -->
    <nav style="padding:20px 0;font-size:0.85rem;color:var(--gris)">
        <a href="index.php" style="color:var(--gris)">Accueil</a> › 
        <a href="produits.php" style="color:var(--gris)">Produits</a> › 
        <?php if ($produit['categorie']): ?>
        <a href="produits.php?categorie=<?= $produit['categorie_id'] ?>" style="color:var(--gris)"><?= sanitize($produit['categorie']) ?></a> › 
        <?php endif; ?>
        <span><?= sanitize($produit['nom']) ?></span>
    </nav>

    <div class="product-detail">
        <!-- Image -->
        <div class="product-image-big">
            <?php if ($produit['image_url']): ?>
                <img src="<?= sanitize($produit['image_url']) ?>" alt="<?= sanitize($produit['nom']) ?>">
            <?php else: ?>
                <div class="product-image-placeholder">📦</div>
            <?php endif; ?>
        </div>

        <!-- Infos -->
        <div>
            <span class="product-cat" style="font-size:0.8rem;color:var(--gris);text-transform:uppercase;letter-spacing:1px">
                <?= sanitize($produit['categorie'] ?? '') ?>
            </span>
            <h1 style="font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;margin:10px 0 12px">
                <?= sanitize($produit['nom']) ?>
            </h1>

            <!-- Notes -->
            <?php if ($moyenneNote > 0): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                <span class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?= $i <= $moyenneNote ? '★' : '☆' ?>
                    <?php endfor; ?>
                </span>
                <span style="font-size:0.9rem;color:var(--gris)"><?= $moyenneNote ?>/5 (<?= count($avis) ?> avis)</span>
            </div>
            <?php endif; ?>

            <!-- Prix -->
            <div style="font-family:'Playfair Display',serif;font-size:2.4rem;font-weight:700;color:var(--accent);margin-bottom:20px">
                <?= formatPrice($produit['prix']) ?>
            </div>

            <!-- Stock -->
            <div style="margin-bottom:24px">
                <?php if ($produit['stock'] > $produit['stock_min']): ?>
                    <span style="color:var(--succes);font-weight:600">✓ En stock (<?= $produit['stock'] ?> disponibles)</span>
                <?php elseif ($produit['stock'] > 0): ?>
                    <span style="color:#e67e22;font-weight:600">⚠ Stock limité (<?= $produit['stock'] ?> restants)</span>
                <?php else: ?>
                    <span style="color:var(--danger);font-weight:600">✕ Rupture de stock</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if ($produit['description']): ?>
            <p style="color:var(--gris);line-height:1.7;margin-bottom:28px"><?= sanitize($produit['description']) ?></p>
            <?php endif; ?>

            <!-- Ref -->
            <div style="font-size:0.82rem;color:#aaa;margin-bottom:24px">
                Réf. : <?= sanitize($produit['reference'] ?? 'N/A') ?>
            </div>

            <!-- Ajout panier -->
            <?php if ($produit['stock'] > 0): ?>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <div class="qty-selector">
                    <button class="qty-btn" onclick="changeQty(-1)">−</button>
                    <input class="qty-input" type="number" id="qty" value="1" min="1" max="<?= $produit['stock'] ?>">
                    <button class="qty-btn" onclick="changeQty(1)">+</button>
                </div>
                <button class="btn-primary" style="padding:12px 32px;border-radius:50px;flex:1"
                    onclick="addToCartWithQty(<?= $produit['id'] ?>)">
                    Ajouter au panier
                </button>
            </div>
            <?php else: ?>
            <button class="btn-primary" disabled style="padding:12px 32px;border-radius:50px;opacity:0.5;cursor:not-allowed">
                Rupture de stock
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- AVIS -->
    <section style="padding:60px 0;border-top:1px solid #ede9e2">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;margin-bottom:32px">
            Avis clients <em style="color:var(--accent)">(<?= count($avis) ?>)</em>
        </h2>

        <div style="display:grid;grid-template-columns:1fr 400px;gap:40px">
            <!-- Liste avis -->
            <div>
                <?php if (empty($avis)): ?>
                    <p style="color:var(--gris)">Aucun avis pour le moment. Soyez le premier !</p>
                <?php else: ?>
                    <?php foreach ($avis as $a): ?>
                    <div class="avis-card">
                        <div class="avis-header">
                            <strong><?= sanitize($a['client_nom']) ?></strong>
                            <span class="avis-stars"><?= str_repeat('★', $a['note']) ?><?= str_repeat('☆', 5 - $a['note']) ?></span>
                            <span style="font-size:0.78rem;color:var(--gris);margin-left:auto"><?= date('d/m/Y', strtotime($a['created_at'])) ?></span>
                        </div>
                        <p style="color:var(--gris);font-size:0.95rem"><?= sanitize($a['commentaire']) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Formulaire avis -->
            <div>
                <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:20px">Laisser un avis</h3>
                <?php if (!isLoggedIn()): ?>
                    <p style="color:var(--gris)">
                        <a href="login.php" style="color:var(--accent)">Connectez-vous</a> pour laisser un avis.
                    </p>
                <?php else: ?>
                    <?php foreach ($avisErrors as $e): ?>
                        <div class="flash flash-error"><?= sanitize($e) ?></div>
                    <?php endforeach; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                        <div class="form-group" style="margin-bottom:16px">
                            <label>Note</label>
                            <div style="display:flex;gap:8px;margin-top:6px">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor:pointer;font-size:1.5rem;color:#ddd" id="star-<?= $i ?>">
                                    <input type="radio" name="note" value="<?= $i ?>" required style="display:none"
                                           onchange="highlightStars(<?= $i ?>)"> ★
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:16px">
                            <label>Commentaire</label>
                            <textarea name="commentaire" required placeholder="Votre expérience avec ce produit…"
                                      style="min-height:100px"></textarea>
                        </div>
                        <button type="submit" name="avis_submit" class="btn-primary">Publier l'avis</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Produits similaires -->
    <?php if (!empty($similaires)): ?>
    <section style="padding:0 0 60px">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;margin-bottom:32px">
            Produits <em style="color:var(--accent)">similaires</em>
        </h2>
        <div class="products-grid" style="grid-template-columns:repeat(4,1fr)">
            <?php foreach ($similaires as $p): ?>
            <article class="product-card">
                <div class="product-img">
                    <?php if ($p['image_url']): ?>
                        <img src="<?= sanitize($p['image_url']) ?>" alt="<?= sanitize($p['nom']) ?>">
                    <?php else: ?>
                        <div class="img-placeholder">📦</div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3><a href="produit.php?id=<?= $p['id'] ?>"><?= sanitize($p['nom']) ?></a></h3>
                    <div class="product-footer">
                        <span class="price"><?= formatPrice($p['prix']) ?></span>
                        <button class="btn-add" onclick="addToCart(<?= $p['id'] ?>)">+ Panier</button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<footer class="footer">
    <div class="footer-bottom">© 2025 E1 Shop</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
function changeQty(delta) {
    const input = document.getElementById('qty');
    const max = parseInt(input.max);
    input.value = Math.max(1, Math.min(max, parseInt(input.value) + delta));
}
function addToCartWithQty(productId) {
    const qty = parseInt(document.getElementById('qty').value);
    addToCart(productId, qty);
}
function highlightStars(n) {
    for (let i = 1; i <= 5; i++) {
        document.getElementById('star-' + i).style.color = i <= n ? '#f5a623' : '#ddd';
    }
}
</script>
</body>
</html>
