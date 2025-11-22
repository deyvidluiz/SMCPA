<?php
// Configurar cookie de sessão para funcionar em todo o domínio
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Iniciar sessão PRIMEIRO, antes de qualquer include
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado através da página de login
$estaLogado = false;
$usuarioID = null;

// Verifica se tem a flag 'logado' que é definida APENAS no login.php
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    // Se tem a flag logado, verifica se tem o ID do usuário
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        $usuarioID = $_SESSION['usuario_id'];
        $estaLogado = true;
    } elseif (isset($_SESSION['id']) && !empty($_SESSION['id'])) {
        $usuarioID = $_SESSION['id'];
        $estaLogado = true;
    }
}

// Se não estiver logado ou não tiver ID válido, redireciona para login
if (!$estaLogado || !$usuarioID) {
    session_destroy();
    header("Location: ../login/login.php");
    exit;
}

// Incluir o arquivo com a classe Database
require_once('../../config.php'); 
include_once(BASE_URL.'/conexao/conexao.php');  // Certifique-se de que esse caminho está correto

// Criar uma instância da classe Database
$db = new Database();

// Estabelecer a conexão com o banco de dados (PDO)
$pdo = $db->conexao();  // Obtém a conexão PDO

// ================== PESQUISA DE USUÁRIOS ==================
if (isset($_POST['procurar'])) {
    $pesquisa = $_POST['procurar'];
} else {
    $pesquisa = '';
}

// Criar a consulta SQL com parâmetro preparado para USUÁRIOS
$sql = "SELECT id, usuario, email, senha, data_cadastro, Imagem 
        FROM usuarios 
        WHERE usuario LIKE :pesquisa"; 

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':pesquisa', "%$pesquisa%", PDO::PARAM_STR);
$stmt->execute();

// Recupera os dados dos usuários
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Função para excluir usuários
if (isset($_GET['delete_usuario'])) {
    $usuario_id = $_GET['delete_usuario'];

    $stmtDeleteusuario = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmtDeleteusuario->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmtDeleteusuario->execute();
  
    header('location: dashboardadm.php');
    exit;
}

// Função para redefinir senha do usuário (converter para texto plano)
if (isset($_POST['redefinir_senha'])) {
    $usuario_id = $_POST['usuario_id'];
    $nova_senha = $_POST['nova_senha'];
    
    if (!empty($nova_senha)) {
        try {
            $stmtRedefinir = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
            $stmtRedefinir->bindParam(':senha', $nova_senha);
            $stmtRedefinir->bindParam(':id', $usuario_id, PDO::PARAM_INT);
            $stmtRedefinir->execute();
            
            $sucesso_redefinir = "Senha redefinida com sucesso!";
        } catch (PDOException $e) {
            $erro_redefinir = "Erro ao redefinir senha: " . $e->getMessage();
        }
    } else {
        $erro_redefinir = "A senha não pode estar vazia!";
    }
}

// ================== CONSULTA DAS PRAGAS ==================
$sqlPragas = "SELECT 
                ID,
                Nome,
                Planta_Hospedeira,
                Descricao,
                Imagem_Not_Null,
                ID_Praga,
                Localidade,
                Data_Aparicao,
                Observacoes
              FROM Pragas_Surtos
              ORDER BY ID DESC";

$stmtPragas = $pdo->prepare($sqlPragas);
$stmtPragas->execute();

// Recupera os dados das pragas em $lista
$lista = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="../../paginas/dashboard/dashboardadm.css">
  <title>Dashboard - SMCPA</title>
</head>
<body>
  <div class="dashboard-container">
    <!-- Sidebar (Menu Lateral) -->
    <aside class="sidebar">
      <div class="logo">
        <a href="#">
          <img src="/SMCPA/imgs/logotrbf.png" alt="Logo">
        </a>
      </div>
      <nav class="menu-lateral">
        <ul>
          <li class="item-menu">
            <a href="dashboard.php">
              <span class="icon"><i class="fa-solid fa-home"></i></span>
              <span class="txt-link">Home</span>
            </a>
          </li>
          <li class="item-menu ativo">
            <a href="../cadastro/cadpraga.php">
              <span class="icon"><i class="bi bi-columns-gap"></i></span>
              <span class="txt-link">Dashboard</span>
            </a>
          </li>
          <li class="item-menu">
            <a href="../cadastro/cadpraga.php">
              <span class="icon"><i class="bi bi-calendar-range"></i></span>
              <span class="txt-link">Agenda</span>
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
        </ul>
      </nav> 
    </aside>

    <!-- Main Content -->
    <div class="tabela-container">
      <nav class="navbar bg-body-tertiary">
        <div class="container-fluid">
          <form class="d-flex" role="search" action="./index.php" method="post" style="flex: 1;">
            <input class="form-control me-2" type="search" name="procurar" placeholder="Nome" aria-label="Procurar" autofocus/>
            <button class="btn btn-outline-success" type="submit">Procurar</button>
          </form>
          <div class="d-flex gap-2 ms-3">
            <a href="perfil.php" class="btn btn-outline-primary">
              <i class="fa-solid fa-user"></i> Perfil
            </a>
          </div>
        </div>
      </nav>

      <div class="container mt-3">
        <!-- Mensagens de Sucesso/Erro -->
        <?php if (isset($sucesso_redefinir)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($sucesso_redefinir); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        
        <?php if (isset($erro_redefinir)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($erro_redefinir); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        
        <!-- Exibindo os dados da pesquisa de usuários -->
        <?php if (!empty($dados)): ?>
          <div class="titulo">
            <h2>Resultados da Pesquisa de Usuários</h2>
          </div>
          <table class="table table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>Imagem</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Senha</th>
                <th>Data de Cadastro</th>
                <th>Opções</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dados as $user): ?>
                <tr>
                  <td><?= htmlspecialchars($user['id'] ?? 'N/A') ?></td>
                  <td>
                    <?php if (!empty($user['Imagem'])): ?>
                      <img src="/uploads/usuarios/<?= htmlspecialchars($user['Imagem']); ?>" 
                           alt="Foto de perfil" 
                           style="width:80px; height:80px; object-fit:cover; border:1px solid #ddd; border-radius:4px;">
                    <?php else: ?>
                      <img src="/SMCPA/imgs/logotrbf.png" 
                           alt="Sem foto" 
                           style="width:80px; height:80px; object-fit:cover; opacity:0.5; border:1px solid #ddd; border-radius:4px;">
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($user['usuario'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($user['senha'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($user['data_cadastro'] ?? 'N/A') ?></td>
                  <td>
                    <a href="perfil.php?id=<?= $user['id']; ?>" class="btn btn-info btn-sm">
                      <i class="bi bi-person-circle"></i> Ver Perfil
                    </a>
                    <a href="editar.php?id=<?= $user['id']; ?>" class="btn btn-primary btn-sm">Editar</a>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalRedefinirSenha<?= $user['id']; ?>">
                      <i class="bi bi-key"></i> Redefinir Senha
                    </button>
                    <a href="?delete_usuario=<?= $user['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Tem certeza que deseja excluir este usuário?');">Excluir</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>Nenhum usuário encontrado.</p>
        <?php endif; ?>

        <hr>

        <!-- LISTA DE PRAGAS CADASTRADAS -->
        <div class="titulo">
          <h2>Pragas cadastradas</h2>
        </div>

        <?php if (!empty($lista)) : ?>
          <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Imagem</th>
                    <th>Nome</th>
                    <th>Planta Hospedeira</th>
                    <th>Localidade</th>
                    <th>Data Aparição</th>
                    <th>Observações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $praga): ?>
                    <tr>
                        <td><?= htmlspecialchars($praga['ID']); ?></td>
                        <td>
                            <?php if (!empty($praga['Imagem_Not_Null'])): ?>
                                <img src="/uploads/pragas/<?= htmlspecialchars($praga['Imagem_Not_Null']); ?>" 
                                     alt="Imagem da praga" 
                                     style="max-width:80px; max-height:80px;">
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($praga['Nome']); ?></td>
                        <td><?= htmlspecialchars($praga['Planta_Hospedeira']); ?></td>
                        <td><?= htmlspecialchars($praga['Localidade']); ?></td>
                        <td><?= htmlspecialchars($praga['Data_Aparicao']); ?></td>
                        <td><?= nl2br(htmlspecialchars($praga['Observacoes'])); ?></td>
                        <td class="actions">
                            <a href="excluir_praga.php?id=<?= $praga['ID']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Tem certeza que deseja excluir esta praga?');">
                               Excluir
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
          </table>
        <?php else : ?>
          <p>Nenhuma praga cadastrada ainda.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Modais para Redefinir Senha -->
  <?php if (!empty($dados)): ?>
    <?php foreach ($dados as $user): ?>
      <div class="modal fade" id="modalRedefinirSenha<?= $user['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-warning">
              <h5 class="modal-title">
                <i class="bi bi-key"></i> Redefinir Senha - <?= htmlspecialchars($user['usuario']); ?>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <div class="modal-body">
                <input type="hidden" name="usuario_id" value="<?= $user['id']; ?>">
                <div class="mb-3">
                  <label for="nova_senha_<?= $user['id']; ?>" class="form-label">Nova Senha (em texto plano)</label>
                  <input type="text" class="form-control" id="nova_senha_<?= $user['id']; ?>" 
                         name="nova_senha" required placeholder="Digite a nova senha">
                  <small class="text-muted">A senha será armazenada exatamente como digitada (sem criptografia)</small>
                </div>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle"></i> <strong>Senha atual:</strong> <?= htmlspecialchars($user['senha']); ?>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="redefinir_senha" class="btn btn-warning">
                  <i class="bi bi-check-circle"></i> Redefinir Senha
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
