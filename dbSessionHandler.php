<?php

function getPdo($host, $dbname, $user, $mdp){
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $mdp, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

class DbSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $fallback = false;

    public function __construct(){
        // Lire la configuration depuis les variables d'environnement
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_NAME') ?: 'cluster_session';
        $user = getenv('DB_USER') ?: 'postgres';
        $mdp = getenv('DB_PASSWORD') ?: 'harena';

        try {
            $this->pdo = getPdo($host, $dbname, $user, $mdp);
        } catch (PDOException $e) {
            // Ne pas laisser l'application planter : activer un fallback non-persistant
            error_log('DB connection failed: ' . $e->getMessage());
            $this->pdo = null;
            $this->fallback = true;
        }
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        if ($this->fallback || !$this->pdo) {
            // Fallback : session non persistante
            return '';
        }

        $stmt = $this->pdo->prepare("SELECT data FROM php_session WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetchColumn();
        return $data ?: '';
    }

    public function write($id, $data) {
        if ($this->fallback || !$this->pdo) {
            // Accept write but don't persist
            return true;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO php_session (id, data) VALUES (?, ?)
            ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, date_change = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$id, $data]);
    }

    public function destroy($id) {
        if ($this->fallback || !$this->pdo) {
            return true;
        }

        $stmt = $this->pdo->prepare("DELETE FROM php_session WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($maxlifetime) {
        if ($this->fallback || !$this->pdo) {
            return true;
        }

        $stmt = $this->pdo->prepare("DELETE FROM php_session WHERE date_change < CURRENT_TIMESTAMP - INTERVAL '$maxlifetime seconds'");
        return $stmt->execute();
    }
}

session_set_save_handler(new DbSessionHandler(), true);