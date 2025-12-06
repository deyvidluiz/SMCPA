<?php
require_once('../../config.php');
include_once(BASE_URL.'/database/conexao.php');

$db = new Database();
$pdo = $db->conexao();

echo "<h2>Estrutura da Tabela Pragas_Surtos</h2>";
$stmt = $pdo->query('DESCRIBE Pragas_Surtos');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th></tr>";
foreach ($cols as $col) {
    echo "<tr>";
    echo "<td>{$col['Field']}</td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Key']}</td>";
    echo "</tr>";
}
echo "</table>";

// Verificar se tem ID_Praga_Original
$temColunaOriginal = false;
foreach ($cols as $col) {
    if ($col['Field'] === 'ID_Praga_Original') {
        $temColunaOriginal = true;
        break;
    }
}

echo "<p>";
if (!$temColunaOriginal) {
    echo "<strong style='color: red;'>❌ Coluna ID_Praga_Original não existe!</strong><br>";
    echo "Será criada automaticamente.";
} else {
    echo "<strong style='color: green;'>✅ Coluna ID_Praga_Original existe</strong>";
}
echo "</p>";
?>
