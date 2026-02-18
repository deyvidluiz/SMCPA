<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id']) && !isset($_SESSION['logado'])) {
  header("Location: /SMCPA/paginas/login/login.php");
  exit;
}

$usuarioID = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;

require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');

$db = new Database();
$pdo = $db->conexao();

$isAdmin = false;
if (isset($_SESSION['is_admin'])) {
  $isAdmin = $_SESSION['is_admin'] == 1;
} else {
  try {
    $st = $pdo->prepare("SELECT is_admin FROM Usuarios WHERE id = :id");
    $st->bindParam(':id', $usuarioID, PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $isAdmin = ($row && isset($row['is_admin']) && $row['is_admin'] == 1);
    $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
  } catch (PDOException $e) {
    $isAdmin = false;
  }
}

// Buscar imagem do perfil
$imagemPerfil = null;
if ($usuarioID) {
  try {
    $st = $pdo->prepare("SELECT Imagem FROM Usuarios WHERE id = :id");
    $st->bindParam(':id', $usuarioID, PDO::PARAM_INT);
    $st->execute();
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['Imagem'])) {
      $imagemPerfil = '/uploads/usuarios/' . $r['Imagem'];
    }
  } catch (PDOException $e) {
  }
}
if (!$imagemPerfil) {
  $imagemPerfil = '/SMCPA/imgs/logotrbf.png';
}

// ---------- USUÁRIO: enviar feedback ----------
$mensagemSucesso = '';
$mensagemErro = '';
$tipo = '';
$mensagem = '';

if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_feedback'])) {
  $tipo = trim($_POST['tipo'] ?? '');
  $mensagem = trim($_POST['mensagem'] ?? '');

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
      $mensagemCompleta = "[Tipo: " . ucfirst($tipo) . "]\n\n" . $mensagem;
      $stmt = $pdo->prepare("INSERT INTO Feedback (Usuario, Mensagem) VALUES (:usuario_id, :mensagem)");
      $stmt->bindParam(':usuario_id', $usuarioID, PDO::PARAM_INT);
      $stmt->bindParam(':mensagem', $mensagemCompleta, PDO::PARAM_STR);
      if ($stmt->execute()) {
        $mensagemSucesso = 'Feedback enviado com sucesso! Obrigado pela sua contribuição.';
        $tipo = '';
        $mensagem = '';
      } else {
        $mensagemErro = 'Erro ao enviar feedback. Por favor, tente novamente.';
      }
    } catch (PDOException $e) {
      error_log("Feedback: " . $e->getMessage());
      $mensagemErro = 'Erro ao enviar feedback. Por favor, tente novamente.';
    }
  }
}

// ---------- ADMIN: listar feedbacks ----------
$feedbacks = [];
if ($isAdmin) {
  try {
    $stmt = $pdo->query("
      SELECT f.ID, f.Mensagem, f.Data_Envio, f.Usuario AS ID_Usuario,
             u.usuario AS nome_usuario, u.Email AS email_usuario
      FROM Feedback f
      INNER JOIN Usuarios u ON f.Usuario = u.ID
      ORDER BY f.Data_Envio DESC, f.ID DESC
    ");
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log("Feedback admin: " . $e->getMessage());
  }
}

// Extrair tipo da mensagem (formato: [Tipo: X])
function extrairTipoFeedback($msg)
{
  if (preg_match('/^\[Tipo:\s*(.+?)\]/i', $msg, $m)) {
    return trim($m[1]);
  }
  return 'Outro';
}

function extrairMensagemSemTipo($msg)
{
  if (preg_match('/^\[Tipo:\s*.+?\]\s*\n+/s', $msg, $m)) {
    return trim(substr($msg, strlen($m[0])));
  }
  return trim($msg);
}

// Dados do usuário para o formulário (apenas quando não é admin)
$nomeUsuario = '';
$emailUsuario = '';
if (!$isAdmin && $usuarioID) {
  try {
    $st = $pdo->prepare("SELECT usuario, email FROM Usuarios WHERE id = :id");
    $st->bindParam(':id', $usuarioID, PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $nomeUsuario = $row['usuario'] ?? '';
      $emailUsuario = $row['email'] ?? '';
    }
  } catch (PDOException $e) {
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
  <title><?= $isAdmin ? 'Relatório de Feedbacks' : 'Feedback' ?> - SMCPA</title>
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="/SMCPA/css/dashboard.css">
  <link rel="stylesheet" href="/SMCPA/css/feedback.css">
</head>

<body>
  <div class="dashboard-container">
    <?php include_once(BASE_URL . '/includes/sidebar.php'); ?>

    <main class="main-content">
      <header class="topbar">
        <div class="left"></div>
        <div class="right d-flex align-items-center gap-3">
          <a href="../tutorial/tutorial.php" class="btn btn-outline-light">
            <i class="fa-solid fa-book"></i> Tutoriais
          </a>
          <a href="./perfil.php" style="text-decoration: none;">
            <img src="<?= htmlspecialchars($imagemPerfil); ?>" alt="Perfil" class="rounded-circle"
              style="width: 40px; height: 40px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); cursor: pointer;"
              onerror="this.src='/SMCPA/imgs/logotrbf.png'">
          </a>
        </div>
      </header>

      <section class="content">
        <?php if ($isAdmin): ?>
          <!-- ADMIN: Grade de feedbacks -->
          <div class="feedback-admin-container">
            <div class="feedback-header">
              <h1><i class="bi bi-chat-dots-fill"></i> Relatório de Feedbacks</h1>
              <p>Feedbacks enviados pelos usuários do sistema.</p>
            </div>

            <?php if (empty($feedbacks)): ?>
              <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Nenhum feedback recebido até o momento.
              </div>
            <?php else: ?>
              <div class="dashboard-grid feedback-grid-admin">
                <?php foreach ($feedbacks as $fb): ?>
                  <?php
                  $tipoFb = extrairTipoFeedback($fb['Mensagem']);
                  $mensagemFb = extrairMensagemSemTipo($fb['Mensagem']);
                  $dataFb = !empty($fb['Data_Envio']) ? date('d/m/Y', strtotime($fb['Data_Envio'])) : '-';
                  ?>
                  <div class="dashboard-item feedback-card-admin" style="border-left: 4px solid #16a34a;">
                    <h5 class="mb-2"><i class="bi bi-person-circle text-primary"></i> <?= htmlspecialchars($fb['nome_usuario'] ?? 'Usuário') ?></h5>
                    <p class="mb-1 text-muted small">
                      <i class="bi bi-envelope"></i> <?= htmlspecialchars($fb['email_usuario'] ?? '-') ?>
                    </p>
                    <p class="mb-2">
                      <span class="badge bg-secondary"><?= htmlspecialchars($tipoFb) ?></span>
                      <span class="text-muted ms-2 small"><i class="bi bi-calendar3"></i> <?= $dataFb ?></span>
                    </p>
                    <div class="feedback-mensagem">
                      <p class="mb-0"><?= nl2br(htmlspecialchars($mensagemFb)) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <!-- USUÁRIO: Formulário de feedback -->
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
                  <label for="nome" class="form-label"><i class="bi bi-person"></i> Nome</label>
                  <input type="text" class="form-control" id="nome" value="<?= htmlspecialchars($nomeUsuario); ?>" disabled>
                  <small class="form-text text-muted">Identificação automática do seu perfil</small>
                </div>
                <div class="mb-4">
                  <label for="email" class="form-label"><i class="bi bi-envelope"></i> Email</label>
                  <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($emailUsuario); ?>" disabled>
                  <small class="form-text text-muted">Usado para responder seu feedback, se necessário</small>
                </div>
                <div class="mb-4">
                  <label for="tipo" class="form-label"><i class="bi bi-tag"></i> Tipo de Feedback <span class="text-danger">*</span></label>
                  <select class="form-select" id="tipo" name="tipo" required>
                    <option value="">Selecione o tipo de feedback</option>
                    <option value="sugestao" <?= $tipo === 'sugestao' ? 'selected' : ''; ?>>💡 Sugestão</option>
                    <option value="problema" <?= $tipo === 'problema' ? 'selected' : ''; ?>>🐛 Reportar Problema</option>
                    <option value="elogio" <?= $tipo === 'elogio' ? 'selected' : ''; ?>>⭐ Elogio</option>
                    <option value="duvida" <?= $tipo === 'duvida' ? 'selected' : ''; ?>>❓ Dúvida</option>
                    <option value="outro" <?= $tipo === 'outro' ? 'selected' : ''; ?>>📝 Outro</option>
                  </select>
                </div>
                <div class="mb-4">
                  <label for="mensagem" class="form-label"><i class="bi bi-chat-left-text"></i> Mensagem <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="mensagem" name="mensagem" rows="8"
                    placeholder="Descreva seu feedback de forma detalhada. Quanto mais informações, melhor poderemos ajudá-lo!"
                    required minlength="10"><?= htmlspecialchars($mensagem); ?></textarea>
                  <small class="form-text text-muted">Mínimo de 10 caracteres</small>
                  <div class="char-count"><span id="charCount"><?= strlen($mensagem) ?></span> / 2000 caracteres</div>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                  <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                  <button type="submit" name="enviar_feedback" class="btn btn-primary"><i class="bi bi-send"></i> Enviar Feedback</button>
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
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/SMCPA/js/menu.js"></script>
  <?php if (!$isAdmin): ?>
    <script>
      const mensagemEl = document.getElementById('mensagem');
      const charCountEl = document.getElementById('charCount');
      if (mensagemEl && charCountEl) {
        mensagemEl.addEventListener('input', function() {
          let len = this.value.length;
          if (len > 2000) {
            this.value = this.value.substring(0, 2000);
            len = 2000;
          }
          charCountEl.textContent = len;
          charCountEl.style.color = len > 1800 ? (len >= 2000 ? '#dc3545' : '#ffc107') : '#6c757d';
        });
      }
      <?php if ($mensagemSucesso): ?>
        setTimeout(function() {
          const f = document.getElementById('formFeedback');
          if (f) {
            f.reset();
            document.getElementById('charCount').textContent = '0';
          }
        }, 100);
      <?php endif; ?>
    </script>
  <?php endif; ?>
</body>

</html>