<?php
// Script para verificar se a tabela historico_pragas existe e criar se necessário
// Localização: paginas/dashboard/verificar_historico.php

session_start();

// Verificar se é admin (segurança)
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../login/login.php");
    exit;
}

require_once('../../config.php');
include_once(BASE_URL.'/database/conexao.php');

$db = new Database();
$pdo = $db->conexao();

echo "<h2>Verificação da Tabela de Histórico</h2>";

// Verificar se tabela existe
try {
    $result = $pdo->query("DESC historico_pragas");
    if ($result) {
        echo "<p style='color: green;'>✓ Tabela <strong>historico_pragas</strong> já existe.</p>";
        echo "<h3>Estrutura da tabela:</h3>";
        echo "<pre>";
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            echo $row['Field'] . " - " . $row['Type'] . " (" . $row['Null'] . ")\n";
        }
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Tabela não encontrada. Criando...</p>";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS historico_pragas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ID_Praga INT NOT NULL,
            media_pragas_planta DECIMAL(10,2),
            severidade VARCHAR(50),
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ID_Praga) REFERENCES Pragas_Surtos(ID) ON DELETE CASCADE
        )");
        echo "<p style='color: green;'>✓ Tabela criada com sucesso!</p>";
    } catch (PDOException $ex) {
        echo "<p style='color: red;'>✗ Erro ao criar tabela: " . $ex->getMessage() . "</p>";
    }
}

// Contar registros
try {
    $count = $pdo->query("SELECT COUNT(*) as total FROM historico_pragas")->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total de registros:</strong> " . $count['total'] . "</p>";
    
    if ($count['total'] > 0) {
        echo "<p>✓ Histórico está sendo registrado!</p>";
        echo "<h3>Últimas 5 atualizações:</h3>";
        $recent = $pdo->query("SELECT h.*, p.Nome FROM historico_pragas h LEFT JOIN Pragas_Surtos p ON h.ID_Praga = p.ID ORDER BY h.data_atualizacao DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' style='width:100%; border-collapse:collapse;'>";
        echo "<tr><th>ID Praga</th><th>Nome Praga</th><th>Média</th><th>Severidade</th><th>Data/Hora</th></tr>";
        foreach ($recent as $row) {
            echo "<tr>";
            echo "<td>" . $row['ID_Praga'] . "</td>";
            echo "<td>" . ($row['Nome'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['media_pragas_planta'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['severidade'] ?? 'N/A') . "</td>";
            echo "<td>" . $row['data_atualizacao'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro ao contar registros: " . $e->getMessage() . "</p>";
}

echo "<p><a href='/SMCPA/paginas/dashboard/dashboard.php'>← Voltar ao Dashboard</a></p>";
?>
