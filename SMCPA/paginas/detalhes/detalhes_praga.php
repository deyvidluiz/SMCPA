<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Iniciar sessão PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
$estaLogado = false;
$usuarioID = null;

// Verifica a flag 'logado' definida no login.php
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        $usuarioID = $_SESSION['usuario_id'];
        $estaLogado = true;
    } elseif (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
        $usuarioID = $_SESSION['id'];
        $estaLogado = true;
    }
}

// Se não estiver logado, redireciona
if (!$estaLogado || !$usuarioID) {
    session_destroy();
    header("Location: ../login/login.php");
    exit;
}

// Incluir arquivos de conexão
require_once('../../config.php'); 
include_once(BASE_URL.'/conexao/conexao.php');

$db = new Database();
$pdo = $db->conexao();

// Verificar se é administrador
$isAdmin = false;
if (isset($_SESSION['is_admin'])) {
    $isAdmin = $_SESSION['is_admin'] == 1;
} else {
    try {
        $stmtAdmin = $pdo->prepare("SELECT is_admin FROM usuarios WHERE id = :id");
        $stmtAdmin->bindParam(':id', $usuarioID, PDO::PARAM_INT);
        $stmtAdmin->execute();
        $userAdmin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
        $isAdmin = ($userAdmin && isset($userAdmin['is_admin']) && $userAdmin['is_admin'] == 1);
        $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
    } catch (PDOException $e) {
        $isAdmin = false;
    }
}

// Verificar se foi passado um ID de praga
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $dashboardUrl = $isAdmin ? "../dashboard/dashboardadm.php" : "../dashboard/dashboard.php";
    header("Location: " . $dashboardUrl);
    exit;
}

$pragaID = intval($_GET['id']);

// Buscar dados da praga
try {
    $stmt = $pdo->prepare("SELECT 
                            ID, Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, 
                            ID_Praga, Localidade, Data_Aparicao, Observacoes, ID_Usuario 
                          FROM Pragas_Surtos 
                          WHERE ID = :id");
    $stmt->bindParam(':id', $pragaID, PDO::PARAM_INT);
    $stmt->execute();
    $praga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$praga) {
        $dashboardUrl = $isAdmin ? "../dashboard/dashboardadm.php" : "../dashboard/dashboard.php";
        header("Location: " . $dashboardUrl . "?erro=praga_nao_encontrada");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar praga: " . $e->getMessage());
}

// Buscar dados do usuário que cadastrou a praga (se houver ID_Usuario)
$usuarioPraga = null;
if (!empty($praga['ID_Usuario'])) {
    try {
        $stmtUsuario = $pdo->prepare("SELECT id, usuario, email, Imagem FROM usuarios WHERE id = :id");
        $stmtUsuario->bindParam(':id', $praga['ID_Usuario'], PDO::PARAM_INT);
        $stmtUsuario->execute();
        $usuarioPraga = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignora erro se não conseguir buscar usuário
    }
}

// Verificar se é o próprio usuário que cadastrou
$ehMinhaPraga = (!empty($praga['ID_Usuario']) && $praga['ID_Usuario'] == $usuarioID);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Praga - <?php echo htmlspecialchars($praga['Nome']); ?> - SMCPA</title>
    <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="detalhes_praga.css">
</head>
<body>
    <div class="detalhes-container">
        <!-- Navegação Superior -->
        <div class="d-flex justify-content-between mb-4">
            <a href="<?= $isAdmin ? '../dashboard/dashboardadm.php' : '../dashboard/dashboard.php'; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
            </a>
            <?php if ($ehMinhaPraga): ?>
                <a href="../dashboard/perfil.php" class="btn btn-primary">
                    <i class="bi bi-person-circle"></i> Meu Perfil
                </a>
            <?php endif; ?>
        </div>

        <!-- Card Principal com Imagem e Informações Básicas -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="bi bi-bug-fill"></i> <?php echo htmlspecialchars($praga['Nome']); ?>
                </h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Imagem da Praga -->
                    <div class="col-md-5 mb-4 mb-md-0">
                        <?php if (!empty($praga['Imagem_Not_Null'])): ?>
                            <img src="/uploads/pragas/<?php echo htmlspecialchars($praga['Imagem_Not_Null']); ?>" 
                                 alt="Imagem da praga <?php echo htmlspecialchars($praga['Nome']); ?>" 
                                 class="imagem-praga">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-light" 
                                 style="height: 300px; border-radius: 8px;">
                                <div class="text-center text-muted">
                                    <i class="bi bi-image" style="font-size: 4rem;"></i>
                                    <p class="mt-3">Sem imagem disponível</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Informações Básicas -->
                    <div class="col-md-7">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="bi bi-flower1 text-success"></i> Planta Hospedeira
                            </div>
                            <div class="info-value">
                                <span class="badge bg-success badge-custom">
                                    <?php echo htmlspecialchars($praga['Planta_Hospedeira']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="bi bi-geo-alt-fill text-primary"></i> Localidade
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($praga['Localidade']); ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="bi bi-calendar-event text-warning"></i> Data de Aparição
                            </div>
                            <div class="info-value">
                                <?php 
                                $dataFormatada = date('d/m/Y', strtotime($praga['Data_Aparicao']));
                                echo htmlspecialchars($dataFormatada); 
                                ?>
                            </div>
                        </div>

                        <?php if (!empty($praga['ID_Praga'])): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="bi bi-tag-fill text-info"></i> ID da Praga
                            </div>
                            <div class="info-value">
                                <span class="badge bg-info badge-custom">
                                    <?php echo htmlspecialchars($praga['ID_Praga']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($usuarioPraga): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="bi bi-person-circle text-secondary"></i> Cadastrado por
                            </div>
                            <div class="info-value">
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($usuarioPraga['Imagem'])): ?>
                                        <img src="/uploads/usuarios/<?php echo htmlspecialchars($usuarioPraga['Imagem']); ?>" 
                                             alt="Foto de perfil" 
                                             style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px; object-fit: cover;">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($usuarioPraga['usuario']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card de Descrição -->
        <?php if (!empty($praga['Descricao'])): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-file-text"></i> Descrição
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space: pre-wrap; line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($praga['Descricao'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card de Observações -->
        <?php if (!empty($praga['Observacoes'])): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clipboard-data"></i> Observações
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space: pre-wrap; line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($praga['Observacoes'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card de Informações Adicionais -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Informações Adicionais
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-label">
                            <i class="bi bi-hash text-muted"></i> ID do Registro
                        </div>
                        <div class="info-value">
                            #<?php echo htmlspecialchars($praga['ID']); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">
                            <i class="bi bi-calendar-check text-muted"></i> Data de Cadastro
                        </div>
                        <div class="info-value">
                            <?php 
                            // Se não houver data específica, usa a data de aparição
                            $dataCadastro = !empty($praga['Data_Aparicao']) 
                                ? date('d/m/Y', strtotime($praga['Data_Aparicao'])) 
                                : 'Não informada';
                            echo htmlspecialchars($dataCadastro); 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="d-flex gap-2 justify-content-end">
            <a href="../dashboard/dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <?php if ($ehMinhaPraga): ?>
                <a href="../dashboard/perfil.php" class="btn btn-primary">
                    <i class="bi bi-person-circle"></i> Ver no Meu Perfil
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

