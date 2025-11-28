<?php
class Database
{
    // private $host = "localhost";
    // private $dbname = "teste";
    // private $username = "root";
    // private $password = "databasekey@31";
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
            // echo "Conexão bem-sucedida!";
            return ($this->conn);
        } catch (PDOException $e) {
            die("Erro ao conectar: " . $e->getMessage());
        }
    }
}


