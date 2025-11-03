<?php

function getPdo($host, $dbname, $user, $mdp){
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $mdp, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    return $pdo;
}


class DbSessionHandler implements SessionHandlerInterface {
    private $pdo ;

    public function __construct(){
        $this->pdo =getPdo('localhost' , 'cluster_session' , 'cluster' , 'cluster');
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM php_session WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetchColumn();
        return $data ?: ''; 
    }

    public function write($id, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO php_session (id, data) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data)
        ");
        return $stmt->execute([$id, $data]);
    }

    public function destroy($id) {
        $stmt = $this->pdo->prepare("DELETE FROM php_session WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($maxlifetime) {
        return true;
    }

}

session_set_save_handler(new DbSessionHandler() , true);
