# GroupyClient – Plateforme d'achats groupés (PHP)

> **BTS SIO SLAM – Session 2026 · KERMOISON Kevin · INGETIS Paris**
> Projet léger (client PHP) · Alternance Lexramax Inc – Nanterre

---

## 📋 Description

GroupyClient est une plateforme web d'achats groupés développée en PHP natif.
Elle permet à des clients de rejoindre des préventes : si le seuil d'acheteurs minimum
est atteint avant la date limite, la vente est confirmée et une facture est générée automatiquement.

La base de données **ecommerce_db** est partagée avec **GroupyVendeur** (C# ASP.NET Core 8),
permettant une synchronisation en temps réel sans API intermédiaire.

---

## 🛠 Stack technologique

| Couche | Technologie |
|--------|------------|
| Backend | PHP 8.1+ natif (PDO, sessions, BCrypt) |
| Frontend | HTML5, CSS3, JavaScript ES6+, **Bootstrap 5.3** |
| Base de données | MySQL 8 / MariaDB |
| Framework CSS | Bootstrap 5.3 + Bootstrap Icons |
| Serveur local | Apache (WAMP 3.3) |
| IDE | Visual Studio Code |
| Tests | phpMyAdmin, Postman (API REST) |
| Versioning | Git / GitHub |

---

## 🚀 Installation locale

### Prérequis
- WAMP 3.3+ (Apache + MySQL + PHP 8.1)
- PHP extensions : `pdo_mysql`, `mbstring`, `json`

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/kermoisonkevin/GroupyClient.git
cd GroupyClient

# 2. Copier dans le dossier WAMP
cp -r . C:/wamp64/www/GroupyClient/

# 3. Importer la base de données
# Ouvrir phpMyAdmin → Importer → ecommerce_db.sql

# 4. Configurer la connexion BDD
# Éditer includes/config.php :
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'root');
define('DB_PASS', '');

# 5. Lancer le site
# Ouvrir : http://localhost/GroupyClient
```

---

## 🔐 Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | admin@e1.com | Admin1234! |
| Client | jean.dupont@demo.com | Client1234! |

---

## 📁 Structure du projet

```
GroupyClient/
├── index.php                 # Accueil : hero, catégories, préventes
├── produits.php              # Catalogue avec filtres et tri
├── produits_bootstrap.php    # Catalogue version Bootstrap 5
├── produit.php               # Fiche produit + participation
├── login.php                 # Connexion multi-rôles
├── register.php              # Inscription client/vendeur
├── mes-preventes.php         # Tableau de bord client
├── compte.php                # Profil et paramètres
├── admin.php                 # Panel administrateur
│
├── api/
│   └── index.php             # API REST JSON (Critère 6)
│
├── includes/
│   ├── config.php            # BDD, helpers, sécurité, clôture auto
│   ├── bootstrap_header.php  # En-tête Bootstrap 5
│   └── bootstrap_footer.php  # Pied de page Bootstrap 5
│
├── php/
│   ├── cart_action.php       # API AJAX panier
│   ├── search_suggest.php    # Autocomplete AJAX
│   └── logout.php            # Déconnexion
│
├── css/
│   └── style.css             # Styles personnalisés (design doré)
│
├── js/
│   └── main.js               # JavaScript (AJAX, animations, toast)
│
└── ecommerce_db.sql          # Schéma BDD + données de démo
```

---

## 🔗 API REST (Critère 6)

L'application expose une API REST JSON :

```bash
# Liste des préventes actives
GET http://localhost/GroupyClient/api/?route=produits

# Détail d'un produit
GET http://localhost/GroupyClient/api/?route=produit&id=1

# Catégories
GET http://localhost/GroupyClient/api/?route=categories

# Statistiques
GET http://localhost/GroupyClient/api/?route=stats

# Inscription à une prévente (authentifié)
POST http://localhost/GroupyClient/api/?route=prevente
Authorization: Bearer groupy_api_token_2026
Content-Type: application/json
{ "client_id": 1, "produit_id": 2 }
```

---

## 🛡 Sécurité (Critère 7)

- **BCrypt cost=12** – Hachage des mots de passe (`password_hash`)
- **Tokens CSRF** – `bin2hex(random_bytes(32))` sur tous les formulaires POST
- **Requêtes PDO préparées** – Protection anti-injection SQL
- **Sessions sécurisées** – `httponly`, `samesite=Strict`, `session_regenerate_id()`
- **XSS** – `htmlspecialchars()` sur toutes les sorties

---

## 🧪 Tests (Critère 9)

Voir le fichier [`TESTS.md`](TESTS.md) pour le tableau de recette complet.

```
Tests fonctionnels  : 15/15 ✅
Tests sécurité      :  5/5  ✅
Tests régression    :  5/5  ✅
```

---

## 📖 Documentation (Critère 10)

- [`CDC_E1_Ecommerce.docx`](docs/CDC_E1_Ecommerce.docx) – Cahier des charges
- [`Manuel_E1_Ecommerce.docx`](docs/Manuel_E1_Ecommerce.docx) – Manuel utilisateur
- [`CHANGELOG.md`](CHANGELOG.md) – Historique des versions

---

## 🔄 Lien avec GroupyVendeur (AG1)

Les deux projets **partagent la même base de données** `ecommerce_db` :

```
GroupyClient (PHP)          ecommerce_db (MySQL)          GroupyVendeur (C#)
     Client ──────────────── commandes ─────────────────── Vendeur
  passe commande             produits                  traite commande
                             mouvements_stock          met à jour stock
```

---

## 👤 Auteur

**Kevin Kermoison** – Étudiant BTS SIO SLAM  
INGETIS Paris · Alternance Lexramax Inc · Session 2026  
GitHub : [@kermoisonkevin](https://github.com/kermoisonkevin)
