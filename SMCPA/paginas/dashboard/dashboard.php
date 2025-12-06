<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Inicia a sessão para manter o login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers para prevenir cache e garantir que o botão voltar não funcione após logout
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id']) && !isset($_SESSION['logado'])) {
    // Se não estiver logado, redireciona para login
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
// Se não houver imagem, usar imagem padrão
if (!$imagemPerfil) {
    $imagemPerfil = '/SMCPA/imgs/logotrbf.png';
}

// Buscar todas as pragas do usuário
$todasPragas = [];
try {
    $stmtPragas = $pdo->prepare("SELECT ID, Nome, Planta_Hospedeira, Localidade, Data_Aparicao 
                                 FROM Pragas_Surtos 
                                 WHERE ID_Usuario = :usuarioID 
                                 ORDER BY Data_Aparicao DESC");
    $stmtPragas->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmtPragas->execute();
    $todasPragas = $stmtPragas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $todasPragas = [];
}

// Buscar localidade do usuário do cadastro (prioridade) ou da primeira praga
$localidadeUsuario = 'Região não especificada';
try {
    // Verificar se a coluna localizacao existe
    try {
        $stmtCheck = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'localizacao'");
        $colunaExiste = $stmtCheck->rowCount() > 0;
        
        if (!$colunaExiste) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN localizacao VARCHAR(255) DEFAULT NULL");
        }
    } catch (PDOException $e) {
        // Ignorar erro - coluna provavelmente já existe
    }
    
    // Buscar localização do usuário no cadastro
    $stmtLocalizacao = $pdo->prepare("SELECT localizacao FROM usuarios WHERE id = :usuarioID");
    $stmtLocalizacao->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmtLocalizacao->execute();
    $resultadoLocalizacao = $stmtLocalizacao->fetch(PDO::FETCH_ASSOC);
    
    if ($resultadoLocalizacao && !empty($resultadoLocalizacao['localizacao'])) {
        $localidadeUsuario = trim($resultadoLocalizacao['localizacao']);
    } elseif (!empty($todasPragas)) {
        // Se não tiver localização no cadastro, usar da primeira praga
        $localidadeUsuario = $todasPragas[0]['Localidade'] ?? 'Região não especificada';
    }
} catch (PDOException $e) {
    // Em caso de erro, tentar usar a localidade da primeira praga
    if (!empty($todasPragas)) {
        $localidadeUsuario = $todasPragas[0]['Localidade'] ?? 'Região não especificada';
    }
}

// Buscar todas as localidades próximas (para prevenção)
$localidadesProximas = [];
if (!empty($localidadeUsuario) && $localidadeUsuario != 'Região não especificada') {
    try {
        // Buscar localidades que contenham palavras-chave da localidade do usuário
        $palavrasLocalidade = explode(' ', strtolower(trim($localidadeUsuario)));
        $localidadesProximas = [$localidadeUsuario]; // Sempre incluir a própria localidade
        
        foreach ($palavrasLocalidade as $palavra) {
            $palavra = trim($palavra);
            if (strlen($palavra) > 2) { // Ignorar palavras muito curtas
                $stmtLocalidades = $pdo->prepare("SELECT DISTINCT Localidade 
                                                 FROM Pragas_Surtos 
                                                 WHERE LOWER(Localidade) LIKE :palavra 
                                                 AND Localidade != '' 
                                                 AND Localidade IS NOT NULL
                                                 AND LOWER(Localidade) != LOWER(:localidadeAtual)
                                                 LIMIT 10");
                $palavraLike = '%' . $palavra . '%';
                $stmtLocalidades->bindParam(':palavra', $palavraLike, PDO::PARAM_STR);
                $stmtLocalidades->bindParam(':localidadeAtual', $localidadeUsuario, PDO::PARAM_STR);
                $stmtLocalidades->execute();
                $resultados = $stmtLocalidades->fetchAll(PDO::FETCH_COLUMN);
                $localidadesProximas = array_merge($localidadesProximas, $resultados);
            }
        }
        $localidadesProximas = array_unique($localidadesProximas);
    } catch (PDOException $e) {
        $localidadesProximas = [$localidadeUsuario];
    }
} else {
    $localidadesProximas = [];
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

// Função para extrair palavras-chave do nome da praga (para busca de pragas similares)
function extrairPalavrasChave($nome) {
    $nome = strtolower(trim($nome));
    // Remover palavras comuns muito curtas
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

// Buscar pragas do usuário com imagens para o seletor (usando DISTINCT por nome para evitar duplicatas)
$pragasComImagens = [];
try {
    $stmtPragasImagens = $pdo->prepare("SELECT DISTINCT Nome, MIN(ID) as ID, MIN(Planta_Hospedeira) as Planta_Hospedeira
                                        FROM Pragas_Surtos 
                                        WHERE ID_Usuario = :usuarioID 
                                        AND Imagem_Not_Null IS NOT NULL 
                                        AND Imagem_Not_Null != ''
                                        GROUP BY Nome
                                        ORDER BY Nome ASC");
    $stmtPragasImagens->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmtPragasImagens->execute();
    $pragasComImagens = $stmtPragasImagens->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pragasComImagens = [];
}

// Preparar dados para o gráfico (baseado nas atualizações do cadastro de pragas do usuário com fotos)
$dadosGrafico = [];
$pragaSelecionadaGrafico = null;
$pragaGraficoID = $_GET['praga_grafico'] ?? ($pragaSelecionadaID ?? null);

// Buscar praga selecionada para o gráfico
if ($pragaGraficoID) {
    try {
        // Buscar o nome da praga pelo ID (pode ser qualquer atualização dessa praga)
        $stmtPragaSelecionada = $pdo->prepare("SELECT DISTINCT Nome, MIN(ID) as ID, MIN(Planta_Hospedeira) as Planta_Hospedeira
                                                FROM Pragas_Surtos 
                                                WHERE ID = :pragaID 
                                                AND ID_Usuario = :usuarioID 
                                                AND Imagem_Not_Null IS NOT NULL 
                                                AND Imagem_Not_Null != ''
                                                GROUP BY Nome
                                                LIMIT 1");
        $stmtPragaSelecionada->bindParam(':pragaID', $pragaGraficoID, PDO::PARAM_INT);
        $stmtPragaSelecionada->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
        $stmtPragaSelecionada->execute();
        $pragaResult = $stmtPragaSelecionada->fetch(PDO::FETCH_ASSOC);
        
        if ($pragaResult) {
            $pragaSelecionadaGrafico = $pragaResult;
        } else {
            // Tentar encontrar pelo ID mínimo na lista
            foreach ($pragasComImagens as $praga) {
                if ($praga['ID'] == $pragaGraficoID) {
                    $pragaSelecionadaGrafico = $praga;
                    break;
                }
            }
        }
    } catch (PDOException $e) {
        // Em caso de erro, tentar encontrar na lista
        foreach ($pragasComImagens as $praga) {
            if ($praga['ID'] == $pragaGraficoID) {
                $pragaSelecionadaGrafico = $praga;
                break;
            }
        }
    }
}

// Função para gerar gráfico SVG em PHP puro (sem dependências externas)
function gerarGraficoSVG($dados, $pragaNome = 'Evolução da Infestação') {
    if (empty($dados)) {
        return '';
    }
    
    // Dimensões do gráfico (ajustadas para evitar barra de rolagem)
    $largura = 720;
    $altura = 300;
    $margem = 40;
    $areaLargura = $largura - (2 * $margem);
    $areaAltura = $altura - (2 * $margem);
    // Compressão horizontal para reduzir espaçamento entre pontos
    $scaleX = 0.9;
    
    // Extrair valores mínimo e máximo
    $valores = array_map(fn($d) => (float)$d['media_pragas'], $dados);
    $minValor = min($valores);
    $maxValor = max($valores);
    
    // Se todos os valores são iguais, ajustar a escala
    if ($minValor == $maxValor) {
        $minValor = $maxValor * 0.8;
        $maxValor = $maxValor * 1.2;
    }
    
    $intervalo = $maxValor - $minValor;
    
    // Calcular posições dos pontos
    $pontos = [];
    for ($i = 0; $i < count($dados); $i++) {
      $offsetX = ($areaLargura * (1 - $scaleX)) / 2;
      $x = $margem + $offsetX + ($i / (count($dados) - 1 ?: 1)) * $areaLargura * $scaleX;
      $y = $altura - $margem - (($dados[$i]['media_pragas'] - $minValor) / ($intervalo ?: 1)) * $areaAltura;
      $pontos[] = ['x' => $x, 'y' => $y, 'valor' => $dados[$i]['media_pragas'], 'data' => $dados[$i]['Data_Aparicao']];
    }
    
    // Começar SVG (responsivo e imóvel - sem overflow)
    $svg = '<div style="width: 100%; overflow: hidden; display: flex; align-items: center; justify-content: center;">';
    $svg .= '<svg viewBox="0 0 ' . $largura . ' ' . $altura . '" preserveAspectRatio="xMidYMid meet" width="100%" height="auto" xmlns="http://www.w3.org/2000/svg" style="max-width:100%; max-height:100%; border: 1px solid #ddd; border-radius: 4px; background: white; display: block;">';
    
    // Fundo
    $svg .= '<rect width="' . $largura . '" height="' . $altura . '" fill="white"/>';
    
    // Grade de fundo (linhas horizontais)
    $numLinhas = 5;
    for ($i = 0; $i <= $numLinhas; $i++) {
        $y = $margem + ($i / $numLinhas) * $areaAltura;
        $svg .= '<line x1="' . $margem . '" y1="' . $y . '" x2="' . ($largura - $margem) . '" y2="' . $y . '" stroke="#e0e0e0" stroke-width="1"/>';
        
        // Valores da escala (Y)
        $valor = $maxValor - ($i / $numLinhas) * $intervalo;
        $svg .= '<text x="' . ($margem - 10) . '" y="' . ($y + 5) . '" font-size="10" fill="#666" text-anchor="end">' . number_format($valor, 1, ',', '.') . '</text>';
    }
    
    // Eixo X (horizontal)
    $svg .= '<line x1="' . $margem . '" y1="' . ($altura - $margem) . '" x2="' . ($largura - $margem) . '" y2="' . ($altura - $margem) . '" stroke="#333" stroke-width="2"/>';
    
    // Eixo Y (vertical)
    $svg .= '<line x1="' . $margem . '" y1="' . $margem . '" x2="' . $margem . '" y2="' . ($altura - $margem) . '" stroke="#333" stroke-width="2"/>';
    
    // Desenhar linha de dados
    if (count($pontos) > 1) {
        $pathData = 'M ' . $pontos[0]['x'] . ' ' . $pontos[0]['y'];
        for ($i = 1; $i < count($pontos); $i++) {
            $pathData .= ' L ' . $pontos[$i]['x'] . ' ' . $pontos[$i]['y'];
        }
        $svg .= '<path d="' . $pathData . '" stroke="#dc3545" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
    }
    
    // Desenhar área preenchida (gradiente simulado)
    if (count($pontos) > 1) {
        $pathArea = 'M ' . $pontos[0]['x'] . ' ' . $pontos[0]['y'];
        for ($i = 1; $i < count($pontos); $i++) {
            $pathArea .= ' L ' . $pontos[$i]['x'] . ' ' . $pontos[$i]['y'];
        }
        $pathArea .= ' L ' . $pontos[count($pontos)-1]['x'] . ' ' . ($altura - $margem);
        $pathArea .= ' L ' . $pontos[0]['x'] . ' ' . ($altura - $margem) . ' Z';
        $svg .= '<path d="' . $pathArea . '" fill="rgba(220, 53, 69, 0.2)"/>';
    }
    
    // Desenhar pontos e labels
    for ($i = 0; $i < count($pontos); $i++) {
        $ponto = $pontos[$i];
        
        // Determinar cor do ponto (baseado em mudança)
        $cor = '#dc3545'; // Vermelho padrão
        if ($i > 0) {
            $valAnterior = $pontos[$i-1]['valor'];
            $valAtual = $ponto['valor'];
            if ($valAtual < $valAnterior) {
                $cor = '#28a745'; // Verde para queda
            } elseif ($valAtual > $valAnterior) {
                $cor = '#dc3545'; // Vermelho para aumento
            } else {
                $cor = '#ffc107'; // Amarelo para sem mudança
            }
        }
        
        // Círculo do ponto
        $svg .= '<circle cx="' . $ponto['x'] . '" cy="' . $ponto['y'] . '" r="4" fill="' . $cor . '" stroke="white" stroke-width="2"/>';
        
        // Linha vertical até o eixo X
        $svg .= '<line x1="' . $ponto['x'] . '" y1="' . $ponto['y'] . '" x2="' . $ponto['x'] . '" y2="' . ($altura - $margem) . '" stroke="#ddd" stroke-width="1" stroke-dasharray="2,2"/>';
        
        // Label com data (X)
        $data = DateTime::createFromFormat('Y-m-d H:i:s', $ponto['data']);
        $dataFormatada = $data ? $data->format('d/m/y H:i') : $ponto['data'];
        $svg .= '<text x="' . $ponto['x'] . '" y="' . ($altura - $margem + 18) . '" font-size="9" fill="#666" text-anchor="middle">' . htmlspecialchars($dataFormatada) . '</text>';
        
        // Tooltip com valor (ao passar mouse)
        $svg .= '<title>Data: ' . htmlspecialchars($ponto['data']) . ' | Média: ' . number_format($ponto['valor'], 2, ',', '.') . ' pragas/planta</title>';
    }
    
    // Título
    $svg .= '<text x="' . ($largura / 2) . '" y="22" font-size="13" font-weight="bold" fill="#333" text-anchor="middle">' . htmlspecialchars($pragaNome) . '</text>';
    
    // Labels dos eixos
    $svg .= '<text x="' . ($margem - 30) . '" y="' . ($margem / 2) . '" font-size="11" fill="#666" text-anchor="middle" transform="rotate(-90 ' . ($margem - 30) . ' ' . ($margem / 2) . ')">Média de Pragas/Planta</text>';
    $svg .= '<text x="' . ($largura / 2) . '" y="' . ($altura - 5) . '" font-size="11" fill="#666" text-anchor="middle">Data e Hora das Atualizações</text>';
    
    $svg .= '</svg></div>';
    
    return $svg;
}

try {
    // Buscar dados de infestação (média de pragas por planta) da praga selecionada
    // SEM filtro de datas - mostra TODAS as atualizações do histórico
    if ($pragaSelecionadaGrafico) {
        $nomePragaGrafico = $pragaSelecionadaGrafico['Nome'];
        $pragaIDGrafico = $pragaSelecionadaGrafico['ID'];
        // Buscar do histórico de atualizações (tabela separada)
        $sql = "SELECT data_atualizacao as Data_Aparicao, 
                 media_pragas_planta,
                 severidade
          FROM historico_pragas 
          WHERE ID_Praga = :pragaID
          AND media_pragas_planta IS NOT NULL
          AND media_pragas_planta > 0
          ORDER BY data_atualizacao ASC";
        
        $stmtGrafico = $pdo->prepare($sql);
        $stmtGrafico->bindParam(':pragaID', $pragaIDGrafico, PDO::PARAM_INT);
        $stmtGrafico->execute();
        $dadosInfestacao = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);
        
        // Criar array de dados para o gráfico
        foreach ($dadosInfestacao as $registro) {
            if ($registro['media_pragas_planta'] !== null && $registro['media_pragas_planta'] > 0) {
                $dadosGrafico[] = [
                    'Data_Aparicao' => $registro['Data_Aparicao'],
                    'media_pragas' => round((float)$registro['media_pragas_planta'], 2)
                ];
            }
        }
    }
} catch (PDOException $e) {
    $dadosGrafico = [];
    error_log("Erro ao buscar dados do gráfico: " . $e->getMessage());
}

// Função para gerar recomendações baseadas na praga
function gerarRecomendacoes($nomePraga) {
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

// Buscar TODOS os surtos da região e regiões próximas (para prevenção - não filtrado por praga)
$todosSurtosRegiao = [];
try {
    $dataLimite = date('Y-m-d', strtotime('-30 days'));
    
    // Preparar condições de localidades (usando LIKE para busca mais flexível)
    $condicoesLocalidade = [];
    $paramsLocalidade = [];
    
    if (!empty($localidadeUsuario) && $localidadeUsuario != 'Região não especificada') {
        // Buscar localidades que contenham palavras-chave da localização do usuário
        $palavrasLocalidadeUsuario = explode(' ', strtolower(trim($localidadeUsuario)));
        
        // Adicionar busca exata e por palavras-chave
        $condicoesLocalidade[] = "LOWER(Localidade) = LOWER(:localidadeExata)";
        $paramsLocalidade[':localidadeExata'] = $localidadeUsuario;
        
        // Adicionar busca parcial (contém a localização do usuário)
        $condicoesLocalidade[] = "LOWER(Localidade) LIKE LOWER(:localidadeParcial)";
        $paramsLocalidade[':localidadeParcial'] = '%' . $localidadeUsuario . '%';
        
        // Adicionar busca por palavras-chave da localização do usuário
        foreach ($palavrasLocalidadeUsuario as $index => $palavra) {
            $palavra = trim($palavra);
            if (strlen($palavra) > 2) { // Ignorar palavras muito curtas
                $paramName = ':palavraLocalidade' . $index;
                $condicoesLocalidade[] = "LOWER(Localidade) LIKE " . $paramName;
                $paramsLocalidade[$paramName] = '%' . $palavra . '%';
            }
        }
        
        // Adicionar localidades próximas encontradas
        foreach ($localidadesProximas as $index => $localidade) {
            if ($localidade != $localidadeUsuario && !empty($localidade)) {
                $paramName = ':localidadeProxima' . $index;
                $condicoesLocalidade[] = "LOWER(Localidade) LIKE LOWER(" . $paramName . ")";
                $paramsLocalidade[$paramName] = '%' . $localidade . '%';
            }
        }
        
        if (!empty($condicoesLocalidade)) {
            $sqlCondicaoLocalidade = "(" . implode(" OR ", $condicoesLocalidade) . ")";
            
            // Buscar surtos individuais (não agrupados) para mostrar todos
            $sql = "SELECT ID, Nome, Planta_Hospedeira, Localidade, DATE(Data_Aparicao) as data_surto, Data_Aparicao, Observacoes
                    FROM Pragas_Surtos 
                    WHERE (" . $sqlCondicaoLocalidade . " AND Localidade != '' AND Localidade IS NOT NULL)
                    AND Data_Aparicao >= :dataLimite
                    ORDER BY Data_Aparicao DESC
                    LIMIT 50";
            
            $stmtTodosSurtos = $pdo->prepare($sql);
            
            foreach ($paramsLocalidade as $paramName => $valor) {
                $stmtTodosSurtos->bindValue($paramName, $valor, PDO::PARAM_STR);
            }
            
            $stmtTodosSurtos->bindValue(':dataLimite', $dataLimite, PDO::PARAM_STR);
            $stmtTodosSurtos->execute();
            $todosSurtosRegiao = $stmtTodosSurtos->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $todosSurtosRegiao = [];
    // Log do erro para debug (remover em produção)
    error_log("Erro ao buscar surtos da região: " . $e->getMessage());
}

// Buscar alertas de pragas não lidos
$alertasPragas = [];
$totalAlertasNaoLidos = 0;
try {
    // Criar tabela se não existir
    try {
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
    
    // Buscar alertas não lidos do usuário
    $stmtAlertas = $pdo->prepare("
        SELECT a.ID, a.ID_Praga, a.Nome_Praga, a.Localidade, a.Data_Criacao,
               u.usuario as Usuario_Origem, ps.Planta_Hospedeira
        FROM alertas_pragas a
        LEFT JOIN usuarios u ON a.ID_Usuario_Origem = u.id
        LEFT JOIN Pragas_Surtos ps ON a.ID_Praga = ps.ID
        WHERE a.ID_Usuario_Destino = :usuarioID 
        AND a.Lido = 0
        ORDER BY a.Data_Criacao DESC
        LIMIT 10
    ");
    $stmtAlertas->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmtAlertas->execute();
    $alertasPragas = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar total de alertas não lidos
    $stmtCount = $pdo->prepare("SELECT COUNT(*) as total FROM alertas_pragas WHERE ID_Usuario_Destino = :usuarioID AND Lido = 0");
    $stmtCount->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmtCount->execute();
    $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalAlertasNaoLidos = $countResult['total'] ?? 0;
} catch (PDOException $e) {
    $alertasPragas = [];
    $totalAlertasNaoLidos = 0;
}

// Verificar ação AJAX (definir antes de usar)
$acao = $_GET['acao'] ?? '';

// Processar marcação de alerta como lido
if (isset($_GET['marcar_lido']) && !empty($_GET['marcar_lido'])) {
    $alertaID = intval($_GET['marcar_lido']);
    try {
        $stmtMarcar = $pdo->prepare("UPDATE alertas_pragas SET Lido = 1, Data_Leitura = NOW() WHERE ID = :alertaID AND ID_Usuario_Destino = :usuarioID");
        $stmtMarcar->bindParam(':alertaID', $alertaID, PDO::PARAM_INT);
        $stmtMarcar->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
        $stmtMarcar->execute();
        
        // Redirecionar para remover o parâmetro da URL
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        // Ignorar erro
    }
}

// Processar marcação de todos os alertas como lidos
if ($acao === 'marcar_todos_lidos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmtMarcarTodos = $pdo->prepare("UPDATE alertas_pragas SET Lido = 1, Data_Leitura = NOW() WHERE ID_Usuario_Destino = :usuarioID AND Lido = 0");
        $stmtMarcarTodos->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
        $stmtMarcarTodos->execute();
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Se for requisição AJAX, retornar apenas o conteúdo necessário

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

// Endpoint AJAX para buscar dados do gráfico
if ($acao === 'dados_grafico') {
    header('Content-Type: application/json');
    
    $pragaIDGrafico = $_GET['praga_id'] ?? null;
    $dadosGraficoAjax = [];
    $pragaNomeGraficoAjax = 'Todas as Pragas';
    
    try {
        $dataLimite = date('Y-m-d', strtotime('-30 days'));
        
        if ($pragaIDGrafico) {
            // Buscar nome da praga pelo ID
            $stmtPragaNome = $pdo->prepare("SELECT DISTINCT Nome FROM Pragas_Surtos 
                                            WHERE ID = :pragaID AND ID_Usuario = :usuarioID 
                                            AND Imagem_Not_Null IS NOT NULL 
                                            AND Imagem_Not_Null != ''
                                            LIMIT 1");
            $stmtPragaNome->bindParam(':pragaID', $pragaIDGrafico, PDO::PARAM_INT);
            $stmtPragaNome->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
            $stmtPragaNome->execute();
            $pragaResult = $stmtPragaNome->fetch(PDO::FETCH_ASSOC);
            
            if ($pragaResult) {
                $nomePragaGraficoAjax = $pragaResult['Nome'];
                $pragaNomeGraficoAjax = $nomePragaGraficoAjax;
                
                $sql = "SELECT Data_Aparicao, 
                               media_pragas_planta,
                               severidade
                        FROM Pragas_Surtos 
                        WHERE ID_Usuario = :usuarioID 
                        AND Nome = :nomePraga
                        AND Imagem_Not_Null IS NOT NULL 
                        AND Imagem_Not_Null != ''
                        AND media_pragas_planta IS NOT NULL
                        AND media_pragas_planta > 0
                        AND Data_Aparicao >= :dataLimite
                        ORDER BY Data_Aparicao ASC";
                
                $stmtGrafico = $pdo->prepare($sql);
                $stmtGrafico->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
                $stmtGrafico->bindParam(':nomePraga', $nomePragaGraficoAjax, PDO::PARAM_STR);
                $stmtGrafico->bindValue(':dataLimite', $dataLimite, PDO::PARAM_STR);
                $stmtGrafico->execute();
                $dadosInfestacao = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $dadosInfestacao = [];
            }
        } else {
            // Buscar todas as pragas
            $sql = "SELECT Data_Aparicao, 
                           media_pragas_planta,
                           severidade,
                           Nome
                    FROM Pragas_Surtos 
                    WHERE ID_Usuario = :usuarioID 
                    AND Imagem_Not_Null IS NOT NULL 
                    AND Imagem_Not_Null != ''
                    AND media_pragas_planta IS NOT NULL
                    AND media_pragas_planta > 0
                    AND Data_Aparicao >= :dataLimite
                    ORDER BY Data_Aparicao ASC";
            
            $stmtGrafico = $pdo->prepare($sql);
            $stmtGrafico->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
            $stmtGrafico->bindValue(':dataLimite', $dataLimite, PDO::PARAM_STR);
            $stmtGrafico->execute();
            $dadosInfestacao = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Criar um ponto no gráfico para cada atualização individual
        foreach ($dadosInfestacao as $registro) {
            if ($registro['media_pragas_planta'] !== null && $registro['media_pragas_planta'] > 0) {
                $dadosGraficoAjax[] = [
                    'Data_Aparicao' => $registro['Data_Aparicao'],
                    'media_pragas' => round((float)$registro['media_pragas_planta'], 2)
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'dados' => $dadosGraficoAjax,
            'pragaNome' => $pragaNomeGraficoAjax
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
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
  <title>Dashboard - SMCPA</title>
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
    <main class="main-content">
      <header class="topbar">
        <div class="left">
        </div>
        <div class="right d-flex align-items-center gap-3">
          <!-- Botão de Alertas -->
          <?php if ($totalAlertasNaoLidos > 0): ?>
            <div class="dropdown">
              <button class="btn btn-outline-light position-relative" type="button" id="dropdownAlertas" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell-fill"></i> Alertas
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                  <?= $totalAlertasNaoLidos; ?>
                </span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownAlertas" style="min-width: 350px; max-width: 400px;">
                <li><h6 class="dropdown-header">Novas Pragas na Sua Região</h6></li>
                <?php foreach ($alertasPragas as $alerta): ?>
                  <li>
                    <div class="dropdown-item" data-alerta-id="<?= $alerta['ID']; ?>">
                      <div class="d-flex justify-content-between align-items-start">
                        <a href="gerar_relatorio.php?id=<?= $alerta['ID_Praga']; ?>" target="_blank" style="text-decoration: none; color: inherit; flex: 1;">
                          <strong style="font-size: 0.9rem; color: #212529;"><?= htmlspecialchars($alerta['Nome_Praga']); ?></strong>
                          <?php if (!empty($alerta['Planta_Hospedeira'])): ?>
                            <br><small class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($alerta['Planta_Hospedeira']); ?></small>
                          <?php endif; ?>
                          <br>
                          <small class="text-muted">
                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($alerta['Localidade']); ?>
                            <br>
                            <i class="bi bi-person"></i> <?= htmlspecialchars($alerta['Usuario_Origem'] ?? 'Usuário'); ?>
                            <br>
                            <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($alerta['Data_Criacao'])); ?>
                          </small>
                        </a>
                        <a href="dashboard.php?marcar_lido=<?= $alerta['ID']; ?>" class="btn btn-sm btn-link text-muted ms-2" onclick="event.stopPropagation();" title="Marcar como lido">
                          <i class="bi bi-check-circle"></i>
                        </a>
                      </div>
                    </div>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                <?php endforeach; ?>
                <li><a class="dropdown-item text-center" href="#" onclick="marcarTodosLidos(); return false;"><small>Marcar todos como lidos</small></a></li>
              </ul>
            </div>
          <?php else: ?>
            <button class="btn btn-outline-light" type="button" disabled>
              <i class="bi bi-bell"></i> Alertas
            </button>
          <?php endif; ?>
          
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
        

        <div class="dashboard-grid">
          <!-- Bloco Pragas -->
          <div class="dashboard-item card-pragas blue-item" id="vendas-hoje" style="overflow-y: auto;">
            <h5 class="mb-3"><i class="bi bi-bug-fill text-primary"></i> Todas as Pragas Registradas</h5>
            <?php if (!empty($todasPragas)): ?>
              <div class="list-group" style="max-height: 250px; overflow-y: auto;">
                <?php foreach ($todasPragas as $praga): ?>
                  <div class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                      <h6 class="mb-1"><?= htmlspecialchars($praga['Nome']); ?></h6>
                      <small class="text-muted"><?= date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?></small>
                    </div>
                    <p class="mb-1"><small class="text-muted"><i class="bi bi-flower1"></i> <?= htmlspecialchars($praga['Planta_Hospedeira']); ?></small></p>
                    <p class="mb-1"><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($praga['Localidade']); ?></small></p>
                    <div class="mt-2">
                      <a href="../cadastro/atualizar_praga.php?id=<?= $praga['ID']; ?>" 
                         class="btn btn-sm btn-primary" 
                         title="Atualizar praga com nova foto">
                        <i class="bi bi-pencil-square"></i> Atualizar
                      </a>
                      <a href="gerar_relatorio.php?id=<?= $praga['ID']; ?>" 
                         class="btn btn-sm btn-info" 
                         title="Ver relatório">
                        <i class="bi bi-file-text"></i> Relatório
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="mt-3 text-muted">Nenhuma praga cadastrada ainda.</p>
            <?php endif; ?>
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
              <?php if (!empty($todasPragas)): ?>
                <form action="gerar_relatorio.php" method="GET" target="_blank">
                  <div class="mb-3">
                    <label for="praga_relatorio" class="form-label">Selecione a Praga para Gerar Relatório:</label>
                    <select class="form-select" id="praga_relatorio" name="id" required>
                      <option value="">-- Selecione uma praga --</option>
                      <?php foreach ($todasPragas as $praga): ?>
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
                    <i class="bi bi-info-circle"></i> Você pode gerar relatórios de qualquer praga cadastrada, mesmo as mais antigas.
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

          <!-- Bloco Surtos na Região (Prevenção) -->
          <div class="dashboard-item card-surtos orange-item" id="todos-surtos-regiao" style="overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0"><i class="bi bi-shield-exclamation text-warning"></i> Surtos na Região</h5>
              <a href="../cadastro/cadsurto.php" class="btn btn-sm btn-light" title="Cadastrar novo surto">
                <i class="bi bi-plus-circle"></i> Novo Surto
              </a>
            </div>
            <div style="max-height: 280px; overflow-y: auto;">
              <?php if (!empty($todosSurtosRegiao)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert" style="font-size: 0.85rem; padding: 8px 12px; margin-bottom: 10px;">
                  <i class="bi bi-info-circle"></i> <strong>Atenção:</strong> Surtos registrados na sua região e regiões próximas.
                  <button type="button" class="btn-close" data-bs-dismiss="alert" style="font-size: 0.7rem;"></button>
                </div>
                <?php if ($localidadeUsuario != 'Região não especificada'): ?>
                  <div class="alert alert-info" style="font-size: 0.75rem; padding: 6px 10px; margin-bottom: 10px;">
                    <small><i class="bi bi-geo-alt"></i> Buscando surtos para: <strong><?= htmlspecialchars($localidadeUsuario); ?></strong></small>
                  </div>
                <?php endif; ?>
                <div class="list-group" style="max-height: 220px; overflow-y: auto;">
                  <?php foreach ($todosSurtosRegiao as $surto): ?>
                    <a href="gerar_relatorio.php?id=<?= $surto['ID']; ?>" target="_blank" class="list-group-item list-group-item-action" style="padding: 8px 12px; text-decoration: none; cursor: pointer;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
                      <div class="d-flex justify-content-between align-items-start">
                        <div style="flex: 1;">
                          <strong style="font-size: 0.9rem; color: #212529;"><?= htmlspecialchars($surto['Nome']); ?></strong>
                          <?php if (!empty($surto['Planta_Hospedeira'])): ?>
                            <br><small class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($surto['Planta_Hospedeira']); ?></small>
                          <?php endif; ?>
                          <br>
                          <small class="text-muted">
                            <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($surto['Data_Aparicao'])); ?>
                            <i class="bi bi-geo-alt ms-2"></i> <?= htmlspecialchars($surto['Localidade']); ?>
                          </small>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                          <span class="badge bg-warning text-dark">Surto</span>
                          <small class="text-primary" style="font-size: 0.75rem;">
                            <i class="bi bi-file-earmark-pdf"></i> Ver Relatório
                          </small>
                        </div>
                      </div>
                    </a>
                  <?php endforeach; ?>
                </div>
                <p class="mt-2 mb-0"><small class="text-muted">Total: <?= count($todosSurtosRegiao); ?> surtos registrados</small></p>
              <?php else: ?>
                <div class="alert alert-info" style="font-size: 0.9rem;">
                  <i class="bi bi-info-circle"></i> 
                  <?php if ($localidadeUsuario != 'Região não especificada'): ?>
                    <strong>Nenhum surto encontrado</strong> para a região: <strong><?= htmlspecialchars($localidadeUsuario); ?></strong> nos últimos 30 dias.
                    <br><small class="text-muted">Certifique-se de que a localização no seu perfil está correta e que os surtos foram cadastrados com a mesma localização.</small>
                  <?php else: ?>
                    <strong>Localização não configurada.</strong> Configure sua localização no seu <a href="perfil.php" class="alert-link">perfil</a> para ver surtos da sua região.
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Bloco Gráfico de Infestação -->
          <div class="dashboard-item card-grafico" id="grafico-vendas" style="grid-column: span 2; max-height: 450px; overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0"><i class="bi bi-graph-up text-danger"></i> Evolução da Infestação - Média de Pragas por Planta</h5>
              <?php if (!empty($pragasComImagens)): ?>
                <select class="form-select" id="select-praga-grafico" style="width: auto; min-width: 200px; font-size: 0.9rem;" onchange="atualizarGrafico(this.value)">
                  <option value="">Todas as pragas</option>
                  <?php foreach ($pragasComImagens as $pragaImg): ?>
                    <option value="<?= $pragaImg['ID']; ?>" <?= ($pragaGraficoID == $pragaImg['ID']) ? 'selected' : ''; ?>>
                      <?= htmlspecialchars($pragaImg['Nome']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
            <div id="conteudo-grafico" style="overflow-x: auto;">
              <?php if (!empty($dadosGrafico)): ?>
                <?php 
                  $pragaNomeParaGrafico = $pragaSelecionadaGrafico ? $pragaSelecionadaGrafico['Nome'] : 'Evolução de Todas as Pragas';
                  echo gerarGraficoSVG($dadosGrafico, $pragaNomeParaGrafico);
                ?>
              <?php elseif (!empty($pragasComImagens)): ?>
                <div class="alert alert-warning" style="font-size: 0.9rem;">
                  <i class="bi bi-exclamation-triangle"></i> 
                  <strong>Nenhum dado de infestação registrado</strong> para a praga selecionada.
                  <br><small>Para gerar o gráfico, cadastre ou atualize suas pragas informando a média de pragas por planta.</small>
                </div>
              <?php else: ?>
                <div class="alert alert-info" style="font-size: 0.9rem;">
                  <i class="bi bi-info-circle"></i> 
                  <strong>Nenhuma praga cadastrada.</strong>
                  <br><small>Para gerar o gráfico, você precisa cadastrar pragas com informações de infestação. <a href="../cadastro/cadpraga.php" class="alert-link">Cadastrar praga</a></small>
                </div>
              <?php endif; ?>
            </div>
            <?php if (!empty($pragasComImagens)): ?>
              <div class="mt-2">
                <small class="text-muted">
                  <i class="bi bi-info-circle"></i> O gráfico mostra todas as atualizações das pragas com a média de pragas por planta registrada. 
                  Cores: <span style="color: #28a745;">●</span> Verde = Queda, <span style="color: #dc3545;">●</span> Vermelho = Aumento, <span style="color: #ffc107;">●</span> Amarelo = Sem mudança.
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/SMCPA/js/menu.js"></script>
  <script>
    // Função para marcar todos os alertas como lidos
    function marcarTodosLidos() {
      if (confirm('Deseja marcar todos os alertas como lidos?')) {
        // Fazer requisição para marcar todos como lidos
        fetch('dashboard.php?acao=marcar_todos_lidos', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert('Erro ao marcar alertas como lidos.');
          }
        })
        .catch(error => {
          console.error('Erro:', error);
          alert('Erro ao marcar alertas como lidos.');
        });
      }
    }
  </script>
  <script>
    // Dados das pragas para JavaScript
    const todasPragas = <?= json_encode($todasPragas); ?>;
    
    // Variável global para controle do gráfico
    
    // Função para atualizar recomendações via AJAX
    function atualizarRecomendacoes(pragaID) {
      if (!pragaID) {
        document.getElementById('conteudo-recomendacoes').innerHTML = '<p class="mt-3">Selecione uma praga para ver recomendações.</p>';
        return;
      }
      
      const praga = todasPragas.find(p => p.ID == pragaID);
      if (!praga) return;
      
      // Fazer requisição AJAX
      fetch(`dashboard.php?praga_id=${pragaID}&acao=recomendacoes`)
        .then(response => response.text())
        .then(html => {
          document.getElementById('conteudo-recomendacoes').innerHTML = html;
        })
        .catch(error => console.error('Erro:', error));
    }
    
    // Função para atualizar o gráfico ao selecionar uma praga
    function atualizarGrafico(pragaID) {
        // Recarregar a página com a praga selecionada
        window.location.href = pragaID ? `dashboard.php?praga_id=${pragaID}` : 'dashboard.php';
    }
  </script>
</body>
</html>
