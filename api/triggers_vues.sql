-- ============================================================
-- CRITÈRE 18 – Fonctionnalités applicatives au sein du SGBD
-- Triggers, Vues et Procédures stockées
-- Base : ecommerce_db
-- BTS SIO SLAM · KERMOISON Kevin · INGETIS Paris
-- ============================================================

USE ecommerce_db;

-- ══════════════════════════════════════════════════════════════
-- 1. TABLE DE LOGS (nécessaire pour le trigger)
-- ══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS logs_connexion (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT,
    role        VARCHAR(20) DEFAULT 'client',
    action      VARCHAR(50) NOT NULL,
    ip_address  VARCHAR(45),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logs_commandes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    ancien_statut VARCHAR(20),
    nouveau_statut VARCHAR(20),
    modifie_par INT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════════════════════
-- 2. TRIGGERS
-- ══════════════════════════════════════════════════════════════

-- ── Trigger 1 : Log automatique après insertion d'une prévente ──
DROP TRIGGER IF EXISTS after_prevente_insert;
DELIMITER //
CREATE TRIGGER after_prevente_insert
AFTER INSERT ON preventes
FOR EACH ROW
BEGIN
    -- Enregistrer l'action dans les logs
    INSERT INTO logs_connexion (client_id, role, action)
    VALUES (NEW.client_id, 'client', CONCAT('Inscription prévente #', NEW.produit_id));

    -- Vérifier si le seuil est maintenant atteint et notifier
    -- (vérification passive — la clôture réelle se fait en PHP)
    UPDATE produits p
    SET p.updated_at = NOW()
    WHERE p.id = NEW.produit_id;
END //
DELIMITER ;

-- ── Trigger 2 : Log automatique à chaque changement de statut commande ──
DROP TRIGGER IF EXISTS after_commande_update;
DELIMITER //
CREATE TRIGGER after_commande_update
AFTER UPDATE ON commandes
FOR EACH ROW
BEGIN
    IF OLD.statut != NEW.statut THEN
        INSERT INTO logs_commandes (commande_id, ancien_statut, nouveau_statut)
        VALUES (NEW.id, OLD.statut, NEW.statut);
    END IF;
END //
DELIMITER ;

-- ── Trigger 3 : Décrément automatique du stock à l'expédition ──
DROP TRIGGER IF EXISTS after_commande_expediee;
DELIMITER //
CREATE TRIGGER after_commande_expediee
AFTER UPDATE ON commandes
FOR EACH ROW
BEGIN
    -- Si le statut passe à 'expediee', décrémenter le stock de chaque produit
    IF OLD.statut != 'expediee' AND NEW.statut = 'expediee' THEN
        UPDATE produits p
        INNER JOIN commande_lignes cl ON cl.produit_id = p.id
        SET p.stock = p.stock - cl.quantite
        WHERE cl.commande_id = NEW.id
          AND p.stock >= cl.quantite; -- Sécurité : pas de stock négatif

        -- Enregistrer les mouvements de stock
        INSERT INTO mouvements_stock (produit_id, vendeur_id, type, quantite, motif, commande_id)
        SELECT cl.produit_id, NEW.vendeur_id, 'sortie', cl.quantite,
               CONCAT('Expédition commande #', NEW.id), NEW.id
        FROM commande_lignes cl
        WHERE cl.commande_id = NEW.id;
    END IF;
END //
DELIMITER ;

-- ── Trigger 4 : Alerte stock minimum ──
DROP TRIGGER IF EXISTS after_stock_update;
DELIMITER //
CREATE TRIGGER after_stock_update
AFTER UPDATE ON produits
FOR EACH ROW
BEGIN
    -- Si le stock passe sous le minimum, insérer une notification
    IF NEW.stock < NEW.stock_min AND OLD.stock >= OLD.stock_min THEN
        INSERT INTO historique_actions (acteur_type, acteur_id, action, details)
        VALUES ('systeme', NULL,
                'ALERTE STOCK',
                CONCAT('Produit "', NEW.nom, '" (#', NEW.id, ') : stock=', NEW.stock, ' < min=', NEW.stock_min));
    END IF;
END //
DELIMITER ;


-- ══════════════════════════════════════════════════════════════
-- 3. VUES SQL
-- ══════════════════════════════════════════════════════════════

-- ── Vue 1 : Produits actifs avec statistiques ──
DROP VIEW IF EXISTS v_produits_actifs;
CREATE VIEW v_produits_actifs AS
SELECT
    p.id,
    p.nom,
    c.nom                                              AS categorie,
    p.prix_initial,
    p.prix_groupe,
    ROUND((1 - p.prix_groupe / p.prix_initial) * 100, 1) AS economie_pct,
    p.stock,
    p.stock_min,
    CASE
        WHEN p.stock = 0               THEN 'Rupture'
        WHEN p.stock < p.stock_min     THEN 'Stock faible'
        ELSE                                'OK'
    END                                                AS statut_stock,
    COUNT(DISTINCT pv.id)                              AS nb_preventes,
    p.nb_min_acheteurs,
    ROUND(COUNT(DISTINCT pv.id) * 100.0 / NULLIF(p.nb_min_acheteurs, 0), 1) AS progression_pct,
    p.date_limite,
    DATEDIFF(p.date_limite, NOW())                     AS jours_restants
FROM produits p
LEFT JOIN categories c  ON c.id = p.categorie_id
LEFT JOIN preventes  pv ON pv.produit_id = p.id AND pv.statut = 'en_attente'
WHERE p.statut = 'en_attente'
  AND p.date_limite > NOW()
GROUP BY p.id;

-- ── Vue 2 : Statistiques par vendeur ──
DROP VIEW IF EXISTS v_stats_vendeurs;
CREATE VIEW v_stats_vendeurs AS
SELECT
    v.id                                               AS vendeur_id,
    v.entreprise,
    v.email,
    v.statut,
    COUNT(DISTINCT p.id)                               AS nb_produits_total,
    COUNT(DISTINCT CASE WHEN p.statut='en_attente' THEN p.id END) AS nb_actifs,
    COUNT(DISTINCT CASE WHEN p.statut='vendu'      THEN p.id END) AS nb_confirmes,
    COUNT(DISTINCT CASE WHEN p.statut='annule'     THEN p.id END) AS nb_annules,
    COUNT(DISTINCT pv.id)                              AS nb_preventes_total,
    COALESCE(SUM(f.montant), 0)                        AS ca_total,
    COUNT(DISTINCT s.id)                               AS nb_signalements
FROM vendeurs v
LEFT JOIN produits    p  ON p.vendeur_id  = v.id
LEFT JOIN preventes   pv ON pv.produit_id = p.id
LEFT JOIN factures    f  ON f.prevente_id = pv.id
LEFT JOIN signalements s ON s.produit_id  = p.id
GROUP BY v.id;

-- ── Vue 3 : Tableau de bord commandes ──
DROP VIEW IF EXISTS v_commandes_dashboard;
CREATE VIEW v_commandes_dashboard AS
SELECT
    c.id                                               AS commande_id,
    CONCAT(cl.prenom, ' ', cl.nom)                     AS client_nom,
    cl.email                                           AS client_email,
    c.statut,
    c.total,
    c.created_at,
    COUNT(cl2.id)                                      AS nb_articles,
    SUM(cl2.quantite)                                  AS total_articles,
    c.ville_livraison,
    c.code_postal_livraison
FROM commandes c
JOIN clients       cl  ON cl.id  = c.client_id
JOIN commande_lignes cl2 ON cl2.commande_id = c.id
GROUP BY c.id;


-- ══════════════════════════════════════════════════════════════
-- 4. PROCÉDURES STOCKÉES
-- ══════════════════════════════════════════════════════════════

-- ── Procédure : Clôturer manuellement une prévente ──
DROP PROCEDURE IF EXISTS sp_cloturer_prevente;
DELIMITER //
CREATE PROCEDURE sp_cloturer_prevente(IN p_produit_id INT)
BEGIN
    DECLARE v_nb_preventes INT DEFAULT 0;
    DECLARE v_nb_min       INT DEFAULT 0;
    DECLARE v_nom          VARCHAR(200);

    -- Récupérer les infos du produit
    SELECT nom, nb_min_acheteurs INTO v_nom, v_nb_min
    FROM produits WHERE id = p_produit_id;

    -- Compter les préventes en attente
    SELECT COUNT(*) INTO v_nb_preventes
    FROM preventes
    WHERE produit_id = p_produit_id AND statut = 'en_attente';

    -- Décision
    IF v_nb_preventes >= v_nb_min THEN
        -- Confirmer la vente
        UPDATE produits SET statut = 'vendu' WHERE id = p_produit_id;
        UPDATE preventes SET statut = 'confirmee' WHERE produit_id = p_produit_id AND statut = 'en_attente';
        SELECT CONCAT('✅ Vente confirmée : ', v_nom, ' (', v_nb_preventes, '/', v_nb_min, ' acheteurs)') AS resultat;
    ELSE
        -- Annuler la vente
        UPDATE produits SET statut = 'annule' WHERE id = p_produit_id;
        UPDATE preventes SET statut = 'annulee' WHERE produit_id = p_produit_id AND statut = 'en_attente';
        SELECT CONCAT('❌ Vente annulée : ', v_nom, ' (', v_nb_preventes, '/', v_nb_min, ' acheteurs)') AS resultat;
    END IF;
END //
DELIMITER ;

-- Usage : CALL sp_cloturer_prevente(1);


-- ══════════════════════════════════════════════════════════════
-- 5. VÉRIFICATION
-- ══════════════════════════════════════════════════════════════

-- Lister tous les triggers créés
SHOW TRIGGERS FROM ecommerce_db;

-- Lister toutes les vues créées
SELECT TABLE_NAME AS vue
FROM INFORMATION_SCHEMA.VIEWS
WHERE TABLE_SCHEMA = 'ecommerce_db';

-- Tester la vue produits actifs
-- SELECT * FROM v_produits_actifs ORDER BY jours_restants ASC;

-- Tester la vue stats vendeurs
-- SELECT * FROM v_stats_vendeurs;

-- Tester la procédure
-- CALL sp_cloturer_prevente(1);
