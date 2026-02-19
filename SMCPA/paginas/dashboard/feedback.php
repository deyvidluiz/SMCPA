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
$avaliacaoEstrelas = '';
$usabFacilidade = isset($_POST['usabilidade_facilidade']) ? (int) $_POST['usabilidade_facilidade'] : null;
$usabOrganizacao = isset($_POST['usabilidade_organizacao']) ? (int) $_POST['usabilidade_organizacao'] : null;
$usabRegistro = isset($_POST['usabilidade_registro']) ? (int) $_POST['usabilidade_registro'] : null;
$usabRelatorio = isset($_POST['usabilidade_relatorio']) ? (int) $_POST['usabilidade_relatorio'] : null;
$usabDecisao = isset($_POST['usabilidade_decisao']) ? (int) $_POST['usabilidade_decisao'] : null;
$usabUsaria = isset($_POST['usabilidade_usaria']) ? (int) $_POST['usabilidade_usaria'] : null;

if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_feedback'])) {
  $tipo = trim($_POST['tipo'] ?? '');
  $mensagem = trim($_POST['mensagem'] ?? '');
  $rawEstrelas = isset($_POST['avaliacao_estrelas']) ? trim((string) $_POST['avaliacao_estrelas']) : '';
  $avaliacaoEstrelas = null;
  if ($rawEstrelas !== '') {
    $raw = str_replace(',', '.', $rawEstrelas);
    if (is_numeric($raw)) {
      $v = (float) $raw;
      $v = max(1, min(5, $v));
      $avaliacaoEstrelas = round($v * 2) / 2;
    }
  }
  $usabFacilidade = isset($_POST['usabilidade_facilidade']) ? (int) $_POST['usabilidade_facilidade'] : null;
  $usabOrganizacao = isset($_POST['usabilidade_organizacao']) ? (int) $_POST['usabilidade_organizacao'] : null;
  $usabRegistro = isset($_POST['usabilidade_registro']) ? (int) $_POST['usabilidade_registro'] : null;
  $usabRelatorio = isset($_POST['usabilidade_relatorio']) ? (int) $_POST['usabilidade_relatorio'] : null;
  $usabDecisao = isset($_POST['usabilidade_decisao']) ? (int) $_POST['usabilidade_decisao'] : null;
  $usabUsaria = isset($_POST['usabilidade_usaria']) ? (int) $_POST['usabilidade_usaria'] : null;

  if (empty($usuarioID)) {
    $mensagemErro = 'Erro: Usuário não identificado. Por favor, faça login novamente.';
  } elseif (empty($tipo)) {
    $mensagemErro = 'Por favor, selecione o tipo de feedback.';
  } elseif ($avaliacaoEstrelas === null || $avaliacaoEstrelas < 1 || $avaliacaoEstrelas > 5) {
    $mensagemErro = 'Escolha uma nota nas estrelas (1 a 5).';
  } elseif (empty($mensagem)) {
    $mensagemErro = 'Por favor, preencha a mensagem.';
  } elseif (strlen($mensagem) < 10) {
    $mensagemErro = 'A mensagem deve ter pelo menos 10 caracteres.';
  } elseif (strlen($mensagem) > 2000) {
    $mensagemErro = 'A mensagem não pode exceder 2000 caracteres.';
  } else {
    try {
      $mensagemCompleta = "[Tipo: " . ucfirst($tipo) . "]\n\n" . $mensagem;
      $stmt = $pdo->prepare("
        INSERT INTO Feedback (Usuario, Mensagem, Avaliacao_Estrelas, Usabilidade_Facilidade, Usabilidade_Organizacao, Usabilidade_Registro, Usabilidade_Relatorio, Usabilidade_Decisao, Usabilidade_Usaria)
        VALUES (:usuario_id, :mensagem, :estrelas, :u_facilidade, :u_organizacao, :u_registro, :u_relatorio, :u_decisao, :u_usaria)
      ");
      $stmt->bindParam(':usuario_id', $usuarioID, PDO::PARAM_INT);
      $stmt->bindParam(':mensagem', $mensagemCompleta, PDO::PARAM_STR);
      $stmt->bindValue(':estrelas', $avaliacaoEstrelas >= 1 ? $avaliacaoEstrelas : null, PDO::PARAM_STR);
      $stmt->bindValue(':u_facilidade', ($usabFacilidade >= 1 && $usabFacilidade <= 5) ? $usabFacilidade : null, PDO::PARAM_INT);
      $stmt->bindValue(':u_organizacao', ($usabOrganizacao >= 1 && $usabOrganizacao <= 5) ? $usabOrganizacao : null, PDO::PARAM_INT);
      $stmt->bindValue(':u_registro', ($usabRegistro >= 1 && $usabRegistro <= 5) ? $usabRegistro : null, PDO::PARAM_INT);
      $stmt->bindValue(':u_relatorio', ($usabRelatorio >= 1 && $usabRelatorio <= 5) ? $usabRelatorio : null, PDO::PARAM_INT);
      $stmt->bindValue(':u_decisao', ($usabDecisao >= 1 && $usabDecisao <= 5) ? $usabDecisao : null, PDO::PARAM_INT);
      $stmt->bindValue(':u_usaria', ($usabUsaria >= 1 && $usabUsaria <= 5) ? $usabUsaria : null, PDO::PARAM_INT);
      if ($stmt->execute()) {
        $mensagemSucesso = 'Enviado. Obrigado.';
        $tipo = '';
        $mensagem = '';
        $avaliacaoEstrelas = null;
        $usabFacilidade = $usabOrganizacao = $usabRegistro = $usabRelatorio = $usabDecisao = $usabUsaria = null;
      } else {
        $mensagemErro = 'Erro ao enviar feedback. Por favor, tente novamente.';
      }
    } catch (PDOException $e) {
      error_log("Feedback: " . $e->getMessage());
      $mensagemErro = 'Erro ao enviar feedback. Verifique se o banco foi criado com database/bancodedados.sql ou execute database/migrate_feedback_legado.sql em bancos antigos.';
    }
  }
}

// ---------- ADMIN: listar feedbacks ----------
$feedbacks = [];
if ($isAdmin) {
  try {
    $stmt = $pdo->query("
      SELECT f.ID, f.Mensagem, f.Data_Envio, f.Usuario AS ID_Usuario,
             f.Avaliacao_Estrelas, f.Usabilidade_Facilidade, f.Usabilidade_Organizacao,
             f.Usabilidade_Registro, f.Usabilidade_Relatorio, f.Usabilidade_Decisao, f.Usabilidade_Usaria,
             COALESCE(u.usuario, f.Autor_Nome) AS nome_usuario,
             COALESCE(u.Email, f.Autor_Email) AS email_usuario,
             COALESCE(u.localizacao, f.Autor_Localizacao) AS localizacao_usuario,
             COALESCE(u.Data_Cadastro, f.Autor_Data_Cadastro) AS data_cadastro_usuario
      FROM Feedback f
      LEFT JOIN Usuarios u ON f.Usuario = u.ID
      ORDER BY f.Data_Envio DESC, f.ID DESC
    ");
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log("Feedback admin: " . $e->getMessage());
    try {
      $stmt = $pdo->query("
        SELECT f.ID, f.Mensagem, f.Data_Envio, f.Usuario AS ID_Usuario,
               COALESCE(u.usuario, f.Autor_Nome) AS nome_usuario,
               COALESCE(u.Email, f.Autor_Email) AS email_usuario,
               COALESCE(u.localizacao, f.Autor_Localizacao) AS localizacao_usuario,
               COALESCE(u.Data_Cadastro, f.Autor_Data_Cadastro) AS data_cadastro_usuario
        FROM Feedback f
        LEFT JOIN Usuarios u ON f.Usuario = u.ID
        ORDER BY f.Data_Envio DESC, f.ID DESC
      ");
      $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
      error_log("Feedback admin fallback: " . $e2->getMessage());
      try {
        $stmt = $pdo->query("
          SELECT f.ID, f.Mensagem, f.Data_Envio, f.Usuario AS ID_Usuario,
                 f.Avaliacao_Estrelas, f.Usabilidade_Facilidade, f.Usabilidade_Organizacao,
                 f.Usabilidade_Registro, f.Usabilidade_Relatorio, f.Usabilidade_Decisao, f.Usabilidade_Usaria,
                 u.usuario AS nome_usuario, u.Email AS email_usuario,
                 u.localizacao AS localizacao_usuario, u.Data_Cadastro AS data_cadastro_usuario
          FROM Feedback f
          INNER JOIN Usuarios u ON f.Usuario = u.ID
          ORDER BY f.Data_Envio DESC, f.ID DESC
        ");
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e3) {
        error_log("Feedback admin fallback 2: " . $e3->getMessage());
      }
    }
  }
}

// ---------- Média de avaliação (estrelas) - admin e usuário ----------
$mediaEstrelas = null;
$totalAvaliacoes = 0;
try {
  $stmt = $pdo->query("SELECT AVG(Avaliacao_Estrelas) AS media, COUNT(Avaliacao_Estrelas) AS total FROM Feedback WHERE Avaliacao_Estrelas IS NOT NULL AND Avaliacao_Estrelas BETWEEN 1 AND 5");
  if ($stmt) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['total'] > 0) {
      $mediaEstrelas = (float) $row['media'];
      $totalAvaliacoes = (int) $row['total'];
    }
  }
} catch (PDOException $e) {
  // Coluna pode não existir ainda
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

/** Renderiza HTML das estrelas conforme a média (0–5). Retorna array: html estrelas, texto número. */
function renderizarMediaEstrelas($media)
{
  if ($media === null || $media < 0) {
    return ['html' => '', 'texto' => '-'];
  }
  $media = min(5, max(0, (float) $media));
  $cheias = (int) floor($media);
  $resto = $media - $cheias;
  $meia = ($resto >= 0.25 && $resto < 0.75) ? 1 : (($resto >= 0.75) ? 1 : 0);
  if ($resto >= 0.75) {
    $cheias += 1;
    $meia = 0;
  } elseif ($meia) {
    $meia = 1;
  }
  $vazias = 5 - $cheias - $meia;
  $html = '';
  for ($i = 0; $i < $cheias; $i++) {
    $html .= '<i class="bi bi-star-fill text-warning" aria-hidden="true"></i>';
  }
  if ($meia) {
    $html .= '<i class="bi bi-star-half text-warning" aria-hidden="true"></i>';
  }
  for ($i = 0; $i < $vazias; $i++) {
    $html .= '<i class="bi bi-star text-warning" aria-hidden="true"></i>';
  }
  return ['html' => $html, 'texto' => number_format($media, 1, ',', '')];
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
              <h1><i class="bi bi-chat-dots-fill"></i> Feedbacks</h1>
              <p>O que os usuários enviaram.</p>
            </div>

            <?php
            $mediaRender = $mediaEstrelas !== null ? renderizarMediaEstrelas($mediaEstrelas) : ['html' => '', 'texto' => '-'];
            if ($mediaEstrelas !== null):
            ?>
            <div class="media-avaliacao-card">
              <div class="media-avaliacao-titulo">Média das notas</div>
              <div class="media-avaliacao-stars" aria-label="Média: <?= $mediaRender['texto'] ?> de 5">
                <?= $mediaRender['html'] ?>
              </div>
              <div class="media-avaliacao-numero"><?= $mediaRender['texto'] ?> <span class="text-muted">/ 5</span></div>
              <div class="media-avaliacao-total">(<?= $totalAvaliacoes ?> <?= $totalAvaliacoes === 1 ? 'avaliação' : 'avaliações' ?>)</div>
            </div>
            <?php endif; ?>

            <?php if (empty($feedbacks)): ?>
              <div class="alert alert-info">
                Ainda não há feedbacks.
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
                    <?php if (!empty($fb['localizacao_usuario'])): ?>
                    <p class="mb-1 text-muted small">
                      <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($fb['localizacao_usuario']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($fb['data_cadastro_usuario'])): ?>
                    <p class="mb-1 text-muted small">
                      <i class="bi bi-calendar-check"></i> Cadastro em <?= date('d/m/Y', strtotime($fb['data_cadastro_usuario'])) ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-2">
                      <span class="badge bg-secondary"><?= htmlspecialchars($tipoFb) ?></span>
                      <span class="text-muted ms-2 small"><i class="bi bi-calendar3"></i> <?= $dataFb ?></span>
                      <?php if (isset($fb['Avaliacao_Estrelas']) && $fb['Avaliacao_Estrelas'] !== null && $fb['Avaliacao_Estrelas'] !== ''): ?>
                        <?php
                        $notaFb = (float) $fb['Avaliacao_Estrelas'];
                        $renderFb = renderizarMediaEstrelas($notaFb);
                        ?>
                        <span class="ms-2 avaliacao-admin" title="Nota: <?= $renderFb['texto'] ?>/5">
                          <?= $renderFb['html'] ?>
                        </span>
                        <span class="ms-1 small text-muted"><?= $renderFb['texto'] ?>/5</span>
                      <?php endif; ?>
                    </p>
                    <?php
                    $temUsabilidade = !empty($fb['Usabilidade_Facilidade']) || !empty($fb['Usabilidade_Organizacao']) || !empty($fb['Usabilidade_Registro']) || !empty($fb['Usabilidade_Relatorio']) || !empty($fb['Usabilidade_Decisao']) || !empty($fb['Usabilidade_Usaria']);
                    if ($temUsabilidade):
                      $perguntas = [
                        'Usabilidade_Facilidade' => 'O sistema é fácil de usar?',
                        'Usabilidade_Organizacao' => 'Informações organizadas de forma clara?',
                        'Usabilidade_Registro' => 'Processo de registro simples?',
                        'Usabilidade_Relatorio' => 'Relatório facilita a análise das ocorrências?',
                        'Usabilidade_Decisao' => 'Sistema auxilia na tomada de decisão?',
                        'Usabilidade_Usaria' => 'Utilizaria em situação real?',
                      ];
                    ?>
                    <div class="feedback-usabilidade-admin mb-2">
                      <?php foreach ($perguntas as $col => $txt): ?>
                        <?php if (isset($fb[$col]) && $fb[$col] !== null && $fb[$col] !== ''): ?>
                          <div class="usab-item small"><span class="usab-pergunta"><?= htmlspecialchars($txt) ?></span> <strong><?= (int)$fb[$col] ?>/5</strong></div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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
              <h1><i class="bi bi-chat-dots"></i> Feedback</h1>
              <p>Conte o que achou do SMCPA.</p>
            </div>

            <?php
            $mediaRenderUser = $mediaEstrelas !== null ? renderizarMediaEstrelas($mediaEstrelas) : null;
            if ($mediaRenderUser !== null):
            ?>
            <div class="media-avaliacao-card media-avaliacao-user">
              <span class="media-avaliacao-label"><i class="bi bi-star-fill"></i> Média das notas:</span>
              <span class="media-avaliacao-stars media-avaliacao-stars-inline" aria-label="Média: <?= $mediaRenderUser['texto'] ?> de 5"><?= $mediaRenderUser['html'] ?></span>
              <span class="media-avaliacao-numero-inline"><?= $mediaRenderUser['texto'] ?></span>
              <span class="text-muted small">(<?= $totalAvaliacoes ?> <?= $totalAvaliacoes === 1 ? 'avaliação' : 'avaliações' ?>)</span>
            </div>
            <?php endif; ?>

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

                <?php
                $formNota = '';
                if ($avaliacaoEstrelas !== null && $avaliacaoEstrelas !== '' && is_numeric($avaliacaoEstrelas)) {
                  $v = max(1, min(5, (float) $avaliacaoEstrelas));
                  $v = round($v * 2) / 2;
                  $formNota = str_replace('.', ',', (string) $v);
                }
                ?>
                <div class="mb-4 avaliacao-estrelas-wrap">
                  <label class="form-label">Sua nota (1 a 5) <span class="text-danger">*</span></label>
                  <div class="avaliacao-nota-linha">
                    <div class="estrelas" id="estrelas" role="group" aria-label="Estrelas 1 a 5">
                      <?php for ($i = 0; $i < 5; $i++): ?>
                        <button type="button" class="estrela" data-index="<?= $i ?>" aria-label="Estrela <?= $i + 1 ?>"><i class="bi bi-star"></i></button>
                      <?php endfor; ?>
                    </div>
                    <span id="nota-exibida" class="nota-exibida"><?= htmlspecialchars($formNota) ?></span>
                  </div>
                  <input type="hidden" name="avaliacao_estrelas" id="avaliacao_estrelas" value="<?= htmlspecialchars($formNota) ?>" required>
                </div>

                <div class="feedback-questionario mb-4">
                  <label class="form-label">Perguntas sobre o uso (opcional)</label>
                  <p class="form-text text-muted mb-3">Escala de 1 a 5.</p>
                  <div class="questionario-lista">
                    <div class="pergunta-item">
                      <span class="pergunta-texto">O sistema é fácil de usar?</span>
                      <div class="escala-1-5" role="group" aria-label="O sistema é fácil de usar?">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                          <label class="escala-opcao"><input type="radio" name="usabilidade_facilidade" value="<?= $v ?>" <?= ($usabFacilidade === $v) ? 'checked' : '' ?>> <span><?= $v ?></span></label>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <div class="pergunta-item">
                      <span class="pergunta-texto">As informações estão organizadas de forma clara?</span>
                      <div class="escala-1-5" role="group" aria-label="As informações estão organizadas de forma clara?">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                          <label class="escala-opcao"><input type="radio" name="usabilidade_organizacao" value="<?= $v ?>" <?= ($usabOrganizacao === $v) ? 'checked' : '' ?>> <span><?= $v ?></span></label>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <div class="pergunta-item">
                      <span class="pergunta-texto">O processo de registro é simples?</span>
                      <div class="escala-1-5" role="group" aria-label="O processo de registro é simples?">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                          <label class="escala-opcao"><input type="radio" name="usabilidade_registro" value="<?= $v ?>" <?= ($usabRegistro === $v) ? 'checked' : '' ?>> <span><?= $v ?></span></label>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <div class="pergunta-item">
                      <span class="pergunta-texto">O relatório facilita a análise das ocorrências?</span>
                      <div class="escala-1-5" role="group" aria-label="O relatório facilita a análise das ocorrências?">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                          <label class="escala-opcao"><input type="radio" name="usabilidade_relatorio" value="<?= $v ?>" <?= ($usabRelatorio === $v) ? 'checked' : '' ?>> <span><?= $v ?></span></label>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <div class="pergunta-item">
                      <span class="pergunta-texto">O sistema auxilia na tomada de decisão?</span>
                      <div class="escala-1-5" role="group" aria-label="O sistema auxilia na tomada de decisão?">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                          <label class="escala-opcao"><input type="radio" name="usabilidade_decisao" value="<?= $v ?>" <?= ($usabDecisao === $v) ? 'checked' : '' ?>> <span><?= $v ?></span></label>
                        <?php endfor; ?>
                      </div>
                    </div>
                    <div class="pergunta-item">
                      <span class="pergunta-texto">Você utilizaria essa ferramenta em situação real?</span>
                      <div class="escala-1-5" role="group" aria-label="Você utilizaria essa ferramenta em situação real?">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                          <label class="escala-opcao"><input type="radio" name="usabilidade_usaria" value="<?= $v ?>" <?= ($usabUsaria === $v) ? 'checked' : '' ?>> <span><?= $v ?></span></label>
                        <?php endfor; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mb-4">
                  <label for="mensagem" class="form-label"><i class="bi bi-chat-left-text"></i> Mensagem <span class="text-danger">*</span></label>
                  <textarea class="form-control" id="mensagem" name="mensagem" rows="6"
                    placeholder="Escreva aqui o que quiser."
                    required minlength="10"><?= htmlspecialchars($mensagem); ?></textarea>
                  <small class="form-text text-muted">Mín. 10 caracteres</small>
                  <div class="char-count"><span id="charCount"><?= strlen($mensagem) ?></span> / 2000 caracteres</div>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                  <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
                  <button type="submit" name="enviar_feedback" class="btn btn-primary">Enviar</button>
                </div>
              </form>
            </div>

            <div class="feedback-info">
              <div class="info-card">
                <i class="bi bi-info-circle"></i>
                <h5>O que acontece?</h5>
                <p>O feedback é guardado e pode ser usado para melhorar o sistema. Se precisar, entramos em contato pelo seu email.</p>
              </div>
              <div class="info-card">
                <i class="bi bi-shield-check"></i>
                <h5>Seus dados</h5>
                <p>Só usamos para melhorar o SMCPA e, se for o caso, responder você.</p>
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
      (function() {
        var estrelasEl = document.getElementById('estrelas');
        var inputNota = document.getElementById('avaliacao_estrelas');
        var notaExibida = document.getElementById('nota-exibida');
        if (!estrelasEl || !inputNota) return;
        var botoes = estrelasEl.querySelectorAll('.estrela');

        function normalizarNota(v) {
          v = parseFloat(v, 10);
          if (isNaN(v) || v < 1) return 0;
          if (v > 5) return 5;
          return Math.round(v * 2) / 2;
        }

        function atualizarIcones(v) {
          v = normalizarNota(v);
          if (v === 0) {
            botoes.forEach(function(btn, i) {
              var icon = btn.querySelector('i');
              if (icon) icon.className = 'bi bi-star';
              btn.classList.remove('ativa');
            });
            return;
          }
          var cheias = Math.floor(v);
          var meia = (v - cheias) >= 0.5 ? 1 : 0;
          botoes.forEach(function(btn, i) {
            var icon = btn.querySelector('i');
            if (!icon) return;
            if (i < cheias) {
              icon.className = 'bi bi-star-fill';
              btn.classList.add('ativa');
            } else if (meia && i === cheias) {
              icon.className = 'bi bi-star-half';
              btn.classList.add('ativa');
            } else {
              icon.className = 'bi bi-star';
              btn.classList.remove('ativa');
            }
          });
        }

        function setarNota(val) {
          var v = normalizarNota(val);
          if (v < 1) v = 0;
          var texto = v > 0 ? (v % 1 === 0 ? String(v) : String(v).replace('.', ',')) : '';
          inputNota.value = texto;
          if (notaExibida) notaExibida.textContent = texto;
          atualizarIcones(v > 0 ? v : 0);
        }

        botoes.forEach(function(btn, i) {
          btn.addEventListener('click', function(ev) {
            var rect = btn.getBoundingClientRect();
            var x = ev.clientX - rect.left;
            var valor = x < rect.width / 2 ? (i + 0.5) : (i + 1);
            valor = Math.max(1, Math.min(5, valor));
            valor = Math.round(valor * 2) / 2;
            setarNota(valor);
          });
        });

        var inicial = normalizarNota(inputNota.value);
        if (inicial >= 1 && inicial <= 5) {
          atualizarIcones(inicial);
          if (notaExibida) notaExibida.textContent = inicial % 1 === 0 ? String(inicial) : String(inicial).replace('.', ',');
        }
      })();
      var mensagemEl = document.getElementById('mensagem');
      var charCountEl = document.getElementById('charCount');
      if (mensagemEl && charCountEl) {
        mensagemEl.addEventListener('input', function() {
          var len = this.value.length;
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
          var f = document.getElementById('formFeedback');
          if (f) {
            f.reset();
            if (document.getElementById('charCount')) document.getElementById('charCount').textContent = '0';
            var inp = document.getElementById('avaliacao_estrelas');
            if (inp) inp.value = '';
            var notaExibida = document.getElementById('nota-exibida');
            if (notaExibida) notaExibida.textContent = '';
            var es = document.getElementById('estrelas');
            if (es) {
              es.querySelectorAll('.estrela').forEach(function(btn) {
                btn.classList.remove('ativa');
                var icon = btn.querySelector('i');
                if (icon) icon.className = 'bi bi-star';
              });
            }
          }
        }, 100);
      <?php endif; ?>
    </script>
  <?php endif; ?>
</body>

</html>