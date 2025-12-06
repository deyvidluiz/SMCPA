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

// Apenas administradores podem acessar
if (!$isAdmin) {
    header("Location: ../login/login.php?erro=acesso_negado");
    exit;
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

// ================== PESQUISA DE USUÁRIOS ==================
if (isset($_POST['procurar'])) {
    $pesquisa = $_POST['procurar'];
} else {
    $pesquisa = '';
}

// Criar a consulta SQL com parâmetro preparado para USUÁRIOS
$sql = "SELECT id, usuario, email, senha, data_cadastro, Imagem, is_admin 
        FROM usuarios 
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
  <style>
    .tabela-container {
      margin-left: 20%;
      width: 80%;
      padding: 20px;
    }
    .card-usuario {
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .card-usuario:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }
    .usuario-imagem {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #28a745;
    }
    .badge-admin {
      background-color: #dc3545;
      color: white;
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- Sidebar -->
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
    <div class="tabela-container">
      <nav class="navbar bg-body-tertiary mb-4">
        <div class="container-fluid">
          <form class="d-flex" role="search" action="filtros_usuarios.php" method="post" style="flex: 1;">
            <input class="form-control me-2" type="search" name="procurar" placeholder="Nome ou Email" aria-label="Procurar Usuário" value="<?= htmlspecialchars($pesquisa); ?>" autofocus/>
            <button class="btn btn-outline-success" type="submit">
              <i class="bi bi-search"></i> Procurar
            </button>
            <?php if (!empty($pesquisa)): ?>
              <a href="filtros_usuarios.php" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-x-circle"></i> Limpar
              </a>
            <?php endif; ?>
          </form>
          <div class="d-flex gap-2 ms-3 align-items-center">
            <a href="perfil.php" style="text-decoration: none;">
              <img src="<?= htmlspecialchars($imagemPerfil); ?>" 
                   alt="Perfil do usuário" 
                   class="rounded-circle" 
                   style="width: 40px; height: 40px; object-fit: cover; border: 2px solid rgba(0,0,0,0.1); cursor: pointer;"
                   onerror="this.src='/SMCPA/imgs/logotrbf.png'">
            </a>
          </div>
        </div>
      </nav>

      <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2><i class="bi bi-people-fill text-primary"></i> Filtros de Usuários</h2>
          <p class="text-muted mb-0">Total: <?= count($dados); ?> usuário(s) encontrado(s)</p>
        </div>

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
                      <a href="dashboardadm.php?usuario_id=<?= $user['id']; ?>" 
                         class="btn btn-primary btn-sm">
                        <i class="bi bi-eye"></i> Ver Detalhes
                      </a>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

