<?php
require_once 'includes/config.php';
startSession();
if (!isAdmin()) { setFlash('error', 'Accès refusé.'); redirect('index.php'); }

$db    = getDB();
$flash = getFlash();

// Stats rapides
$stats = [
    'produits'   => $db->query("SELECT COUNT(*) FROM produits WHERE actif=1")->fetchColumn(),
    'commandes'  => $db->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
    'clients'    => $db->query("SELECT COUNT(*) FROM clients WHERE role='client'")->fetchColumn(),
    'ca_total'   => $db->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE statut != 'annulee'")->fetchColumn(),
    'attente'    => $db->query("SELECT COUNT(*) FROM commandes WHERE statut='en_attente'")->fetchColumn(),
    'ruptures'   => $db->query("SELECT COUNT(*) FROM produits WHERE stock=0 AND actif=1")->fetchColumn(),
];

// Dernières commandes
$commandes = $db->query("
    SELECT c.*, CONCAT(cl.prenom,' ',cl.nom) as client_nom
    FROM commandes c JOIN clients cl ON c.client_id=cl.id
    ORDER BY c.created_at DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration – <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 – Critère 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-layout { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; }
        .admin-sidebar { background: var(--noir); padding: 32px 0; }
        .admin-sidebar .logo { display: block; padding: 0 24px 32px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .admin-menu a { display: block; padding: 12px 24px; color: #aaa; font-weight: 500; font-size: 0.9rem; transition: all 0.2s; }
        .admin-menu a:hover, .admin-menu a.active { background: rgba(200,169,110,0.15); color: var(--accent); }
        .admin-main { background: #f8f6f2; }
        .admin-header { background: white; padding: 20px 32px; border-bottom: 1px solid #ede9e2; display: flex; align-items: center; justify-content: space-between; }
        .admin-content { padding: 32px; }
        .stat-cards { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid #ede9e2; }
        .stat-num { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; }
        .stat-lbl { font-size: 0.8rem; color: var(--gris); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <a href="index.php" class="logo" style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:white">
            E<span style="color:var(--accent)">1</span>
        </a>
        <nav class="admin-menu" style="margin-top:16px">
            <a href="admin.php" class="active">📊 Dashboard</a>
            <a href="produits.php">📦 Produits</a>
            <a href="commandes.php">🧾 Commandes</a>
            <a href="index.php">🏪 Boutique</a>
            <a href="php/logout.php" style="margin-top:auto">🚪 Déconnexion</a>
        </nav>
    </aside>

    <div class="admin-main">
        <div class="admin-header">
            <h1 style="font-family:'Playfair Display',serif;font-size:1.4rem">Administration E1</h1>
            <span style="color:var(--gris);font-size:0.85rem"><?= date('d/m/Y H:i') ?></span>
        </div>

        <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>" style="margin:0"><?= sanitize($flash['message']) ?></div>
        <?php endif; ?>

        <div class="admin-content">
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-lbl">Produits actifs</div>
                    <div class="stat-num" style="color:var(--accent)"><?= $stats['produits'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">Clients inscrits</div>
                    <div class="stat-num"><?= $stats['clients'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">CA total</div>
                    <div class="stat-num" style="color:var(--succes)"><?= formatPrice($stats['ca_total']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">Commandes totales</div>
                    <div class="stat-num"><?= $stats['commandes'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">En attente</div>
                    <div class="stat-num" style="color:#e67e22"><?= $stats['attente'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-lbl">Ruptures de stock</div>
                    <div class="stat-num" style="color:var(--danger)"><?= $stats['ruptures'] ?></div>
                </div>
            </div>

            <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:16px">Dernières commandes</h2>
            <div style="background:white;border-radius:12px;border:1px solid #ede9e2;overflow:hidden">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $c): ?>
                        <tr>
                            <td><strong>#<?= $c['id'] ?></strong></td>
                            <td><?= sanitize($c['client_nom']) ?></td>
                            <td><strong><?= formatPrice($c['total']) ?></strong></td>
                            <td>
                                <?php
                                $cls = match($c['statut']) {
                                    'livree','expediee','confirmee' => 'badge-success',
                                    'annulee' => 'badge-danger',
                                    default => 'badge-warning'
                                };
                                ?>
                                <span class="badge <?= $cls ?>"><?= str_replace('_',' ',$c['statut']) ?></span>
                            </td>
                            <td style="color:var(--gris);font-size:0.85rem"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
