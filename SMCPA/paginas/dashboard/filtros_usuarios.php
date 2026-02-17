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

// Headers para prevenir cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Incluir arquivos de conexão
require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');

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

// Apenas administradores podem acessar
if (!$isAdmin) {
  header("Location: ../login/login.php?erro=acesso_negado");
  exit;
}

// Processar exclusão de usuário (somente administradores)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
  if (!$isAdmin) {
    header('Location: filtros_usuarios.php?erro=acesso_negado');
    exit;
  }

  $delUserId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
  if ($delUserId > 0) {
    // Evitar que admin delete a própria conta a partir desta interface
    if ($delUserId == $usuarioID) {
      header('Location: filtros_usuarios.php?erro=nao_possivel_excluir_proprio');
      exit;
    }

    try {
      // Primeiro: excluir todas as pragas (originais + histórico) associadas a este usuário
      $stmtPragas = $pdo->prepare("SELECT ID FROM Pragas_Surtos WHERE ID_Usuario = :uid");
      $stmtPragas->bindParam(':uid', $delUserId, PDO::PARAM_INT);
      $stmtPragas->execute();
      $pragasOriginais = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);

      foreach ($pragasOriginais as $orig) {
        $origId = $orig['ID'];

        // Buscar imagens de todas as entradas relacionadas (original + atualizações)
        $stmtImgs = $pdo->prepare("SELECT Imagem_Not_Null FROM Pragas_Surtos WHERE ID = :id OR ID_Praga_Original = :id");
        $stmtImgs->bindParam(':id', $origId, PDO::PARAM_INT);
        $stmtImgs->execute();
        $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($imgs as $im) {
          if (!empty($im['Imagem_Not_Null'])) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pragas/' . $im['Imagem_Not_Null'];
            if (file_exists($filePath)) {
              @unlink($filePath);
            }
          }
        }

        // Excluir registros originais e históricos
        $stmtDelPr = $pdo->prepare("DELETE FROM Pragas_Surtos WHERE ID = :id OR ID_Praga_Original = :id");
        $stmtDelPr->bindParam(':id', $origId, PDO::PARAM_INT);
        $stmtDelPr->execute();
      }

      // Em segundo lugar: remover quaisquer registros de praga que ainda possam ter ID_Usuario = delUserId
      $stmtCleanup = $pdo->prepare("SELECT Imagem_Not_Null FROM Pragas_Surtos WHERE ID_Usuario = :uid");
      $stmtCleanup->bindParam(':uid', $delUserId, PDO::PARAM_INT);
      $stmtCleanup->execute();
      $leftoverImgs = $stmtCleanup->fetchAll(PDO::FETCH_ASSOC);
      foreach ($leftoverImgs as $li) {
        if (!empty($li['Imagem_Not_Null'])) {
          $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pragas/' . $li['Imagem_Not_Null'];
          if (file_exists($filePath)) {
            @unlink($filePath);
          }
        }
      }
      $stmtDelAll = $pdo->prepare("DELETE FROM Pragas_Surtos WHERE ID_Usuario = :uid");
      $stmtDelAll->bindParam(':uid', $delUserId, PDO::PARAM_INT);
      $stmtDelAll->execute();

      // Buscar imagem do usuário e remover
      $stmtImg = $pdo->prepare("SELECT Imagem FROM Usuarios WHERE id = :id");
      $stmtImg->bindParam(':id', $delUserId, PDO::PARAM_INT);
      $stmtImg->execute();
      $rowImg = $stmtImg->fetch(PDO::FETCH_ASSOC);
      if ($rowImg && !empty($rowImg['Imagem'])) {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/usuarios/' . $rowImg['Imagem'];
        if (file_exists($filePath)) {
          @unlink($filePath);
        }
      }

      // Finalmente: excluir o usuário
      $stmtDel = $pdo->prepare("DELETE FROM Usuarios WHERE id = :id");
      $stmtDel->bindParam(':id', $delUserId, PDO::PARAM_INT);
      $stmtDel->execute();
      header('Location: filtros_usuarios.php?msg=usuario_excluido');
      exit;
    } catch (PDOException $e) {
      header('Location: filtros_usuarios.php?erro=erro_excluir');
      exit;
    }
  }
}

// Buscar imagem do perfil do usuário
$imagemPerfil = null;
if ($usuarioID) {
  try {
    $stmtImagem = $pdo->prepare("SELECT Imagem FROM Usuarios WHERE id = :id");
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

// ================== PESQUISA DE USUÁRIOS ==================
if (isset($_POST['procurar'])) {
  $pesquisa = $_POST['procurar'];
} else {
  $pesquisa = '';
}

// Criar a consulta SQL com parâmetro preparado para USUÁRIOS
$sql = "SELECT id, usuario, email, senha, data_cadastro, Imagem, is_admin 
        FROM Usuarios 
        WHERE usuario LIKE :pesquisa OR email LIKE :pesquisaEmail
        ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$pesquisaParam = "%$pesquisa%";
$stmt->bindValue(':pesquisa', $pesquisaParam, PDO::PARAM_STR);
$stmt->bindValue(':pesquisaEmail', $pesquisaParam, PDO::PARAM_STR);
$stmt->execute();

// Recupera os dados dos usuários
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="/SMCPA/css/dashboard.css">
  <title>Filtros de Usuários - SMCPA</title>
</head>

<body>
  <div class="dashboard-container">
    <?php include_once(BASE_URL . '/includes/sidebar.php'); ?>

    <main class="main-content main-content-filtros">
      <header class="topbar topbar-filtros">
        <div class="left"></div>
        <div class="right d-flex align-items-center gap-2">
          <a href="./perfil.php" class="topbar-perfil-link">
            <img src="<?= htmlspecialchars($imagemPerfil); ?>" alt="Perfil" class="rounded-circle topbar-avatar" onerror="this.src='/SMCPA/imgs/logotrbf.png'">
          </a>
        </div>
      </header>
      <div class="content content-filtros">
    <div class="tabela-container tabela-container-filtros">
      <div class="filtros-pragas-header filtros-usuarios-header">
        <h2 class="filtros-titulo"><i class="bi bi-people-fill"></i> Filtros de Usuários</h2>
        <form class="filtros-form" action="filtros_usuarios.php" method="post">
          <div class="filtros-busca">
            <input class="filtros-input" type="search" name="procurar" placeholder="Nome ou e-mail..." value="<?= htmlspecialchars($pesquisa); ?>" aria-label="Procurar" />
            <button class="filtros-btn filtros-btn-buscar" type="submit"><i class="bi bi-search"></i><span>Procurar</span></button>
            <?php if (!empty($pesquisa)): ?>
              <a href="filtros_usuarios.php" class="filtros-btn filtros-btn-limpar"><i class="bi bi-x-circle"></i><span>Limpar</span></a>
            <?php endif; ?>
          </div>
        </form>
        <p class="filtros-total">Total: <strong><?= count($dados); ?></strong> usuário(s)</p>
      </div>

      <div class="container filtros-container">

        <?php if (!empty($dados)): ?>
          <div class="row g-4">
            <?php foreach ($dados as $user): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card card-usuario h-100 shadow-sm">
                  <div class="card-body text-center">
                    <div class="mb-3">
                      <?php if (!empty($user['Imagem'])): ?>
                        <img src="/uploads/usuarios/<?= htmlspecialchars($user['Imagem']); ?>"
                          class="usuario-imagem"
                          alt="Foto de perfil"
                          onerror="this.src='/SMCPA/imgs/logotrbf.png'">
                      <?php else: ?>
                        <img src="/SMCPA/imgs/logotrbf.png"
                          class="usuario-imagem"
                          alt="Sem foto">
                      <?php endif; ?>
                    </div>

                    <h5 class="card-title">
                      <?= htmlspecialchars($user['usuario']); ?>
                      <?php if (!empty($user['is_admin']) && $user['is_admin'] == 1): ?>
                        <span class="badge badge-admin ms-2">Admin</span>
                      <?php endif; ?>
                    </h5>

                    <p class="card-text">
                      <small class="text-muted">
                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']); ?><br>
                        <i class="bi bi-calendar"></i> Cadastrado em: <?= date('d/m/Y', strtotime($user['data_cadastro'])); ?><br>
                        <i class="bi bi-shield-lock"></i> Senha: <?= '••••' . substr(htmlspecialchars($user['senha'] ?? 'N/A'), -4); ?>
                      </small>
                    </p>
                  </div>

                  <div class="card-footer bg-transparent border-top-0">
                    <div class="d-grid gap-2">
                      <a href="perfil.php?id=<?= $user['id']; ?>"
                        class="btn btn-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver Detalhes
                      </a>
                      <?php if ($isAdmin): ?>
                        <form method="post" onsubmit="return confirm('Confirma a exclusão deste usuário? Esta ação não pode ser desfeita.');">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                          <button type="submit" class="btn btn-danger btn-sm mt-2">
                            <i class="bi bi-trash"></i> Excluir Usuário
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info text-center">
            <i class="bi bi-info-circle"></i>
            <?php if (!empty($pesquisa)): ?>
              Nenhum usuário encontrado com o termo "<strong><?= htmlspecialchars($pesquisa); ?></strong>".
            <?php else: ?>
              Nenhum usuário cadastrado no sistema.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/SMCPA/js/menu.js"></script>
</body>

</html>