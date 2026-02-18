<?php
// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');

// Obter ID do usuário
$usuarioID = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;

$db = new Database();
$pdo = $db->conexao();

// Verificar se é administrador
$isAdmin = false;
if (isset($_SESSION['is_admin'])) {
    $isAdmin = $_SESSION['is_admin'] == 1;
} else {
    try {
        $stmtAdmin = $pdo->prepare("SELECT is_admin FROM Usuarios WHERE id = :id");
        $stmtAdmin->bindParam(':id', $usuarioID, PDO::PARAM_INT);
        $stmtAdmin->execute();
        $userAdmin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
        $isAdmin = ($userAdmin && isset($userAdmin['is_admin']) && $userAdmin['is_admin'] == 1);
        $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
    } catch (PDOException $e) {
        $isAdmin = false;
    }
}

$dashboardUrl = $isAdmin ? 'dashboardadm.php' : 'dashboard.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: ' . $dashboardUrl . '?erro=praga_invalida');
    exit;
}

$id = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare('SELECT Imagem_Not_Null FROM Pragas_Surtos WHERE ID = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $praga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$praga) {
        header('Location: ' . $dashboardUrl . '?erro=praga_nao_encontrada');
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

    header('Location: ' . $dashboardUrl . '?status=praga_excluida');
    exit;
} catch (PDOException $e) {
    error_log('Erro ao excluir praga: ' . $e->getMessage());
    header('Location: ' . $dashboardUrl . '?erro=erro_ao_excluir');
    exit;
}
