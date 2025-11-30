<?php
class Database
{
    private $conn;

    public function conexao($host = "localhost", $dbname = "Sistema", $username = "root", $password = "dvd1224@")
    {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $host . ";dbname=" . $dbname,
                $username,
                $password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conn;
        } catch (PDOException $e) {
            die("Erro ao conectar: " . $e->getMessage());
        }
    }
}
