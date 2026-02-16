<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Iniciar sessão PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ====== VERIFICAÇÃO DE LOGIN ======
$estaLogado = false;
$usuarioID = null;

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

// ====== INCLUDES BÁSICOS ======
require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');

// Verificar se é administrador
$db = new Database();
$pdo = $db->conexao();
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

// Buscar localidade do usuário do cadastro (prioridade) ou da primeira praga
$localidadeUsuario = '';
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

  // Buscar localização do usuário no cadastro
  $stmtLocalizacao = $pdo->prepare("SELECT localizacao FROM Usuarios WHERE id = :usuarioID");
  $stmtLocalizacao->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
  $stmtLocalizacao->execute();
  $resultadoLocalizacao = $stmtLocalizacao->fetch(PDO::FETCH_ASSOC);

  if ($resultadoLocalizacao && !empty($resultadoLocalizacao['localizacao'])) {
    $localidadeUsuario = trim($resultadoLocalizacao['localizacao']);
  } else {
    // Se não tiver localização no cadastro, buscar da primeira praga
    try {
      $stmtLocalidade = $pdo->prepare("SELECT Localidade FROM Pragas_Surtos WHERE ID_Usuario = :usuarioID AND Localidade != '' AND Localidade IS NOT NULL LIMIT 1");
      $stmtLocalidade->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
      $stmtLocalidade->execute();
      $resultadoLocalidade = $stmtLocalidade->fetch(PDO::FETCH_ASSOC);
      if ($resultadoLocalidade && !empty($resultadoLocalidade['Localidade'])) {
        $localidadeUsuario = $resultadoLocalidade['Localidade'];
      }
    } catch (PDOException $e) {
      // Usa o valor padrão vazio
    }
  }
} catch (PDOException $e) {
  // Em caso de erro, tentar buscar da primeira praga
  try {
    $stmtLocalidade = $pdo->prepare("SELECT Localidade FROM Pragas_Surtos WHERE ID_Usuario = :usuarioID AND Localidade != '' AND Localidade IS NOT NULL LIMIT 1");
    $stmtLocalidade->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmtLocalidade->execute();
    $resultadoLocalidade = $stmtLocalidade->fetch(PDO::FETCH_ASSOC);
    if ($resultadoLocalidade && !empty($resultadoLocalidade['Localidade'])) {
      $localidadeUsuario = $resultadoLocalidade['Localidade'];
    }
  } catch (PDOException $e2) {
    // Usa o valor padrão vazio
  }
}

// Buscar todas as pragas cadastradas pelo usuário para seleção
$pragasUsuario = [];
try {
  $stmtPragas = $pdo->prepare("SELECT DISTINCT Nome, Planta_Hospedeira, ID_Praga 
                                 FROM Pragas_Surtos 
                                 WHERE ID_Usuario = :usuarioID 
                                 ORDER BY Nome ASC");
  $stmtPragas->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
  $stmtPragas->execute();
  $pragasUsuario = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $pragasUsuario = [];
}

// Processar cadastro de surto
$mensagemSucesso = '';
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'inserir') {
  // Se não veio do select, pode ter vindo do campo de texto
  $nomePraga = trim($_POST['nome_praga'] ?? '');
  if (empty($nomePraga)) {
    $nomePraga = trim($_POST['nome_praga_novo'] ?? '');
  }

  $plantaHospedeira = trim($_POST['planta_hospedeira'] ?? '');
  $localidade = trim($_POST['localidade'] ?? $localidadeUsuario);
  $dataAparicao = $_POST['data_aparicao'] ?? date('Y-m-d');
  $observacoes = trim($_POST['observacoes'] ?? '');
  $idPraga = trim($_POST['id_praga'] ?? '');

  // Validações
  if (empty($nomePraga)) {
    $mensagemErro = "O nome da praga é obrigatório.";
  } elseif (empty($localidade)) {
    $mensagemErro = "A localidade é obrigatória.";
  } elseif (empty($dataAparicao)) {
    $mensagemErro = "A data de aparição é obrigatória.";
  } else {
    try {
      // Inserir o surto na tabela Pragas_Surtos
      $stmtInsert = $pdo->prepare("
                INSERT INTO Pragas_Surtos (
                    Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, ID_Praga, 
                    Localidade, Data_Aparicao, Observacoes, ID_Usuario
                ) VALUES (
                    :Nome, :Planta_Hospedeira, :Descricao, :Imagem_Not_Null, 
                    :ID_Praga, :Localidade, :Data_Aparicao, :Observacoes, :ID_Usuario
                )
            ");

      $descricao = "Surtos registrados em " . $localidade . " em " . date('d/m/Y', strtotime($dataAparicao));

      $stmtInsert->bindParam(':Nome', $nomePraga, PDO::PARAM_STR);
      $stmtInsert->bindParam(':Planta_Hospedeira', $plantaHospedeira, PDO::PARAM_STR);
      $stmtInsert->bindParam(':Descricao', $descricao, PDO::PARAM_STR);
      $stmtInsert->bindValue(':Imagem_Not_Null', null, PDO::PARAM_NULL);
      $stmtInsert->bindParam(':ID_Praga', $idPraga, PDO::PARAM_STR);
      $stmtInsert->bindParam(':Localidade', $localidade, PDO::PARAM_STR);
      $stmtInsert->bindParam(':Data_Aparicao', $dataAparicao, PDO::PARAM_STR);
      $stmtInsert->bindParam(':Observacoes', $observacoes, PDO::PARAM_STR);
      $stmtInsert->bindParam(':ID_Usuario', $usuarioID, PDO::PARAM_INT);

      if ($stmtInsert->execute()) {
        $pragaID = $pdo->lastInsertId();

        // Criar alertas para usuários da mesma região
        try {
          // Criar tabela de alertas se não existir
          $pdo->exec("
                        CREATE TABLE IF NOT EXISTS alertas_pragas (
                            ID INT AUTO_INCREMENT PRIMARY KEY,
                            ID_Praga INT NOT NULL,
                            ID_Usuario_Destino INT NOT NULL,
                            ID_Usuario_Origem INT NOT NULL,
                            Localidade VARCHAR(255),
                            Nome_Praga VARCHAR(255),
                            Data_Criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                            Lido TINYINT(1) DEFAULT 0,
                            Data_Leitura DATETIME NULL,
                            INDEX idx_usuario_lido (ID_Usuario_Destino, Lido),
                            INDEX idx_data_criacao (Data_Criacao)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
        } catch (PDOException $e) {
          // Tabela pode já existir
        }

        // Buscar usuários da mesma região (exceto o próprio usuário)
        if (!empty($localidade)) {
          $stmtUsuarios = $pdo->prepare("
                        SELECT DISTINCT u.id 
                        FROM Usuarios u
                        WHERE (u.localizacao = :localidade 
                               OR u.localizacao LIKE :localidadeLike)
                        AND u.id != :usuarioID
                        AND u.id IS NOT NULL
                    ");
          $localidadeLike = '%' . $localidade . '%';
          $stmtUsuarios->bindParam(':localidade', $localidade, PDO::PARAM_STR);
          $stmtUsuarios->bindParam(':localidadeLike', $localidadeLike, PDO::PARAM_STR);
          $stmtUsuarios->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
          $stmtUsuarios->execute();
          $usuariosRegiao = $stmtUsuarios->fetchAll(PDO::FETCH_COLUMN);

          // Criar alertas para cada usuário da região
          if (!empty($usuariosRegiao)) {
            $stmtAlerta = $pdo->prepare("
                            INSERT INTO alertas_pragas 
                            (ID_Praga, ID_Usuario_Destino, ID_Usuario_Origem, Localidade, Nome_Praga)
                            VALUES (:ID_Praga, :ID_Usuario_Destino, :ID_Usuario_Origem, :Localidade, :Nome_Praga)
                        ");

            foreach ($usuariosRegiao as $usuarioDestinoID) {
              $stmtAlerta->bindParam(':ID_Praga', $pragaID, PDO::PARAM_INT);
              $stmtAlerta->bindParam(':ID_Usuario_Destino', $usuarioDestinoID, PDO::PARAM_INT);
              $stmtAlerta->bindParam(':ID_Usuario_Origem', $usuarioID, PDO::PARAM_INT);
              $stmtAlerta->bindParam(':Localidade', $localidade, PDO::PARAM_STR);
              $stmtAlerta->bindParam(':Nome_Praga', $nomePraga, PDO::PARAM_STR);
              $stmtAlerta->execute();
            }
          }
        }

        $mensagemSucesso = "Surtos cadastrado com sucesso!";
        // Limpar campos após sucesso
        $_POST = [];
      } else {
        $mensagemErro = "Erro ao cadastrar o surto.";
      }
    } catch (PDOException $e) {
      $mensagemErro = "Erro ao cadastrar o surto: " . $e->getMessage();
    }
  }
}

// Buscar imagem do perfil do usuário
$imagemPerfil = null;
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
if (!$imagemPerfil) {
  $imagemPerfil = '/SMCPA/imgs/logotrbf.png';
}
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
  <link rel="stylesheet" href="/SMCPA/css/dashboard.css">
  <title>Cadastro de Surtos - SMCPA</title>
  <style>
    body {
      background-color: #f5f6fa;
    }

    .form-container {
      max-width: 800px;
      margin: 40px auto;
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .form-container h2 {
      color: #1a3d24;
      margin-bottom: 25px;
      border-bottom: 3px solid #28a745;
      padding-bottom: 10px;
    }

    .btn-cadastrar {
      background-color: #28a745;
      border-color: #28a745;
      color: white;
      padding: 12px 30px;
      font-size: 1.1rem;
    }

    .btn-cadastrar:hover {
      background-color: #218838;
      border-color: #1e7e34;
    }
  </style>
</head>

<body>
  <div class="dashboard-container">
    <?php include_once(BASE_URL . '/includes/sidebar.php'); ?>

    <!-- Main Content -->
    <main class="main-content">
      <header class="topbar">
        <div class="left">
        </div>
        <div class="right d-flex align-items-center gap-3">
          <a href="../tutorial/tutorial.php" class="btn btn-outline-light">
            <i class="fa-solid fa-book"></i> Tutoriais
          </a>
          <a href="../dashboard/perfil.php" style="text-decoration: none;">
            <img src="<?= htmlspecialchars($imagemPerfil); ?>"
              alt="Perfil do usuário"
              class="rounded-circle"
              style="width: 40px; height: 40px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3); cursor: pointer;"
              onerror="this.src='/SMCPA/imgs/logotrbf.png'">
          </a>
        </div>
      </header>

      <section class="content">
        <div class="form-container">
          <h2><i class="bi bi-exclamation-triangle-fill text-warning"></i> Cadastro de Surtos na Região</h2>

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

          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Importante:</strong> Os surtos cadastrados serão exibidos no dashboard para usuários da mesma região.
            Certifique-se de que a localidade está correta.
          </div>

          <form method="POST" id="formSurto">
            <input type="hidden" name="acao" value="inserir">

            <div class="mb-3">
              <label for="nome_praga" class="form-label">
                <i class="bi bi-bug"></i> Nome da Praga <span class="text-danger">*</span>
              </label>
              <?php if (!empty($pragasUsuario)): ?>
                <select class="form-select" id="nome_praga" name="nome_praga" onchange="atualizarPragaInfo()">
                  <option value="">-- Selecione uma praga cadastrada --</option>
                  <?php foreach ($pragasUsuario as $praga): ?>
                    <option value="<?= htmlspecialchars($praga['Nome']); ?>"
                      data-planta="<?= htmlspecialchars($praga['Planta_Hospedeira']); ?>"
                      data-id="<?= htmlspecialchars($praga['ID_Praga'] ?? ''); ?>">
                      <?= htmlspecialchars($praga['Nome']); ?>
                      <?php if (!empty($praga['Planta_Hospedeira'])): ?>
                        - <?= htmlspecialchars($praga['Planta_Hospedeira']); ?>
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-1">Ou digite o nome de uma nova praga abaixo</small>
                <input type="text" class="form-control mt-2" id="nome_praga_novo"
                  placeholder="Digite o nome de uma nova praga (opcional)"
                  oninput="if(this.value) { document.getElementById('nome_praga').value = this.value; }">
              <?php else: ?>
                <input type="text" class="form-control" id="nome_praga" name="nome_praga"
                  placeholder="Digite o nome da praga" required>
                <small class="text-muted">Você ainda não cadastrou nenhuma praga. Digite o nome da praga.</small>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="planta_hospedeira" class="form-label">
                <i class="bi bi-flower1"></i> Planta Hospedeira
              </label>
              <input type="text" class="form-control" id="planta_hospedeira" name="planta_hospedeira"
                placeholder="Ex: Milho, Soja, Algodão, etc.">
            </div>

            <div class="mb-3">
              <label for="localidade" class="form-label">
                <i class="bi bi-geo-alt"></i> Localidade/Região <span class="text-danger">*</span>
              </label>
              <input type="text" class="form-control" id="localidade" name="localidade"
                value="<?= htmlspecialchars($localidadeUsuario); ?>" required
                placeholder="Ex: Zona Rural de São Paulo, Fazenda ABC, etc.">
              <small class="text-muted">Esta localidade será usada para agrupar surtos no dashboard.</small>
            </div>

            <div class="mb-3">
              <label for="data_aparicao" class="form-label">
                <i class="bi bi-calendar"></i> Data de Aparição do Surto <span class="text-danger">*</span>
              </label>
              <input type="date" class="form-control" id="data_aparicao" name="data_aparicao"
                value="<?= date('Y-m-d'); ?>" required>
            </div>

            <div class="mb-3">
              <label for="id_praga" class="form-label">
                <i class="bi bi-tag"></i> ID da Praga (Opcional)
              </label>
              <input type="text" class="form-control" id="id_praga" name="id_praga"
                placeholder="Código ou identificador da praga">
            </div>

            <div class="mb-3">
              <label for="observacoes" class="form-label">
                <i class="bi bi-journal-text"></i> Observações
              </label>
              <textarea class="form-control" id="observacoes" name="observacoes" rows="4"
                placeholder="Informações adicionais sobre o surto, condições climáticas, intensidade, etc."></textarea>
            </div>

            <div class="d-flex gap-2 justify-content-end">
              <a href="<?= $isAdmin ? '../dashboard/dashboardadm.php' : '../dashboard/dashboard.php'; ?>"
                class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
              </a>
              <button type="submit" class="btn btn-cadastrar">
                <i class="bi bi-check-circle"></i> Cadastrar Surto
              </button>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function atualizarPragaInfo() {
      const select = document.getElementById('nome_praga');
      const option = select.options[select.selectedIndex];

      if (option && option.value) {
        const planta = option.getAttribute('data-planta');
        const idPraga = option.getAttribute('data-id');

        if (planta) {
          document.getElementById('planta_hospedeira').value = planta;
        }
        if (idPraga) {
          document.getElementById('id_praga').value = idPraga;
        }

        // Limpar campo de nome novo quando selecionar do dropdown
        const nomeNovo = document.getElementById('nome_praga_novo');
        if (nomeNovo) {
          nomeNovo.value = '';
        }
      }
    }

    // Validação do formulário
    document.getElementById('formSurto')?.addEventListener('submit', function(e) {
      const select = document.getElementById('nome_praga');
      const nomeNovo = document.getElementById('nome_praga_novo');

      // Se não selecionou do dropdown e não digitou nome novo, impedir envio
      if (!select.value && (!nomeNovo || !nomeNovo.value)) {
        e.preventDefault();
        alert('Por favor, selecione uma praga ou digite o nome de uma nova praga.');
        return false;
      }

      // Se digitou nome novo mas não selecionou do dropdown, usar o nome novo
      if (nomeNovo && nomeNovo.value && !select.value) {
        select.value = nomeNovo.value;
      }
    });
  </script>
</body>

</html>