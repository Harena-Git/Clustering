<?php
/**
 * dbMultiPdo.php
 * Simple helper to manage two PDO connections and execute writes on both (best-effort transactional).
 * Reads connection params from environment variables for DB1 and DB2.
 */

function getPdoFromEnv($host, $dbname, $user, $mdp){
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $mdp, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

class MultiDb {
    private $pdo1;
    private $pdo2;

    public function __construct(){
        $h1 = getenv('DB1_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
        $n1 = getenv('DB1_NAME') ?: getenv('DB_NAME') ?: 'cluster_session_a';
        $u1 = getenv('DB1_USER') ?: getenv('DB_USER') ?: 'postgres';
        $p1 = getenv('DB1_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'harena';

        $h2 = getenv('DB2_HOST') ?: '127.0.0.1';
        $n2 = getenv('DB2_NAME') ?: 'cluster_session_b';
        $u2 = getenv('DB2_USER') ?: 'postgres';
        $p2 = getenv('DB2_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'harena';

        try {
            $this->pdo1 = getPdoFromEnv($h1, $n1, $u1, $p1);
        } catch (PDOException $e) {
            error_log('DB1 connection failed: ' . $e->getMessage());
            $this->pdo1 = null;
        }

        try {
            $this->pdo2 = getPdoFromEnv($h2, $n2, $u2, $p2);
        } catch (PDOException $e) {
            error_log('DB2 connection failed: ' . $e->getMessage());
            $this->pdo2 = null;
        }
    }

    /**
     * Read query: use primary DB1 if available, else DB2
     */
    public function read($sql, $params = []){
        $pdo = $this->pdo1 ?: $this->pdo2;
        if (!$pdo) return [];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a write on both DBs. Attempts transactions on both; best-effort: if one fails, we log and try to rollback.
     * Returns true if at least one succeeded, false otherwise.
     */
    public function writeBoth($sql, $params = []){
        $ok1 = false; $ok2 = false;

        // Helper to execute on a PDO instance
        $execOn = function($pdo) use ($sql, $params){
            if (!$pdo) return false;
            try {
                if (!$pdo->inTransaction()) $pdo->beginTransaction();
                $stmt = $pdo->prepare($sql);
                $res = $stmt->execute($params);
                $pdo->commit();
                return $res;
            } catch (Exception $e) {
                try { if ($pdo && $pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $e2) {}
                error_log('Write failed on one DB: ' . $e->getMessage());
                return false;
            }
        };

        if ($this->pdo1) $ok1 = $execOn($this->pdo1);
        if ($this->pdo2) $ok2 = $execOn($this->pdo2);

        // At least one must succeed for us to return true (application-level decision).
        return ($ok1 || $ok2);
    }

    public function getPdo1(){ return $this->pdo1; }
    public function getPdo2(){ return $this->pdo2; }
}

// end
