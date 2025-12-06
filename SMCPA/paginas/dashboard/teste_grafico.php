<?php
session_start();

// Incluir configuração
require_once('../../config.php');
include_once(BASE_URL.'/database/conexao.php');

// Verificar se está logado (para teste, usar ID 1)
$usuarioID = 1; // MUDAR PARA SEU ID

$db = new Database();
$pdo = $db->conexao();

echo "<h2>Teste de Dados do Gráfico</h2>";
echo "<p><strong>Usuário ID:</strong> $usuarioID</p>";
echo "<hr>";

// 1. Verificar se existem pragas cadastradas
echo "<h3>1. Pragas Cadastradas</h3>";
try {
    $stmt = $pdo->prepare("SELECT ID, Nome, Planta_Hospedeira, media_pragas_planta, Data_Aparicao 
                          FROM Pragas_Surtos 
                          WHERE ID_Usuario = :usuarioID 
                          ORDER BY Data_Aparicao DESC LIMIT 10");
    $stmt->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmt->execute();
    $pragas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pragas)) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Planta</th><th>Média Pragas</th><th>Data</th></tr>";
        foreach ($pragas as $praga) {
            $media = $praga['media_pragas_planta'] ?? 'NULL';
            echo "<tr>";
            echo "<td>{$praga['ID']}</td>";
            echo "<td>{$praga['Nome']}</td>";
            echo "<td>{$praga['Planta_Hospedeira']}</td>";
            echo "<td style='background-color: " . (is_null($praga['media_pragas_planta']) ? '#ffcccc' : '#ccffcc') . "'>";
            echo $media . "</td>";
            echo "<td>{$praga['Data_Aparicao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>NENHUMA PRAGA ENCONTRADA!</strong></p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 2. Verificar dados válidos para o gráfico (com média de pragas > 0)
echo "<h3>2. Pragas com Dados de Gráfico (media_pragas_planta > 0)</h3>";
try {
    $dataLimite = date('Y-m-d', strtotime('-30 days'));
    $stmt = $pdo->prepare("SELECT ID, Nome, Data_Aparicao, media_pragas_planta, severidade
                          FROM Pragas_Surtos 
                          WHERE ID_Usuario = :usuarioID 
                          AND media_pragas_planta IS NOT NULL
                          AND media_pragas_planta > 0
                          AND Data_Aparicao >= :dataLimite
                          ORDER BY Data_Aparicao ASC");
    $stmt->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmt->bindValue(':dataLimite', $dataLimite, PDO::PARAM_STR);
    $stmt->execute();
    $graficoPragas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($graficoPragas)) {
        echo "<p style='color: green;'><strong>✓ Encontradas " . count($graficoPragas) . " pragas com dados para o gráfico!</strong></p>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Data</th><th>Média Pragas</th><th>Severidade</th></tr>";
        foreach ($graficoPragas as $praga) {
            echo "<tr>";
            echo "<td>{$praga['ID']}</td>";
            echo "<td>{$praga['Nome']}</td>";
            echo "<td>{$praga['Data_Aparicao']}</td>";
            echo "<td><strong>" . round($praga['media_pragas_planta'], 2) . "</strong></td>";
            echo "<td>{$praga['severidade']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p><small>Limite de data: $dataLimite (últimos 30 dias)</small></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ NENHUMA PRAGA COM DADOS DE GRÁFICO!</strong></p>";
        echo "<p>Razões possíveis:</p>";
        echo "<ul>";
        echo "<li>media_pragas_planta é NULL ou 0</li>";
        echo "<li>Nenhuma praga nos últimos 30 dias</li>";
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// 3. Verificar estrutura da tabela
echo "<h3>3. Estrutura da Tabela Pragas_Surtos</h3>";
try {
    $stmt = $pdo->query("DESCRIBE Pragas_Surtos");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th><th>Extra</th></tr>";
    foreach ($colunas as $coluna) {
        $importante = in_array($coluna['Field'], ['media_pragas_planta', 'severidade', 'Data_Aparicao']) ? 'style="background-color: #ffffcc"' : '';
        echo "<tr $importante>";
        echo "<td>{$coluna['Field']}</td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>Voltar ao Dashboard</a></p>";
?>
