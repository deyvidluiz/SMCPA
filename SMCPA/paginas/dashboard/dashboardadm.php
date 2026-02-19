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

// Headers para prevenir cache e garantir que o botão voltar não funcione após logout
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Incluir o arquivo com a classe Database
require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');  // Certifique-se de que esse caminho está correto

// Criar uma instância da classe Database
$db = new Database();

// Estabelecer a conexão com o banco de dados (PDO)
$pdo = $db->conexao();  // Obtém a conexão PDO

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
// Se não houver imagem, usar imagem padrão
if (!$imagemPerfil) {
  $imagemPerfil = '/SMCPA/imgs/logotrbf.png';
}

// ================== VERIFICAÇÃO DE ADMINISTRADOR ==================
// Verificar se a coluna is_admin existe, se não, criar
try {
  // Verificar se a coluna já existe
  $stmtCheck = $pdo->query("SHOW COLUMNS FROM Usuarios LIKE 'is_admin'");
  $colunaExiste = $stmtCheck->rowCount() > 0;

  if (!$colunaExiste) {
    // Se não existe, criar a coluna
    $pdo->exec("ALTER TABLE Usuarios ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
  }
} catch (PDOException $e) {
  // Ignorar erro - coluna provavelmente já existe
}

// Tornar o usuário ID 7 (Deyvid) como administrador
try {
  $stmtAdminId7 = $pdo->prepare("UPDATE Usuarios SET is_admin = 1 WHERE id = 7");
  $stmtAdminId7->execute();
} catch (PDOException $e) {
  // Ignorar erro se o usuário não existir
}

// Verificar se o usuário logado é administrador (apenas para exibir botão de cadastrar admin)
// Primeiro tenta usar a sessão (mais rápido)
$isAdmin = false;
if (isset($_SESSION['is_admin'])) {
  $isAdmin = $_SESSION['is_admin'] == 1;
} else {
  // Se não estiver na sessão, consulta o banco
  try {
    $stmtAdmin = $pdo->prepare("SELECT is_admin FROM Usuarios WHERE id = :id");
    $stmtAdmin->bindParam(':id', $usuarioID, PDO::PARAM_INT);
    $stmtAdmin->execute();
    $userAdmin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    $isAdmin = ($userAdmin && isset($userAdmin['is_admin']) && $userAdmin['is_admin'] == 1);
    // Salva na sessão para próximas verificações
    $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
  } catch (PDOException $e) {
    // Se der erro, assume que não é admin
    $isAdmin = false;
  }
}
// Nota: Não bloqueia acesso aqui pois o sistema de login já redireciona automaticamente
// apenas administradores para esta página

// Função para excluir usuários (pode ser chamada de outras páginas)
if (isset($_GET['delete_usuario'])) {
  $usuario_id = (int) $_GET['delete_usuario'];

  if ($usuario_id > 0) {
    try {
      // Remover arquivos de imagem das pragas e do perfil (antes de apagar do BD)
      $stmtPragas = $pdo->prepare("SELECT Imagem_Not_Null FROM Pragas_Surtos WHERE ID_Usuario = :uid");
      $stmtPragas->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
      $stmtPragas->execute();
      foreach ($stmtPragas->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!empty($row['Imagem_Not_Null'])) {
          $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pragas/' . $row['Imagem_Not_Null'];
          if (file_exists($filePath)) {
            @unlink($filePath);
          }
        }
      }
      $stmtImg = $pdo->prepare("SELECT Imagem FROM Usuarios WHERE id = :id");
      $stmtImg->bindParam(':id', $usuario_id, PDO::PARAM_INT);
      $stmtImg->execute();
      $rowImg = $stmtImg->fetch(PDO::FETCH_ASSOC);
      if ($rowImg && !empty($rowImg['Imagem'])) {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/usuarios/' . $rowImg['Imagem'];
        if (file_exists($filePath)) {
          @unlink($filePath);
        }
      }

      require_once(BASE_URL . '/database/excluir_usuario_cascata.php');
      excluir_usuario_cascata($pdo, $usuario_id);

      if ($usuario_id == $usuarioID) {
        session_destroy();
        header('location: ../login/login.php?conta_excluida=1');
      } else {
        header('location: dashboardadm.php?sucesso=usuario_excluido');
      }
      exit;
    } catch (PDOException $e) {
      header('location: dashboardadm.php?erro=' . urlencode('Erro ao excluir usuário: ' . $e->getMessage()));
      exit;
    }
  }
}

// Função para redefinir senha do usuário (converter para texto plano)
if (isset($_POST['redefinir_senha'])) {
  $usuario_id = $_POST['usuario_id'];
  $nova_senha = $_POST['nova_senha'];

  if (!empty($nova_senha)) {
    try {
      $stmtRedefinir = $pdo->prepare("UPDATE Usuarios SET senha = :senha WHERE id = :id");
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

// ================== CADASTRO DE ADMINISTRADOR ==================
if (isset($_POST['cadastrar_admin']) && $isAdmin) {
  $usuario_admin = trim($_POST['usuario_admin'] ?? '');
  $email_admin = trim($_POST['email_admin'] ?? '');
  $senha_admin = $_POST['senha_admin'] ?? '';

  if (!empty($usuario_admin) && !empty($email_admin) && !empty($senha_admin)) {
    try {
      // Verificar se o email já existe
      $stmtCheck = $pdo->prepare("SELECT id FROM Usuarios WHERE email = :email");
      $stmtCheck->bindParam(':email', $email_admin);
      $stmtCheck->execute();

      if ($stmtCheck->fetch()) {
        $erro_cadastro_admin = "Este email já está cadastrado!";
      } else {
        // Criptografa a senha
        $senha_hash = password_hash($senha_admin, PASSWORD_DEFAULT);

        // Cadastrar como administrador
        $stmtCadastro = $pdo->prepare("
                    INSERT INTO Usuarios (usuario, email, senha, is_admin) 
                    VALUES (:usuario, :email, :senha, 1)
                ");
        $stmtCadastro->bindParam(':usuario', $usuario_admin);
        $stmtCadastro->bindParam(':email', $email_admin);
        $stmtCadastro->bindParam(':senha', $senha_hash);
        $stmtCadastro->execute();

        $sucesso_cadastro_admin = "Administrador cadastrado com sucesso!";
      }
    } catch (PDOException $e) {
      $erro_cadastro_admin = "Erro ao cadastrar administrador: " . $e->getMessage();
    }
  } else {
    $erro_cadastro_admin = "Todos os campos são obrigatórios!";
  }
}

// ================== DASHBOARD DE PRAGAS (FUNCIONALIDADES DO USUÁRIO) ==================
// Buscar todas as pragas cadastradas pelo usuário logado (mostrar apenas as pragas deste usuário)
$todasPragas = [];
try {
  $stmtPragas = $pdo->prepare("SELECT ID, Nome, Planta_Hospedeira, Localidade, Data_Aparicao, ID_Usuario 
                 FROM Pragas_Surtos 
                 WHERE ID_Usuario = :usuarioID
                 ORDER BY Data_Aparicao DESC");
  $stmtPragas->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
  $stmtPragas->execute();
  $todasPragas = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $todasPragas = [];
}

// Buscar apenas as pragas cadastradas pelo admin logado (para gerar relatórios)
$pragasAdmin = [];
try {
  $stmtPragasAdmin = $pdo->prepare("SELECT ID, Nome, Planta_Hospedeira, Localidade, Data_Aparicao, ID_Usuario 
                                      FROM Pragas_Surtos 
                                      WHERE ID_Usuario = :usuarioID 
                                      ORDER BY Data_Aparicao DESC");
  $stmtPragasAdmin->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
  $stmtPragasAdmin->execute();
  $pragasAdmin = $stmtPragasAdmin->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $pragasAdmin = [];
}

// Buscar localidade do usuário (pega da primeira praga ou usa padrão)
$localidadeUsuario = 'Região não especificada';
if (!empty($todasPragas)) {
  $localidadeUsuario = $todasPragas[0]['Localidade'];
}

// Função para extrair palavras-chave do nome da praga (para busca de pragas similares)
function extrairPalavrasChave($nome)
{
  $nome = strtolower(trim($nome));
  $palavrasComuns = ['de', 'da', 'do', 'das', 'dos', 'a', 'o', 'e', 'em', 'na', 'no'];
  $palavras = explode(' ', $nome);
  $palavrasChave = [];
  foreach ($palavras as $palavra) {
    $palavra = trim($palavra);
    if (strlen($palavra) > 2 && !in_array($palavra, $palavrasComuns)) {
      $palavrasChave[] = $palavra;
    }
  }
  return $palavrasChave;
}

// Praga selecionada (se houver)
$pragaSelecionadaID = $_GET['praga_id'] ?? ($todasPragas[0]['ID'] ?? null);
$pragaSelecionada = null;

if ($pragaSelecionadaID) {
  foreach ($todasPragas as $praga) {
    if ($praga['ID'] == $pragaSelecionadaID) {
      $pragaSelecionada = $praga;
      break;
    }
  }
}

// Preparar dados para o gráfico do admin usando o histórico de atualizações (sem limite de 30 dias)
$dadosGrafico = [];
if ($pragaSelecionada) {
  try {
    // Buscar atualizações históricas da praga (tabela historico_pragas)
    $pragaID = $pragaSelecionada['ID'];
    $sqlHist = "SELECT data_atualizacao AS Data_Aparicao, media_pragas_planta
          FROM historico_pragas
          WHERE ID_Praga = :pragaID
          AND media_pragas_planta IS NOT NULL
          AND media_pragas_planta > 0
          ORDER BY data_atualizacao ASC";
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->bindParam(':pragaID', $pragaID, PDO::PARAM_INT);
    $stmtHist->execute();
    $historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historico as $registro) {
      $dadosGrafico[] = [
        'Data_Aparicao' => $registro['Data_Aparicao'],
        'media_pragas' => round((float)$registro['media_pragas_planta'], 2)
      ];
    }
  } catch (PDOException $e) {
    $dadosGrafico = [];
    error_log('Erro ao buscar histórico para gráfico admin: ' . $e->getMessage());
  }
}

// Função para gerar gráfico SVG em PHP puro (copiada do dashboard do usuário)
function gerarGraficoSVG($dados, $pragaNome = 'Evolução da Infestação')
{
  if (empty($dados)) {
    return '';
  }
  $largura = 720;
  $altura = 300;
  $margem = 40;
  $areaLargura = $largura - (2 * $margem);
  $areaAltura = $altura - (2 * $margem);
  $scaleX = 0.9;
  $valores = array_map(fn($d) => (float)($d['media_pragas'] ?? 0), $dados);
  $minValor = min($valores);
  $maxValor = max($valores);
  if ($minValor == $maxValor) {
    $minValor = $maxValor * 0.8;
    $maxValor = $maxValor * 1.2;
  }
  $intervalo = $maxValor - $minValor;
  $pontos = [];
  for ($i = 0; $i < count($dados); $i++) {
    $offsetX = ($areaLargura * (1 - $scaleX)) / 2;
    $x = $margem + $offsetX + ($i / (count($dados) - 1 ?: 1)) * $areaLargura * $scaleX;
    $y = $altura - $margem - ((($dados[$i]['media_pragas'] ?? 0) - $minValor) / ($intervalo ?: 1)) * $areaAltura;
    $pontos[] = ['x' => $x, 'y' => $y, 'valor' => ($dados[$i]['media_pragas'] ?? 0), 'data' => $dados[$i]['Data_Aparicao']];
  }
  $svg = '<div style="width: 100%; overflow: hidden; display: flex; align-items: center; justify-content: center;">';
  $svg .= '<svg viewBox="0 0 ' . $largura . ' ' . $altura . '" preserveAspectRatio="xMidYMid meet" width="100%" height="auto" xmlns="http://www.w3.org/2000/svg" style="max-width:100%; max-height:100%; border: 1px solid #ddd; border-radius: 4px; background: white; display: block;">';
  $svg .= '<rect width="' . $largura . '" height="' . $altura . '" fill="white"/>';
  $numLinhas = 5;
  for ($i = 0; $i <= $numLinhas; $i++) {
    $y = $margem + ($i / $numLinhas) * $areaAltura;
    $svg .= '<line x1="' . $margem . '" y1="' . $y . '" x2="' . ($largura - $margem) . '" y2="' . $y . '" stroke="#e0e0e0" stroke-width="1"/>';
    $valor = $maxValor - ($i / $numLinhas) * $intervalo;
    $svg .= '<text x="' . ($margem - 10) . '" y="' . ($y + 5) . '" font-size="10" fill="#666" text-anchor="end">' . number_format($valor, 1, ',', '.') . '</text>';
  }
  $svg .= '<line x1="' . $margem . '" y1="' . ($altura - $margem) . '" x2="' . ($largura - $margem) . '" y2="' . ($altura - $margem) . '" stroke="#333" stroke-width="2"/>';
  $svg .= '<line x1="' . $margem . '" y1="' . $margem . '" x2="' . $margem . '" y2="' . ($altura - $margem) . '" stroke="#333" stroke-width="2"/>';
  if (count($pontos) > 1) {
    $pathData = 'M ' . $pontos[0]['x'] . ' ' . $pontos[0]['y'];
    for ($i = 1; $i < count($pontos); $i++) {
      $pathData .= ' L ' . $pontos[$i]['x'] . ' ' . $pontos[$i]['y'];
    }
    $svg .= '<path d="' . $pathData . '" stroke="#dc3545" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
  }
  if (count($pontos) > 1) {
    $pathArea = 'M ' . $pontos[0]['x'] . ' ' . $pontos[0]['y'];
    for ($i = 1; $i < count($pontos); $i++) {
      $pathArea .= ' L ' . $pontos[$i]['x'] . ' ' . $pontos[$i]['y'];
    }
    $pathArea .= ' L ' . $pontos[count($pontos) - 1]['x'] . ' ' . ($altura - $margem);
    $pathArea .= ' L ' . $pontos[0]['x'] . ' ' . ($altura - $margem) . ' Z';
    $svg .= '<path d="' . $pathArea . '" fill="rgba(220, 53, 69, 0.2)"/>';
  }
  for ($i = 0; $i < count($pontos); $i++) {
    $ponto = $pontos[$i];
    $cor = '#dc3545';
    if ($i > 0) {
      $valAnterior = $pontos[$i - 1]['valor'];
      $valAtual = $ponto['valor'];
      if ($valAtual < $valAnterior) {
        $cor = '#28a745';
      } elseif ($valAtual > $valAnterior) {
        $cor = '#dc3545';
      } else {
        $cor = '#ffc107';
      }
    }
    $svg .= '<circle cx="' . $ponto['x'] . '" cy="' . $ponto['y'] . '" r="4" fill="' . $cor . '" stroke="white" stroke-width="2"/>';
    $svg .= '<line x1="' . $ponto['x'] . '" y1="' . $ponto['y'] . '" x2="' . $ponto['x'] . '" y2="' . ($altura - $margem) . '" stroke="#ddd" stroke-width="1" stroke-dasharray="2,2"/>';
    $data = DateTime::createFromFormat('Y-m-d H:i:s', $ponto['data']);
    $dataFormatada = $data ? $data->format('d/m/y H:i') : $ponto['data'];
    $svg .= '<text x="' . $ponto['x'] . '" y="' . ($altura - $margem + 18) . '" font-size="9" fill="#666" text-anchor="middle">' . htmlspecialchars($dataFormatada) . '</text>';
    $svg .= '<title>Data: ' . htmlspecialchars($ponto['data']) . ' | Média: ' . number_format($ponto['valor'], 2, ',', '.') . ' pragas/planta</title>';
  }
  $svg .= '<text x="' . ($largura / 2) . '" y="22" font-size="13" font-weight="bold" fill="#333" text-anchor="middle">' . htmlspecialchars($pragaNome) . '</text>';
  $svg .= '<text x="' . ($margem - 30) . '" y="' . ($margem / 2) . '" font-size="11" fill="#666" text-anchor="middle" transform="rotate(-90 ' . ($margem - 30) . ' ' . ($margem / 2) . ')">Média de Pragas/Planta</text>';
  $svg .= '<text x="' . ($largura / 2) . '" y="' . ($altura - 5) . '" font-size="11" fill="#666" text-anchor="middle">Data e Hora das Atualizações</text>';
  $svg .= '</svg></div>';
  return $svg;
}

// Função para gerar recomendações baseadas na praga
function gerarRecomendacoes($nomePraga)
{
  $recomendacoes = [];
  $nomeLower = strtolower($nomePraga);

  // Recomendações genéricas
  $recomendacoes[] = "Monitore a área regularmente para detectar novos focos da praga.";
  $recomendacoes[] = "Mantenha o solo bem drenado e evite excesso de umidade.";
  $recomendacoes[] = "Realize rotação de culturas para evitar acúmulo de pragas.";

  // Recomendações específicas por tipo de praga
  if (strpos($nomeLower, 'lagarta') !== false || strpos($nomeLower, 'caterpillar') !== false) {
    $recomendacoes[] = "Use inseticidas biológicos à base de Bacillus thuringiensis.";
    $recomendacoes[] = "Instale armadilhas com feromônios para monitoramento.";
    $recomendacoes[] = "Remova manualmente as lagartas quando possível.";
  } elseif (strpos($nomeLower, 'pulgão') !== false || strpos($nomeLower, 'aphid') !== false) {
    $recomendacoes[] = "Aplique sabão inseticida ou óleo de neem.";
    $recomendacoes[] = "Introduza predadores naturais como joaninhas.";
    $recomendacoes[] = "Evite excesso de nitrogênio que favorece pulgões.";
  } elseif (strpos($nomeLower, 'ácaro') !== false || strpos($nomeLower, 'mite') !== false) {
    $recomendacoes[] = "Aumente a umidade relativa do ar com irrigação.";
    $recomendacoes[] = "Use acaricidas específicos para ácaros.";
    $recomendacoes[] = "Remova folhas muito infestadas.";
  } elseif (strpos($nomeLower, 'fungo') !== false || strpos($nomeLower, 'fungus') !== false) {
    $recomendacoes[] = "Aplique fungicidas preventivos antes do período chuvoso.";
    $recomendacoes[] = "Melhore a circulação de ar entre as plantas.";
    $recomendacoes[] = "Evite irrigação por aspersão nas folhas.";
  } elseif (strpos($nomeLower, 'besouro') !== false || strpos($nomeLower, 'beetle') !== false) {
    $recomendacoes[] = "Use armadilhas adesivas amarelas.";
    $recomendacoes[] = "Aplique inseticidas no início da manhã ou fim da tarde.";
    $recomendacoes[] = "Remova plantas hospedeiras alternativas.";
  } else {
    $recomendacoes[] = "Consulte um agrônomo para tratamento específico.";
    $recomendacoes[] = "Use produtos registrados para o controle desta praga.";
  }

  return $recomendacoes;
}

$recomendacoes = $pragaSelecionada ? gerarRecomendacoes($pragaSelecionada['Nome']) : [];

// Verificar ação AJAX
$acao = $_GET['acao'] ?? '';

// Se for requisição AJAX, retornar apenas o conteúdo necessário
if ($acao === 'surtos' && $pragaSelecionada) {
  // Buscar surtos para esta praga
  $surtos30Dias = [];
  try {
    $dataLimite = date('Y-m-d', strtotime('-30 days'));

    // Extrair palavras-chave do nome da praga
    $palavrasChave = extrairPalavrasChave($pragaSelecionada['Nome']);
    $nomePragaExato = trim($pragaSelecionada['Nome']);
    $nomePragaLike = '%' . $nomePragaExato . '%';

    // Preparar condições de busca de pragas similares
    $condicoesPraga = [];
    $params = [];

    $condicoesPraga[] = "LOWER(TRIM(Nome)) = LOWER(TRIM(:nomePraga))";
    $params[':nomePraga'] = $nomePragaExato;

    $condicoesPraga[] = "LOWER(Nome) LIKE LOWER(:nomePragaLike)";
    $params[':nomePragaLike'] = $nomePragaLike;

    foreach ($palavrasChave as $index => $palavra) {
      $paramName = ':palavraChave' . $index;
      $condicoesPraga[] = "LOWER(Nome) LIKE " . $paramName;
      $params[$paramName] = '%' . $palavra . '%';
    }

    $sqlCondicaoPraga = "(" . implode(" OR ", $condicoesPraga) . ")";

    $sql = "SELECT DATE(Data_Aparicao) as data_surto, COUNT(*) as total 
                FROM Pragas_Surtos 
                WHERE " . $sqlCondicaoPraga . "
                AND Data_Aparicao >= :dataLimite
                GROUP BY DATE(Data_Aparicao)
                ORDER BY Data_Aparicao ASC";

    $stmtSurtos = $pdo->prepare($sql);

    foreach ($params as $paramName => $valor) {
      $stmtSurtos->bindValue($paramName, $valor, PDO::PARAM_STR);
    }

    $stmtSurtos->bindValue(':dataLimite', $dataLimite, PDO::PARAM_STR);
    $stmtSurtos->execute();
    $surtos30Dias = $stmtSurtos->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $surtos30Dias = [];
  }

  if (!empty($surtos30Dias)): ?>
    <div class="info-box">
      <p class="mb-1"><strong>Praga:</strong> <?= htmlspecialchars($pragaSelecionada['Nome']); ?></p>
      <p class="mb-2"><strong>Região:</strong> Todas as regiões</p>
      <p class="mb-0"><small class="text-muted"><i class="bi bi-info-circle"></i> Incluindo surtos de pragas similares</small></p>
    </div>
    <div class="list-group" style="max-height: 180px; overflow-y: auto;">
      <?php foreach ($surtos30Dias as $surtos): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between">
            <span><i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($surtos['data_surto'])); ?></span>
            <span class="badge bg-warning text-dark"><?= $surtos['total']; ?> surto(s)</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 mb-0"><small class="text-muted">Total: <?= count($surtos30Dias); ?> dias com surtos</small></p>
  <?php else: ?>
    <p class="text-muted">Nenhum surto registrado nos últimos 30 dias para esta praga.</p>
  <?php endif;
  exit;
}

if ($acao === 'recomendacoes' && $pragaSelecionada) {
  $recomendacoes = gerarRecomendacoes($pragaSelecionada['Nome']);
  if (!empty($recomendacoes)): ?>
    <div class="info-box mb-2">
      <p class="mb-0"><strong>Para:</strong> <?= htmlspecialchars($pragaSelecionada['Nome']); ?></p>
    </div>
    <ul class="list-unstyled" style="max-height: 200px; overflow-y: auto;">
      <?php foreach ($recomendacoes as $recomendacao): ?>
        <li class="mb-2">
          <i class="bi bi-check-circle text-success"></i>
          <small><?= htmlspecialchars($recomendacao); ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="text-muted">Nenhuma recomendação disponível.</p>
<?php endif;
  exit;
}
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
  <title>Dashboard Admin - SMCPA</title>
</head>

<body>
  <div class="dashboard-container">
    <?php include_once(BASE_URL . '/includes/sidebar.php'); ?>
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
          <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCadastrarAdmin">
              <i class="bi bi-shield-lock"></i> Cadastrar Admin
            </button>
          <?php endif; ?>
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
        <!-- Dashboard de Pragas (Funcionalidades do Usuário) -->
        <div class="dashboard-grid">
          <!-- Bloco Pragas -->
          <div class="dashboard-item card-pragas blue-item" id="vendas-hoje" style="overflow-y: auto;">
            <h5 class="mb-3"><i class="bi bi-bug-fill text-primary"></i> Todas as Pragas Registradas</h5>
            <?php if (!empty($todasPragas)): ?>
              <div class="list-group" style="max-height: 250px; overflow-y: auto;">
                <?php foreach ($todasPragas as $praga): ?>
                  <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                      <div style="flex: 1;">
                        <h6 class="mb-1"><?= htmlspecialchars($praga['Nome']); ?></h6>
                        <p class="mb-1"><small class="text-muted"><i class="bi bi-flower1"></i> <?= htmlspecialchars($praga['Planta_Hospedeira']); ?></small></p>
                        <p class="mb-0"><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($praga['Localidade']); ?></small></p>
                      </div>
                      <small class="text-muted"><?= date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?></small>
                    </div>
                    <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr;">
                      <a href="../cadastro/atualizar_praga.php?id=<?= $praga['ID']; ?>" class="btn btn-sm btn-primary" title="Atualizar praga">
                        <i class="bi bi-pencil-square"></i> Atualizar
                      </a>
                      <a href="gerar_relatorio.php?id=<?= $praga['ID']; ?>" class="btn btn-sm btn-info" target="_blank" title="Gerar relatório">
                        <i class="bi bi-file-earmark-pdf"></i> Relatório
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="mt-3 text-muted">Nenhuma praga cadastrada ainda.</p>
            <?php endif; ?>
          </div>

          <!-- Bloco Surtos -->
          <div class="dashboard-item card-surtos orange-item" id="vendas-periodicas" style="overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Surtos (Últimos 30 dias)</h5>
              <div class="d-flex gap-2 align-items-center">
                <?php if (!empty($todasPragas)): ?>
                  <select class="form-select select-praga" id="select-surtos" style="width: auto; min-width: 180px; font-size: 0.85rem;" onchange="atualizarSurtos(this.value)">
                    <option value="">Todas as pragas</option>
                    <?php foreach ($todasPragas as $praga): ?>
                      <option value="<?= $praga['ID']; ?>" <?= ($pragaSelecionadaID == $praga['ID']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($praga['Nome']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>
                <a href="../cadastro/cadsurto.php" class="btn btn-sm btn-light" title="Cadastrar novo surto">
                  <i class="bi bi-plus-circle"></i> Novo Surto
                </a>
              </div>
            </div>
            <div id="conteudo-surtos">
              <?php if ($pragaSelecionada && !empty($surtos30Dias)): ?>
                <div class="info-box">
                  <p class="mb-1"><strong>Praga:</strong> <?= htmlspecialchars($pragaSelecionada['Nome']); ?></p>
                  <p class="mb-2"><strong>Região:</strong> Todas as regiões</p>
                  <p class="mb-0"><small class="text-muted"><i class="bi bi-info-circle"></i> Incluindo surtos de pragas similares</small></p>
                </div>
                <div class="list-group" style="max-height: 180px; overflow-y: auto;">
                  <?php foreach ($surtos30Dias as $surtos): ?>
                    <div class="list-group-item">
                      <div class="d-flex justify-content-between">
                        <span><i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($surtos['data_surto'])); ?></span>
                        <span class="badge bg-warning text-dark"><?= $surtos['total']; ?> surto(s)</span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <p class="mt-2 mb-0"><small class="text-muted">Total: <?= count($surtos30Dias); ?> dias com surtos</small></p>
              <?php elseif ($pragaSelecionada): ?>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle"></i> Nenhum surto registrado nos últimos 30 dias para "<strong><?= htmlspecialchars($pragaSelecionada['Nome']); ?></strong>".
                </div>
              <?php else: ?>
                <p class="text-muted">Selecione uma praga para ver os surtos.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bloco Recomendações -->
          <div class="dashboard-item card-recomendacoes green-item" id="receber-hoje" style="overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0"><i class="bi bi-lightbulb-fill text-success"></i> Recomendações</h5>
              <?php if (!empty($todasPragas)): ?>
                <select class="form-select select-praga" id="select-recomendacoes" style="width: auto; min-width: 180px; font-size: 0.85rem;" onchange="atualizarRecomendacoes(this.value)">
                  <option value="">Selecione uma praga</option>
                  <?php foreach ($todasPragas as $praga): ?>
                    <option value="<?= $praga['ID']; ?>" <?= ($pragaSelecionadaID == $praga['ID']) ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($praga['Nome']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
            <div id="conteudo-recomendacoes">
              <?php if ($pragaSelecionada && !empty($recomendacoes)): ?>
                <div class="info-box mb-2">
                  <p class="mb-0"><strong>Para:</strong> <?= htmlspecialchars($pragaSelecionada['Nome']); ?></p>
                </div>
                <ul class="list-unstyled" style="max-height: 200px; overflow-y: auto;">
                  <?php foreach ($recomendacoes as $index => $recomendacao): ?>
                    <li class="mb-2">
                      <i class="bi bi-check-circle text-success"></i>
                      <small><?= htmlspecialchars($recomendacao); ?></small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php elseif ($pragaSelecionada): ?>
                <p class="text-muted">Nenhuma recomendação disponível.</p>
              <?php else: ?>
                <p class="text-muted">Selecione uma praga para ver recomendações.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bloco Relatórios -->
          <div class="dashboard-item card-relatorios small-item" id="tabela-vendas">
            <h5 class="mb-3"><i class="bi bi-file-earmark-text text-purple"></i> Relatórios</h5>
            <div class="mt-3">
              <?php if (!empty($pragasAdmin)): ?>
                <form action="gerar_relatorio.php" method="GET" target="_blank">
                  <div class="mb-3">
                    <label for="praga_relatorio" class="form-label">Selecione a Praga para Gerar Relatório:</label>
                    <select class="form-select" id="praga_relatorio" name="id" required>
                      <option value="">-- Selecione uma praga --</option>
                      <?php foreach ($pragasAdmin as $praga): ?>
                        <option value="<?= $praga['ID']; ?>">
                          <?= htmlspecialchars($praga['Nome']); ?>
                          - <?= htmlspecialchars($praga['Planta_Hospedeira']); ?>
                          (<?= date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-file-earmark-pdf"></i> Gerar Relatório
                  </button>
                </form>
                <div class="mt-3">
                  <small class="text-muted">
                    <i class="bi bi-info-circle"></i> Você pode gerar relatórios apenas das pragas que você cadastrou. Para visualizar relatórios de outras pragas, acesse <a href="filtros_pragas.php">Filtros de Pragas</a>.
                  </small>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle"></i> Você ainda não cadastrou nenhuma praga.
                  <a href="/SMCPA/paginas/cadastro/cadpraga.php" class="alert-link">Cadastre uma praga</a> para gerar relatórios.
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bloco Gráfico de Surtos -->
          <div class="dashboard-item card-grafico" id="grafico-vendas" style="max-height: 350px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0"><i class="bi bi-graph-up text-info"></i> Evolução do Surtos (Últimos 30 dias)</h5>
              <?php if (!empty($todasPragas)): ?>
                <select class="form-select select-praga" id="select-grafico" style="width: auto; min-width: 180px; font-size: 0.85rem;" onchange="atualizarGrafico(this.value)">
                  <option value="">Selecione uma praga</option>
                  <?php foreach ($todasPragas as $praga): ?>
                    <option value="<?= $praga['ID']; ?>" <?= ($pragaSelecionadaID == $praga['ID']) ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($praga['Nome']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
            <div id="conteudo-grafico" style="overflow: hidden;">
              <?php if ($pragaSelecionada && !empty($dadosGrafico)): ?>
                <?php
                $pragaNomeParaGrafico = $pragaSelecionada['Nome'] ?? 'Evolução da Praga';
                echo gerarGraficoSVG($dadosGrafico, $pragaNomeParaGrafico);
                ?>
              <?php elseif ($pragaSelecionada): ?>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle"></i> Nenhum dado disponível para gerar o gráfico desta praga.
                </div>
              <?php else: ?>
                <p class="text-muted">Selecione uma praga para ver o gráfico de surtos.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Mensagens de Sucesso/Erro (apenas para cadastro de admin) -->
        <?php if (isset($sucesso_cadastro_admin)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 20px;">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($sucesso_cadastro_admin); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (isset($erro_cadastro_admin)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 20px;">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($erro_cadastro_admin); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <!-- Modal para Cadastrar Administrador -->
  <?php if ($isAdmin): ?>
    <div class="modal fade" id="modalCadastrarAdmin" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">
              <i class="bi bi-shield-lock"></i> Cadastrar Novo Administrador
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST">
            <div class="modal-body">
              <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <strong>Atenção:</strong> Apenas administradores podem cadastrar outros administradores.
              </div>
              <div class="mb-3">
                <label for="usuario_admin" class="form-label">Nome do Administrador</label>
                <input type="text" class="form-control" id="usuario_admin"
                  name="usuario_admin" required placeholder="Digite o nome do administrador">
              </div>
              <div class="mb-3">
                <label for="email_admin" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email_admin"
                  name="email_admin" required placeholder="Digite o e-mail">
              </div>
              <div class="mb-3">
                <label for="senha_admin" class="form-label">Senha</label>
                <input type="password" class="form-control" id="senha_admin"
                  name="senha_admin" required placeholder="Digite a senha" minlength="6">
                <small class="text-muted">A senha deve ter no mínimo 6 caracteres e será criptografada.</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="cadastrar_admin" class="btn btn-danger">
                <i class="bi bi-shield-check"></i> Cadastrar Administrador
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/SMCPA/js/menu.js"></script>
  <script>
    // Dados das pragas para JavaScript
    const todasPragas = <?= json_encode($todasPragas); ?>;
    const localidadeUsuario = <?= json_encode($localidadeUsuario); ?>;

    // Função para atualizar surtos via AJAX
    function atualizarSurtos(pragaID) {
      if (!pragaID) {
        document.getElementById('conteudo-surtos').innerHTML = '<p class="mt-3">Selecione uma praga para ver os surtos.</p>';
        return;
      }

      const praga = todasPragas.find(p => p.ID == pragaID);
      if (!praga) return;

      // Fazer requisição AJAX
      fetch(`dashboardadm.php?praga_id=${pragaID}&acao=surtos`)
        .then(response => response.text())
        .then(html => {
          document.getElementById('conteudo-surtos').innerHTML = html;
        })
        .catch(error => console.error('Erro:', error));
    }

    // Função para atualizar recomendações via AJAX
    function atualizarRecomendacoes(pragaID) {
      if (!pragaID) {
        document.getElementById('conteudo-recomendacoes').innerHTML = '<p class="mt-3">Selecione uma praga para ver recomendações.</p>';
        return;
      }

      const praga = todasPragas.find(p => p.ID == pragaID);
      if (!praga) return;

      // Fazer requisição AJAX
      fetch(`dashboardadm.php?praga_id=${pragaID}&acao=recomendacoes`)
        .then(response => response.text())
        .then(html => {
          document.getElementById('conteudo-recomendacoes').innerHTML = html;
        })
        .catch(error => console.error('Erro:', error));
    }

    // Função para atualizar gráfico via AJAX
    function atualizarGrafico(pragaID) {
      if (!pragaID) {
        document.getElementById('conteudo-grafico').innerHTML = '<p class="mt-3">Selecione uma praga para ver o gráfico de surtos.</p>';
        return;
      }

      // Recarregar página com nova praga selecionada
      window.location.href = `dashboardadm.php?praga_id=${pragaID}`;
    }

    // Chart handled server-side (SVG). No Chart.js initialization here for admin.
  </script>
</body>

</html>