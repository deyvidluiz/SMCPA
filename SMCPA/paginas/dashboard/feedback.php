<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Inicia a sessão para manter o login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers para prevenir cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id']) && !isset($_SESSION['logado'])) {
    header("Location: /SMCPA/paginas/login/login.php");
    exit;
}

// Obter ID do usuário
$usuarioID = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;

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

// Buscar dados do usuário
$nomeUsuario = '';
$emailUsuario = '';
if ($usuarioID) {
    try {
        $stmtUsuario = $pdo->prepare("SELECT usuario, email FROM usuarios WHERE id = :id");
        $stmtUsuario->bindParam(':id', $usuarioID, PDO::PARAM_INT);
        $stmtUsuario->execute();
        $user = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $nomeUsuario = $user['usuario'] ?? '';
            $emailUsuario = $user['email'] ?? '';
        }
    } catch (PDOException $e) {
        // Ignorar erro
    }
}

// Buscar imagem do perfil do usuário
$imagemPerfil = null;
if ($usuarioID) {
    try {
        $stmtImagem = $pdo->prepare("SELECT Imagem FROM usuarios WHERE id = :id");
        $stmtImagem->bindParam(':id', $usuarioID, PDO::PARAM_INT);
        $stmtImagem->execute();
        $resultado = $stmtImagem->fetch(PDO::FETCH_ASSOC);
        if ($resultado && !empty($resultado['Imagem'])) {
            $imagemPerfil = '/uploads/usuarios/' . $resultado['Imagem'];
        }
    } catch (PDOException $e) {
        $imagemPerfil = null;
    }
}
if (!$imagemPerfil) {
    $imagemPerfil = '/SMCPA/imgs/logotrbf.png';
}

// A tabela Feedback já existe no banco de dados conforme o script SQL do projeto
// Estrutura: ID, Mensagem, Usuario (FK para Usuarios.ID), Data_Envio

// Processar envio do formulário
$mensagemSucesso = '';
$mensagemErro = '';
$tipo = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_feedback'])) {
    $tipo = trim($_POST['tipo'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');
    
    // Validação básica
    if (empty($usuarioID)) {
        $mensagemErro = 'Erro: Usuário não identificado. Por favor, faça login novamente.';
    } elseif (empty($tipo)) {
        $mensagemErro = 'Por favor, selecione o tipo de feedback.';
    } elseif (empty($mensagem)) {
        $mensagemErro = 'Por favor, preencha a mensagem.';
    } elseif (strlen($mensagem) < 10) {
        $mensagemErro = 'A mensagem deve ter pelo menos 10 caracteres.';
    } elseif (strlen($mensagem) > 2000) {
        $mensagemErro = 'A mensagem não pode exceder 2000 caracteres.';
    } else {
        try {
            // Adaptado para a estrutura do banco: tabela Feedback com campo Usuario
            // Incluindo o tipo de feedback na mensagem já que a tabela não tem campo Tipo separado
            $mensagemCompleta = "[Tipo: " . ucfirst($tipo) . "]\n\n" . $mensagem;
            
            // Preparar e executar inserção usando a estrutura correta do banco
            $stmt = $pdo->prepare("INSERT INTO Feedback (Usuario, Mensagem) 
                                   VALUES (:usuario_id, :mensagem)");
            $stmt->bindParam(':usuario_id', $usuarioID, PDO::PARAM_INT);
            $stmt->bindParam(':mensagem', $mensagemCompleta, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $mensagemSucesso = 'Feedback enviado com sucesso! Obrigado pela sua contribuição.';
                // Limpar campos do formulário
                $tipo = '';
                $mensagem = '';
            } else {
                $mensagemErro = 'Erro ao enviar feedback. Por favor, tente novamente.';
            }
        } catch (PDOException $e) {
            // Exibir erro detalhado para ajudar no debug
            $errorCode = $e->getCode();
            $errorMsg = $e->getMessage();
            
            // Log do erro completo
            error_log("Erro ao inserir feedback [Código: $errorCode]: " . $errorMsg);
            
            // Mensagens amigáveis baseadas no tipo de erro
            if (strpos($errorMsg, 'Table') !== false && (strpos($errorMsg, 'doesn\'t exist') !== false || strpos($errorMsg, 'doesn\'t exist') !== false)) {
                $mensagemErro = 'Erro: A tabela Feedback não existe no banco de dados. Por favor, execute o script SQL do projeto ou entre em contato com o administrador.';
            } elseif (strpos($errorMsg, 'foreign key') !== false || strpos($errorMsg, 'constraint') !== false || strpos($errorMsg, 'Usuario') !== false) {
                $mensagemErro = 'Erro: ID de usuário inválido. Por favor, faça login novamente.';
            } else {
                $mensagemErro = 'Erro ao enviar feedback: ' . htmlspecialchars($errorMsg) . 
                               '. Por favor, tente novamente ou entre em contato com o suporte.';
            }
        } catch (Exception $e) {
            $mensagemErro = 'Erro inesperado: ' . htmlspecialchars($e->getMessage()) . 
                           '. Por favor, tente novamente.';
            error_log("Erro inesperado ao inserir feedback: " . $e->getMessage());
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
  <title>Feedback - SMCPA</title>
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="/SMCPA/css/dashboard.css">
  <link rel="stylesheet" href="/SMCPA/css/feedback.css">
</head>
<body>
  <div class="dashboard-container">
    <!-- Sidebar (Menu Lateral) -->
    <aside class="sidebar">
      <div class="logo">
        <a href="<?= $isAdmin ? '/SMCPA/paginas/dashboard/dashboardadm.php' : '/SMCPA/paginas/dashboard/dashboard.php'; ?>">
          <img src="/SMCPA/imgs/logotrbf.png" alt="SMCPA Logo">
        </a>
      </div>

      <nav class="menu-lateral">
        <ul>
          <li class="item-menu">
            <a href="<?= $isAdmin ? '/SMCPA/paginas/dashboard/dashboardadm.php' : '/SMCPA/paginas/dashboard/dashboard.php'; ?>">
              <span class="icon"><i class="fa-solid fa-home"></i></span>
              <span class="txt-link">Home</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/cadastro/cadpraga.php">
              <span class="icon"><i class="bi bi-columns-gap"></i></span>
              <span class="txt-link">Cadastrar Pragas</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/cadastro/cadsurto.php">
              <span class="icon"><i class="bi bi-exclamation-triangle"></i></span>
              <span class="txt-link">Cadastrar Surtos</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/dashboard/filtros_pragas.php">
              <span class="icon"><i class="bi bi-funnel"></i></span>
              <span class="txt-link">Filtros de Pragas</span>
            </a>
          </li>
          <?php if ($isAdmin): ?>
          <li class="item-menu">
            <a href="/SMCPA/paginas/dashboard/filtros_usuarios.php">
              <span class="icon"><i class="bi bi-people"></i></span>
              <span class="txt-link">Filtros de Usuários</span>
            </a>
          </li>
          <?php endif; ?>
          <li class="item-menu">
            <a href="/SMCPA/paginas/dashboard/feedback.php">
              <span class="icon"><i class="bi bi-chat-dots"></i></span>
              <span class="txt-link">Feedback</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/dashboard/perfil.php">
              <span class="icon"><i class="bi bi-person-lines-fill"></i></span>
              <span class="txt-link">Conta</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="/SMCPA/paginas/login/logout.php">
              <span class="icon"><i class="bi bi-box-arrow-right"></i></span>
              <span class="txt-link">Sair</span>
            </a>
          </li>
        </ul>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <header class="topbar">
        <div class="left">
        </div>
        <div class="right d-flex align-items-center gap-3">
          <a href="../tutorial/tutorial.php" class="btn btn-outline-light">
            <i class="fa-solid fa-book"></i> Tutoriais
          </a>
          <a href="./perfil.php" style="text-decoration: none;">
            <img src="<?= htmlspecialchars($imagemPerfil); ?>" 
                 alt="Perfil do usuário" 
                 class="rounded-circle" 
                 style="width: 40px; height: 40px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); cursor: pointer;"
                 onerror="this.src='/SMCPA/imgs/logotrbf.png'">
          </a>
        </div>
      </header>

      <section class="content">
        <div class="feedback-container">
          <div class="feedback-header">
            <h1><i class="bi bi-chat-dots"></i> Envie seu Feedback</h1>
            <p>Sua opinião é muito importante para nós! Ajude-nos a melhorar o SMCPA.</p>
          </div>

          <?php if ($mensagemSucesso): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="bi bi-check-circle"></i> <?= htmlspecialchars($mensagemSucesso); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <?php if ($mensagemErro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($mensagemErro); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <div class="feedback-card">
            <form method="POST" action="feedback.php" id="formFeedback">
              <div class="mb-4">
                <label for="nome" class="form-label">
                  <i class="bi bi-person"></i> Nome
                </label>
                <input type="text" class="form-control" id="nome" value="<?= htmlspecialchars($nomeUsuario); ?>" disabled>
                <small class="form-text text-muted">Identificação automática do seu perfil</small>
              </div>

              <div class="mb-4">
                <label for="email" class="form-label">
                  <i class="bi bi-envelope"></i> Email
                </label>
                <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($emailUsuario); ?>" disabled>
                <small class="form-text text-muted">Usado para responder seu feedback, se necessário</small>
              </div>

              <div class="mb-4">
                <label for="tipo" class="form-label">
                  <i class="bi bi-tag"></i> Tipo de Feedback <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="tipo" name="tipo" required>
                  <option value="">Selecione o tipo de feedback</option>
                  <option value="sugestao" <?= (isset($tipo) && $tipo === 'sugestao') ? 'selected' : ''; ?>>💡 Sugestão</option>
                  <option value="problema" <?= (isset($tipo) && $tipo === 'problema') ? 'selected' : ''; ?>>🐛 Reportar Problema</option>
                  <option value="elogio" <?= (isset($tipo) && $tipo === 'elogio') ? 'selected' : ''; ?>>⭐ Elogio</option>
                  <option value="duvida" <?= (isset($tipo) && $tipo === 'duvida') ? 'selected' : ''; ?>>❓ Dúvida</option>
                  <option value="outro" <?= (isset($tipo) && $tipo === 'outro') ? 'selected' : ''; ?>>📝 Outro</option>
                </select>
              </div>

              <div class="mb-4">
                <label for="mensagem" class="form-label">
                  <i class="bi bi-chat-left-text"></i> Mensagem <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="mensagem" name="mensagem" rows="8" 
                          placeholder="Descreva seu feedback de forma detalhada. Quanto mais informações, melhor poderemos ajudá-lo!" 
                          required minlength="10"><?= isset($mensagem) ? htmlspecialchars($mensagem) : ''; ?></textarea>
                <small class="form-text text-muted">Mínimo de 10 caracteres</small>
                <div class="char-count">
                  <span id="charCount">0</span> / 2000 caracteres
                </div>
              </div>

              <div class="d-flex gap-2 justify-content-end">
                <a href="<?= $isAdmin ? 'dashboardadm.php' : 'dashboard.php'; ?>" class="btn btn-secondary">
                  <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" name="enviar_feedback" class="btn btn-primary">
                  <i class="bi bi-send"></i> Enviar Feedback
                </button>
              </div>
            </form>
          </div>

          <div class="feedback-info">
            <div class="info-card">
              <i class="bi bi-info-circle"></i>
              <h5>Como funciona?</h5>
              <p>Seu feedback será analisado pela equipe de desenvolvimento. Dependendo do tipo, podemos entrar em contato através do seu email cadastrado.</p>
            </div>
            <div class="info-card">
              <i class="bi bi-clock-history"></i>
              <h5>Tempo de Resposta</h5>
              <p>Nossa equipe revisa os feedbacks regularmente. Feedback sobre problemas críticos são priorizados.</p>
            </div>
            <div class="info-card">
              <i class="bi bi-shield-check"></i>
              <h5>Privacidade</h5>
              <p>Seus dados são mantidos em segurança e usados apenas para melhorar o sistema e responder seu feedback.</p>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Contador de caracteres
    const mensagem = document.getElementById('mensagem');
    const charCount = document.getElementById('charCount');
    
    if (mensagem && charCount) {
      mensagem.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        if (length > 2000) {
          charCount.style.color = '#dc3545';
          this.value = this.value.substring(0, 2000);
          charCount.textContent = 2000;
        } else if (length > 1800) {
          charCount.style.color = '#ffc107';
        } else {
          charCount.style.color = '#6c757d';
        }
      });
      
      // Atualizar contador ao carregar página
      charCount.textContent = mensagem.value.length;
    }

    // Limpar formulário após sucesso (se houver mensagem de sucesso)
    <?php if ($mensagemSucesso): ?>
      setTimeout(function() {
        document.getElementById('formFeedback').reset();
        document.getElementById('charCount').textContent = '0';
      }, 100);
    <?php endif; ?>
  </script>
</body>
</html>

