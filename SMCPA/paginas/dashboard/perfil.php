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

// Headers para prevenir cache e garantir que o botão voltar não funcione após logout
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Incluir arquivos de conexão
require_once('../../config.php'); 
include_once(BASE_URL.'/database/conexao.php');

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

// Verificar se está visualizando perfil de outro usuário (admin)
$usuarioVisualizadoID = $usuarioID; // Por padrão, visualiza o próprio perfil
$ehProprioPerfil = true;

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $idSolicitado = intval($_GET['id']);
    // Se o ID solicitado for diferente do usuário logado, permite visualizar (admin)
    if ($idSolicitado != $usuarioID) {
        $usuarioVisualizadoID = $idSolicitado;
        $ehProprioPerfil = false;
    }
}

// Buscar dados do usuário
try {
    // Verificar se a coluna localizacao existe
    try {
        $stmtCheck = $pdo->query("SHOW COLUMNS FROM Usuarios LIKE 'localizacao'");
        $colunaExiste = $stmtCheck->rowCount() > 0;
        
        if (!$colunaExiste) {
            $pdo->exec("ALTER TABLE Usuarios ADD COLUMN localizacao VARCHAR(255) DEFAULT NULL");
        }
    } catch (PDOException $e) {
        // Ignorar erro - coluna provavelmente já existe
    }
    
    $stmt = $pdo->prepare("SELECT id, usuario, email, Imagem, data_cadastro, localizacao FROM Usuarios WHERE id = :id");
    $stmt->bindParam(':id', $usuarioVisualizadoID, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        session_destroy();
        header("Location: ../login/login.php");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar usuário: " . $e->getMessage());
}

// Recuperar pragas cadastradas pelo usuário
try {
    // Verifica se a coluna ID_Usuario existe, caso contrário ignora o filtro
    $stmtPragas = $pdo->prepare("SELECT ID, Nome, Planta_Hospedeira, Data_Aparicao, Localidade 
                                 FROM Pragas_Surtos 
                                 WHERE ID_Usuario = :usuarioID OR ID_Usuario IS NULL
                                 ORDER BY Data_Aparicao DESC");
    $stmtPragas->bindParam(':usuarioID', $usuarioVisualizadoID, PDO::PARAM_INT);
    $stmtPragas->execute();
    $pragas = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a coluna não existe, busca todas as pragas
    try {
        $stmtPragas = $pdo->prepare("SELECT ID, Nome, Planta_Hospedeira, Data_Aparicao, Localidade 
                                     FROM Pragas_Surtos 
                                     ORDER BY Data_Aparicao DESC");
        $stmtPragas->execute();
        $pragas = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $pragas = [];
        $erro = "Erro ao carregar pragas: " . $e2->getMessage();
    }
}

// Processar ALTERAÇÃO DE FOTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_foto']) && $ehProprioPerfil) {
    if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
        $arquivo = $_FILES['nova_foto'];
        
        // Validações
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            $erro = "A imagem deve ser JPG ou PNG.";
        } elseif ($arquivo['size'] > 5 * 1024 * 1024) { // 5MB
            $erro = "A imagem precisa ser menor que 5MB.";
        } else {
            // Preparar diretório
            $diretorio = $_SERVER['DOCUMENT_ROOT'] . '/uploads/usuarios/';
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0755, true);
            }

            // Gerar nome único
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $nomeImagem = 'usuario_' . $usuarioID . '_' . time() . '.' . $extensao;
            $caminhoCompleto = $diretorio . $nomeImagem;

            // Mover arquivo
            if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                try {
                    // Deletar imagem antiga
                    if (!empty($usuario['Imagem']) && $usuario['Imagem'] !== 'default.jpg') {
                        $imagemAntiga = $diretorio . $usuario['Imagem'];
                        if (file_exists($imagemAntiga)) {
                            unlink($imagemAntiga);
                        }
                    }

                    // Atualizar no banco
                    $stmt = $pdo->prepare("UPDATE Usuarios SET Imagem = :imagem WHERE id = :id");
                    $stmt->bindParam(':imagem', $nomeImagem);
                    $stmt->bindParam(':id', $usuarioID, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $usuario['Imagem'] = $nomeImagem;
                    $sucesso = "Foto de perfil atualizada com sucesso!";
                } catch (PDOException $e) {
                    $erro = "Erro ao atualizar foto no banco: " . $e->getMessage();
                }
            } else {
                $erro = "Erro ao enviar a foto. Verifique as permissões do diretório.";
            }
        }
    } else {
        $erro = "Nenhuma foto foi selecionada ou houve erro no upload.";
    }
}

// Processar ATUALIZAÇÃO DE DADOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_dados']) && $ehProprioPerfil) {
    $novoNome = trim($_POST['usuario']);
    $novoEmail = trim($_POST['email']);
    $novaLocalizacao = trim($_POST['localizacao'] ?? '');
    
    if (empty($novoNome) || empty($novoEmail)) {
        $erro = "Nome e email são obrigatórios.";
    } elseif (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } else {
        try {
            // Verificar se o email já existe (exceto o próprio usuário)
            $stmt = $pdo->prepare("SELECT id FROM Usuarios WHERE email = :email AND id != :id");
            $stmt->bindParam(':email', $novoEmail);
            $stmt->bindParam(':id', $usuarioID, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $erro = "Este email já está sendo usado por outro usuário.";
            } else {
                // Atualizar dados (incluindo localização)
                $stmt = $pdo->prepare("UPDATE Usuarios SET usuario = :usuario, email = :email, localizacao = :localizacao WHERE id = :id");
                $stmt->bindParam(':usuario', $novoNome);
                $stmt->bindParam(':email', $novoEmail);
                $stmt->bindParam(':localizacao', $novaLocalizacao);
                $stmt->bindParam(':id', $usuarioID, PDO::PARAM_INT);
                $stmt->execute();

                // Atualizar sessão
                $_SESSION['usuario'] = $novoNome;
                $_SESSION['email'] = $novoEmail;

                // Atualizar variável local
                $usuario['usuario'] = $novoNome;
                $usuario['email'] = $novoEmail;
                $usuario['localizacao'] = $novaLocalizacao;
                
                $sucesso = "Dados atualizados com sucesso!";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar dados: " . $e->getMessage();
        }
    }
}

// Processar ALTERAÇÃO DE SENHA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha']) && $ehProprioPerfil) {
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmarSenha = $_POST['confirmar_senha'];

    if (empty($senhaAtual) || empty($novaSenha) || empty($confirmarSenha)) {
        $erro = "Todos os campos de senha são obrigatórios.";
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = "A nova senha e a confirmação não conferem.";
    } elseif (strlen($novaSenha) < 6) {
        $erro = "A nova senha deve ter no mínimo 6 caracteres.";
    } else {
        try {
            // Verificar senha atual
            $stmt = $pdo->prepare("SELECT senha FROM Usuarios WHERE id = :id");
            $stmt->bindParam(':id', $usuarioID, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($senhaAtual, $resultado['senha'])) {
                // Atualizar senha
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE Usuarios SET senha = :senha WHERE id = :id");
                $stmt->bindParam(':senha', $senhaHash);
                $stmt->bindParam(':id', $usuarioID, PDO::PARAM_INT);
                $stmt->execute();

                $sucesso = "Senha alterada com sucesso!";
            } else {
                $erro = "Senha atual incorreta!";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao alterar senha: " . $e->getMessage();
        }
    }
}

// Processar EXCLUSÃO DE CONTA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deletar_conta']) && $ehProprioPerfil) {
    $senhaConfirmacao = $_POST['senha_confirmacao'] ?? '';
    
    if (empty($senhaConfirmacao)) {
        $erro = "Digite sua senha para confirmar a exclusão.";
    } else {
        try {
            // Verificar senha
            $stmt = $pdo->prepare("SELECT senha FROM Usuarios WHERE id = :id");
            $stmt->bindParam(':id', $usuarioID, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($senhaConfirmacao, $resultado['senha'])) {
                // Deletar imagem do usuário
                if (!empty($usuario['Imagem']) && $usuario['Imagem'] !== 'default.jpg') {
                    $imagemPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/usuarios/' . $usuario['Imagem'];
                    if (file_exists($imagemPath)) {
                        unlink($imagemPath);
                    }
                }

                // Deletar pragas associadas - com tratamento de erro
                try {
                    $stmtDelPragas = $pdo->prepare("DELETE FROM Pragas_Surtos WHERE ID_Usuario = :usuarioID");
                    $stmtDelPragas->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
                    $stmtDelPragas->execute();
                } catch (PDOException $e) {
                    // Se a coluna ID_Usuario não existir, ignora
                }

                // Deletar o usuário
                $stmtDelUser = $pdo->prepare("DELETE FROM Usuarios WHERE id = :usuarioID");
                $stmtDelUser->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
                $stmtDelUser->execute();

                // Destruir sessão e redirecionar
                session_destroy();
                header("Location: ../login/login.php?conta_excluida=1");
                exit;
            } else {
                $erro = "Senha incorreta. Não foi possível excluir a conta.";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao excluir conta: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Meu Perfil - SMCPA</title>
    <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/SMCPA/css/perfil.css">
</head>
<body>
    <div class="perfil-container">
        <!-- Navegação Superior -->
        <div class="d-flex justify-content-between mb-4">
            <?php 
            // Determinar para qual dashboard voltar
            $dashboardUrl = $isAdmin ? "dashboardadm.php" : "../dashboard/dashboard.php";
            ?>
            <a href="<?= $dashboardUrl; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
            </a>
            <?php if ($ehProprioPerfil): ?>
                <a href="../login/logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            <?php endif; ?>
        </div>

        <!-- Aviso quando visualizando perfil de outro usuário -->
        <?php if (!$ehProprioPerfil): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle"></i> Você está visualizando o perfil de outro usuário. As opções de edição estão desabilitadas.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Mensagens de Sucesso/Erro -->
        <?php if (isset($sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($sucesso); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($erro); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Card do Perfil -->
        <div class="card">
            <div class="card-body text-center">
                <h2 class="card-title mb-4">
                    <i class="bi bi-person-circle"></i> Meu Perfil
                </h2>
                
                <!-- Foto de Perfil -->
                <div class="mb-3">
                    <?php if (!empty($usuario['Imagem'])): ?>
                        <img src="/uploads/usuarios/<?php echo htmlspecialchars($usuario['Imagem']); ?>" 
                             alt="Foto de perfil" 
                             class="perfil-imagem">
                    <?php else: ?>
                        <img src="/SMCPA/imgs/logotrbf.png" 
                             alt="Sem foto" 
                             class="perfil-imagem" 
                             style="opacity: 0.5;">
                    <?php endif; ?>
                </div>

                <h4><?php echo htmlspecialchars($usuario['usuario']); ?></h4>
                <p class="text-muted">
                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($usuario['email']); ?>
                </p>
                <p class="text-muted">
                    <small><i class="bi bi-calendar"></i> Membro desde: 
                    <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></small>
                </p>

                <!-- Formulário de Alteração de Foto -->
                <?php if ($ehProprioPerfil): ?>
                    <form method="POST" enctype="multipart/form-data" class="mt-3">
                        <div class="input-group mb-2">
                            <input type="file" class="form-control" name="nova_foto" 
                                   accept="image/jpeg, image/png, image/jpg" required>
                            <button type="submit" name="alterar_foto" class="btn btn-primary">
                                <i class="bi bi-camera"></i> Alterar Foto
                            </button>
                        </div>
                        <small class="text-muted">Máximo 5MB - Formatos: JPG, PNG</small>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card de Edição de Dados -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil-square"></i> <?php echo $ehProprioPerfil ? 'Editar Dados' : 'Dados do Usuário'; ?>
            </div>
            <div class="card-body">
                <?php if ($ehProprioPerfil): ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="usuario" class="form-label">Nome de Usuário</label>
                                <input type="text" class="form-control" id="usuario" name="usuario" 
                                       value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="localizacao" class="form-label">Localização/Região</label>
                                <input type="text" class="form-control" id="localizacao" name="localizacao" 
                                       value="<?php echo htmlspecialchars($usuario['localizacao'] ?? ''); ?>" 
                                       placeholder="Ex: Zona Rural de São Paulo, Fazenda ABC, etc.">
                                <small class="text-muted">Esta informação será usada para mostrar surtos próximos na sua região</small>
                            </div>
                        </div>

                        <button type="submit" name="atualizar_dados" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Salvar Alterações
                        </button>
                    </form>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome de Usuário</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Localização/Região</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['localizacao'] ?? 'Não informado'); ?>" readonly>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card de Alteração de Senha -->
        <?php if ($ehProprioPerfil): ?>
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-key"></i> Alterar Senha
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual</label>
                            <input type="password" class="form-control" id="senha_atual" 
                                   name="senha_atual" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha" 
                                       name="nova_senha" minlength="6" required>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" 
                                       name="confirmar_senha" minlength="6" required>
                            </div>
                        </div>

                        <button type="submit" name="alterar_senha" class="btn btn-warning">
                            <i class="bi bi-shield-lock"></i> Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Card de Pragas Cadastradas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-bug"></i> <?php echo $ehProprioPerfil ? 'Minhas Pragas Cadastradas' : 'Pragas Cadastradas'; ?> (<?php echo count($pragas); ?>)
                </span>
                <?php if ($ehProprioPerfil): ?>
                    <a href="../cadastro/cadpraga.php" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle"></i> Cadastrar Mais Pragas
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($pragas)): ?>
                    <div class="list-group">
                        <?php foreach ($pragas as $praga): ?>
                            <div class="praga-item">
                                <h6 class="mb-1">
                                    <i class="bi bi-bug-fill text-danger"></i> 
                                    <?php echo htmlspecialchars($praga['Nome']); ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="bi bi-flower1"></i> Planta: <?php echo htmlspecialchars($praga['Planta_Hospedeira']); ?> |
                                    <i class="bi bi-geo-alt"></i> Local: <?php echo htmlspecialchars($praga['Localidade']); ?> |
                                    <i class="bi bi-calendar"></i> Data: <?php echo date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?>
                                </small>
                                <div class="mt-2">
                                    <a href="../cadastro/atualizar_praga.php?id=<?php echo $praga['ID']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil-square"></i> Atualizar
                                    </a>
                                    <a href="../detalhes/detalhes_praga.php?id=<?php echo $praga['ID']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Ver Detalhes
                                    </a>
                                    <a href="gerar_relatorio.php?id=<?php echo $praga['ID']; ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-file-text"></i> Relatório
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">
                        <i class="bi bi-inbox"></i> Você ainda não cadastrou nenhuma praga.
                    </p>
                    <div class="text-center">
                        <a href="../cadastro/cadpraga.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Cadastrar Praga
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card de Exclusão de Conta -->
        <?php if ($ehProprioPerfil): ?>
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i> Zona de Perigo
                </div>
                <div class="card-body">
                    <h5 class="text-danger">Excluir Conta Permanentemente</h5>
                    <p class="text-muted">
                        Esta ação é irreversível. Todos os seus dados, incluindo pragas cadastradas, 
                        serão permanentemente excluídos.
                    </p>
                    
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                            data-bs-target="#modalExcluirConta">
                        <i class="bi bi-trash"></i> Excluir Minha Conta
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="modalExcluirConta" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i> Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" 
                            data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p class="fw-bold text-danger">
                            Tem certeza que deseja excluir sua conta permanentemente?
                        </p>
                        <p>Esta ação NÃO pode ser desfeita. Todos os seus dados serão perdidos.</p>
                        
                        <div class="mb-3">
                            <label for="senha_confirmacao" class="form-label">
                                Digite sua senha para confirmar:
                            </label>
                            <input type="password" class="form-control" 
                                   id="senha_confirmacao" name="senha_confirmacao" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" name="deletar_conta" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Sim, Excluir Minha Conta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>