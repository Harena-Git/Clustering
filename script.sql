-- Création de la base de données
-- Création de deux bases pour le cluster (A et B)
-- Exécuter ces commandes en tant qu'utilisateur postgres (sudo -u postgres psql) ou via psql connecté en superuser.

CREATE DATABASE cluster_session_a;
CREATE DATABASE cluster_session_b;

-- Table de sessions et tables de test (items, servers) à créer dans les deux bases.
-- Connectez-vous ensuite à chaque base et créez les tables ci-dessous.

-- Exemple :
-- sudo -u postgres psql -d cluster_session_a -f /path/to/this/script.sql -- (ou exécuter manuellement les blocs ci-dessous dans chaque DB)

-- Schéma commun : php_session, items, servers

-- Création de la table des sessions
CREATE TABLE IF NOT EXISTS php_session(
    id VARCHAR(255) PRIMARY KEY,
    data TEXT,
    date_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Création d'un index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_php_session_date_change ON php_session(date_change);

-- Table de démonstration pour CRUD
CREATE TABLE IF NOT EXISTS items (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table pour gérer les serveurs (statut)
CREATE TABLE IF NOT EXISTS servers (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    address TEXT,
    status VARCHAR(32) DEFAULT 'up',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index utile
CREATE INDEX IF NOT EXISTS idx_servers_status ON servers(status);