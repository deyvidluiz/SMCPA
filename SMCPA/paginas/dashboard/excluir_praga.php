<?php
require_once('../../config.php');
include_once(BASE_URL . '/conexao/conexao.php');

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: dashboardadm.php?erro=praga_invalida');
    exit;
}

$id = (int) $_GET['id'];
$db = new Database();
$pdo = $db->conexao();

try {
    $stmt = $pdo->prepare('SELECT Imagem_Not_Null FROM Pragas_Surtos WHERE ID = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $praga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$praga) {
        header('Location: dashboardadm.php?erro=praga_nao_encontrada');
        exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM Pragas_Surtos WHERE ID = :id');
    $deleteStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $deleteStmt->execute();

    if (!empty($praga['Imagem_Not_Null'])) {
        $imagemPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/pragas/' . $praga['Imagem_Not_Null'];
        if (is_file($imagemPath)) {
            @unlink($imagemPath);
        }
    }

    header('Location: dashboardadm.php?status=praga_excluida');
    exit;
} catch (PDOException $e) {
    error_log('Erro ao excluir praga: ' . $e->getMessage());
    header('Location: dashboardadm.php?erro=erro_ao_excluir');
    exit;
}


