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

// ================== PESQUISA DE PRAGAS ==================
if (isset($_POST['procurar_praga'])) {
    $pesquisa_praga = $_POST['procurar_praga'];
} else {
    $pesquisa_praga = '';
}

// Criar a consulta SQL com parâmetro preparado para PRAGAS
// Buscar todas as pragas (de todos os usuários) para que todos possam ver relatórios
$sqlPragas = "SELECT 
                ID,
                Nome,
                Planta_Hospedeira,
                Descricao,
                Imagem_Not_Null,
                ID_Praga,
                Localidade,
                Data_Aparicao,
                Observacoes,
                ID_Usuario
              FROM Pragas_Surtos
              WHERE Nome LIKE :pesquisa_praga1 
                 OR Planta_Hospedeira LIKE :pesquisa_praga2 
                 OR Localidade LIKE :pesquisa_praga3
              ORDER BY Data_Aparicao DESC";

$stmtPragas = $pdo->prepare($sqlPragas);
$pesquisa_praga_param = "%$pesquisa_praga%";
$stmtPragas->bindValue(':pesquisa_praga1', $pesquisa_praga_param, PDO::PARAM_STR);
$stmtPragas->bindValue(':pesquisa_praga2', $pesquisa_praga_param, PDO::PARAM_STR);
$stmtPragas->bindValue(':pesquisa_praga3', $pesquisa_praga_param, PDO::PARAM_STR);
$stmtPragas->execute();

// Recupera os dados das pragas
$lista = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
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
  <link rel="stylesheet" href="/SMCPA/paginas/dashboard/dashboard.css">
  <title>Filtros de Pragas - SMCPA</title>
  <style>
    
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <a href="#">
          <img src="/SMCPA/imgs/logotrbf.png" alt="Logo">
        </a>
      </div>

      <nav class="menu-lateral">
        <ul>
          <li class="item-menu">
            <a href="<?= $isAdmin ? 'dashboardadm.php' : 'dashboard.php'; ?>">
              <span class="icon"><i class="fa-solid fa-home"></i></span>
              <span class="txt-link">Home</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="../cadastro/cadpraga.php">
              <span class="icon"><i class="bi bi-columns-gap"></i></span>
              <span class="txt-link">Cadastrar Pragas</span>
            </a>
          </li>
          <li class="item-menu ativo">
            <a href="filtros_pragas.php">
              <span class="icon"><i class="bi bi-funnel"></i></span>
              <span class="txt-link">Filtros de Pragas</span>
            </a>
          </li>
          <?php if ($isAdmin): ?>
          <li class="item-menu">
            <a href="filtros_usuarios.php">
              <span class="icon"><i class="bi bi-people"></i></span>
              <span class="txt-link">Filtros de Usuários</span>
            </a>
          </li>
          <?php endif; ?>
          <li class="item-menu">
            <a href="feedback.php">
              <span class="icon"><i class="bi bi-chat-dots"></i></span>
              <span class="txt-link">Feedback</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="../inicial/inicial.html">
              <span class="icon"><i class="bi bi-gear"></i></span>
              <span class="txt-link">Configurações</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="perfil.php">
              <span class="icon"><i class="bi bi-person-lines-fill"></i></span>
              <span class="txt-link">Conta</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="../login/logout.php">
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
          <form class="d-flex" role="search" action="filtros_pragas.php" method="post" style="flex: 1;">
            <input class="form-control me-2" type="search" name="procurar_praga" placeholder="Nome, Planta ou Localidade" aria-label="Procurar Praga" value="<?= htmlspecialchars($pesquisa_praga); ?>" autofocus/>
            <button class="btn btn-outline-success" type="submit">
              <i class="bi bi-search"></i> Procurar
            </button>
            <?php if (!empty($pesquisa_praga)): ?>
              <a href="filtros_pragas.php" class="btn btn-outline-secondary ms-2">
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
          <h2><i class="bi bi-bug-fill text-primary"></i> Filtros de Pragas</h2>
          <p class="text-muted mb-0">Total: <?= count($lista); ?> praga(s) encontrada(s)</p>
        </div>

        <?php if (!empty($lista)): ?>
          <div class="row g-4">
            <?php foreach ($lista as $praga): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card card-praga h-100 shadow-sm">
                  <?php if (!empty($praga['Imagem_Not_Null'])): ?>
                    <img src="/uploads/pragas/<?= htmlspecialchars($praga['Imagem_Not_Null']); ?>" 
                         class="praga-imagem" 
                         alt="<?= htmlspecialchars($praga['Nome']); ?>"
                         onerror="this.src='/SMCPA/imgs/logotrbf.png'">
                  <?php else: ?>
                    <img src="/SMCPA/imgs/logotrbf.png" 
                         class="praga-imagem" 
                         alt="Sem imagem">
                  <?php endif; ?>
                  
                  <div class="card-body">
                    <h5 class="card-title">
                      <i class="bi bi-bug text-danger"></i> <?= htmlspecialchars($praga['Nome']); ?>
                    </h5>
                    <p class="card-text">
                      <small class="text-muted">
                        <i class="bi bi-flower1"></i> <strong>Planta:</strong> <?= htmlspecialchars($praga['Planta_Hospedeira']); ?><br>
                        <i class="bi bi-geo-alt"></i> <strong>Localidade:</strong> <?= htmlspecialchars($praga['Localidade']); ?><br>
                        <i class="bi bi-calendar"></i> <strong>Data:</strong> <?= date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?>
                      </small>
                    </p>
                    <?php if (!empty($praga['Descricao'])): ?>
                      <p class="card-text">
                        <small><?= htmlspecialchars(substr($praga['Descricao'], 0, 100)); ?><?= strlen($praga['Descricao']) > 100 ? '...' : ''; ?></small>
                      </p>
                    <?php endif; ?>
                  </div>
                  
                  <div class="card-footer bg-transparent border-top-0">
                    <div class="d-grid gap-2">
                      <button type="button" 
                              class="btn btn-primary btn-sm" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalRelatorio<?= $praga['ID']; ?>">
                        <i class="bi bi-file-earmark-pdf"></i> Ver Relatório
                      </button>
                      <a href="../detalhes/detalhes_praga.php?id=<?= $praga['ID']; ?>" 
                         class="btn btn-outline-secondary btn-sm">
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
            <?php if (!empty($pesquisa_praga)): ?>
              Nenhuma praga encontrada com o termo "<strong><?= htmlspecialchars($pesquisa_praga); ?></strong>".
            <?php else: ?>
              Nenhuma praga cadastrada no sistema.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Modais de Relatórios -->
  <?php foreach ($lista as $praga): ?>
    <?php
    // Buscar dados do usuário que cadastrou a praga
    $usuarioPraga = null;
    if (!empty($praga['ID_Usuario'])) {
        try {
            $stmtUsuario = $pdo->prepare("SELECT id, usuario, email FROM usuarios WHERE id = :id");
            $stmtUsuario->bindParam(':id', $praga['ID_Usuario'], PDO::PARAM_INT);
            $stmtUsuario->execute();
            $usuarioPraga = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $usuarioPraga = ['usuario' => 'Usuário', 'email' => ''];
        }
    }
    $dataRelatorio = date('d/m/Y H:i:s');
    ?>
    <div class="modal fade" id="modalRelatorio<?= $praga['ID']; ?>" tabindex="-1" aria-labelledby="modalRelatorioLabel<?= $praga['ID']; ?>" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalRelatorioLabel<?= $praga['ID']; ?>">
              <i class="bi bi-file-earmark-pdf text-primary"></i> Relatório - <?= htmlspecialchars($praga['Nome']); ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="background: white;">
            <!-- Cabeçalho do Relatório -->
            <div class="border-bottom border-success border-3 pb-3 mb-4">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <h3 class="text-success mb-0">SMCPA</h3>
                  <p class="text-muted mb-0">Sistema de Monitoramento e Controle de Pragas Agrícolas</p>
                </div>
                <div class="col-md-4 text-end">
                  <p class="mb-0"><strong>Data do Relatório:</strong></p>
                  <p class="mb-0"><?= $dataRelatorio; ?></p>
                </div>
              </div>
            </div>

            <!-- Título do Relatório -->
            <div class="text-center mb-4">
              <h4 class="text-success">Relatório de Praga Agrícola</h4>
              <h5 class="text-dark"><?= htmlspecialchars($praga['Nome']); ?></h5>
            </div>

            <!-- Informações da Praga -->
            <div class="row mb-3">
              <div class="col-md-6">
                <div class="bg-light border-start border-success border-4 p-3 mb-3">
                  <h6><i class="bi bi-tag"></i> Informações Básicas</h6>
                  <p class="mb-1"><strong>Nome da Praga:</strong> <?= htmlspecialchars($praga['Nome']); ?></p>
                  <p class="mb-1"><strong>ID da Praga:</strong> <?= htmlspecialchars($praga['ID_Praga'] ?? 'N/A'); ?></p>
                  <p class="mb-0"><strong>Planta Hospedeira:</strong> <?= htmlspecialchars($praga['Planta_Hospedeira']); ?></p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="bg-light border-start border-success border-4 p-3 mb-3">
                  <h6><i class="bi bi-geo-alt"></i> Localização</h6>
                  <p class="mb-1"><strong>Localidade:</strong> <?= htmlspecialchars($praga['Localidade']); ?></p>
                  <p class="mb-0"><strong>Data de Aparição:</strong> <?= date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?></p>
                </div>
              </div>
            </div>

            <!-- Descrição -->
            <?php if (!empty($praga['Descricao'])): ?>
            <div class="bg-light border-start border-success border-4 p-3 mb-3">
              <h6><i class="bi bi-file-text"></i> Descrição</h6>
              <p class="mb-0"><?= nl2br(htmlspecialchars($praga['Descricao'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Imagem da Praga -->
            <?php if (!empty($praga['Imagem_Not_Null'])): ?>
            <div class="text-center mb-3">
              <h6><i class="bi bi-image"></i> Imagem da Praga</h6>
              <img src="/uploads/pragas/<?= htmlspecialchars($praga['Imagem_Not_Null']); ?>" 
                   alt="Imagem da praga <?= htmlspecialchars($praga['Nome']); ?>" 
                   class="img-fluid rounded shadow-sm"
                   style="max-height: 300px;"
                   onerror="this.src='/SMCPA/imgs/logotrbf.png'">
            </div>
            <?php endif; ?>

            <!-- Observações -->
            <?php if (!empty($praga['Observacoes'])): ?>
            <div class="bg-light border-start border-success border-4 p-3 mb-3">
              <h6><i class="bi bi-journal-text"></i> Observações</h6>
              <p class="mb-0"><?= nl2br(htmlspecialchars($praga['Observacoes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Informações do Usuário -->
            <?php if ($usuarioPraga): ?>
            <div class="bg-light border-start border-success border-4 p-3 mb-3">
              <h6><i class="bi bi-person"></i> Informações do Responsável</h6>
              <p class="mb-1"><strong>Nome:</strong> <?= htmlspecialchars($usuarioPraga['usuario'] ?? 'N/A'); ?></p>
              <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($usuarioPraga['email'] ?? 'N/A'); ?></p>
            </div>
            <?php endif; ?>

            <!-- Rodapé -->
            <div class="border-top pt-3 mt-4 text-center text-muted" style="font-size: 0.9em;">
              <p class="mb-0">Este relatório foi gerado automaticamente pelo sistema SMCPA</p>
              <p class="mb-0">Relatório ID: <?= $praga['ID']; ?> | Gerado em: <?= $dataRelatorio; ?></p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">
              <i class="bi bi-printer"></i> Imprimir / Salvar PDF
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

              