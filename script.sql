-- Création de la base de données
CREATE DATABASE cluster_session;

-- Connexion à la base de données
\c cluster_session;

-- Création de la table des sessions
CREATE TABLE php_session(
    id VARCHAR(255) PRIMARY KEY,
    data TEXT,
    date_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Création d'un index pour améliorer les performances
CREATE INDEX idx_php_session_date_change ON php_session(date_change);