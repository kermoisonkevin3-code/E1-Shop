<?php
require_once 'includes/config.php';
startSession();

if (isLoggedIn()) redirect('index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session invalide.';
    } else {
        // [PARRAINAGE] Récupération code parrainage optionnel
        $codeParrainage = sanitize($_POST['code_parrainage'] ?? '');
        $nom    = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $mdp    = $_POST['password'] ?? '';
        $mdp2   = $_POST['password2'] ?? '';

        if (strlen($nom) < 2)  $errors[] = 'Nom trop court.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if (strlen($mdp) < 8)  $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        if ($mdp !== $mdp2)    $errors[] = 'Les mots de passe ne correspondent pas.';

        if (empty($errors)) {
            $db = getDB();
            $check = $db->prepare("SELECT id FROM clients WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'Cet email est déjà utilisé.';
            } else {
                $hash = password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12]);
                $ins  = $db->prepare("INSERT INTO clients (nom, prenom, email, mot_de_passe) VALUES (?,?,?,?)");
                $ins->execute([$nom, $prenom, $email, $hash]);
                $id = $db->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['client_id'] = $id;
                $_SESSION['role']      = 'client';
                $_SESSION['nom']       = $nom;

                // [CRITÈRE 18] Log de la création de compte
                logConnexion($id, 'nouveau_client');

                // [PARRAINAGE – CRITÈRE E6] Lier le filleul au parrain si code fourni
                if (!empty($codeParrainage)) {
                    $checkCode = $db->prepare("SELECT id, parrain_id FROM parrainages WHERE code_parrainage = ? AND filleul_id IS NULL LIMIT 1");
                    $checkCode->execute([$codeParrainage]);
                    $parrainage = $checkCode->fetch();
                    if ($parrainage && $parrainage['parrain_id'] != $id) {
                        // Lier le filleul
                        $db->prepare("UPDATE parrainages SET filleul_id = ? WHERE id = ?")
                           ->execute([$id, $parrainage['id']]);
                        setFlash('success', 'Compte créé ! Vous avez été parrainé avec succès 🎁');
                    } else {
                        setFlash('success', 'Compte créé ! Bienvenue ' . $prenom . ' !');
                    }
                } else {
                    setFlash('success', 'Compte créé ! Bienvenue ' . $prenom . ' !');
                }
                redirect('index.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription – <?= SITE_NAME ?></title>
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
    </div>
</nav>

<div class="form-container">
    <h1 class="form-title">Créer un compte</h1>
    <?php foreach ($errors as $e): ?>
        <div class="flash flash-error"><?= sanitize($e) ?></div>
    <?php endforeach; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
            <div class="form-group">
                <label>Nom</label>
                <input type="text" name="nom" required value="<?= sanitize($_POST['nom'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" name="prenom" value="<?= sanitize($_POST['prenom'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Mot de passe <small style="color:#aaa">(8 caractères min)</small></label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Confirmer le mot de passe</label>
            <input type="password" name="password2" required>
        </div>
        <!-- [PARRAINAGE] Champ optionnel – Critère E6 -->
        <div class="form-group" style="margin-top:12px">
            <label>Code parrainage <span style="color:#aaa;font-weight:400">(optionnel)</span></label>
            <input type="text" name="code_parrainage"
                   placeholder="Code d'un ami pour être parrainé"
                   value="<?= sanitize($codeParrainage ?? '') ?>"
                   maxlength="64"
                   style="font-family:monospace;letter-spacing:2px">
            <small style="color:#888;font-size:0.78rem">
                Votre parrain recevra une remise de 10€ lors de votre premier achat 🎁
            </small>
        </div>
        <button type="submit" class="btn-primary">Créer mon compte</button>
    </form>
    <p style="text-align:center; margin-top:20px; font-size:0.9rem; color:#888">
        Déjà inscrit ? <a href="login.php" style="color:#c8a96e; font-weight:600">Se connecter</a>
    </p>
</div>
</body>
</html>
