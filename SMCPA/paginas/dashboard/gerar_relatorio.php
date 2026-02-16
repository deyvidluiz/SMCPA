<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Iniciar sessão PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Evitar cache do navegador para garantir que o relatório sempre mostre dados atualizados
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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

// Verificar se foi passado um ID de praga
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $dashboardUrl = $isAdmin ? "dashboardadm.php" : "dashboard.php";
    header("Location: " . $dashboardUrl . "?erro=id_nao_informado");
    exit;
}

$pragaID = intval($_GET['id']);
$geradoAutomaticamente = isset($_GET['auto']) && $_GET['auto'] == 1;

// Buscar dados da praga (admins podem ver qualquer praga, usuários podem ver pragas da mesma região)
try {
    if ($isAdmin) {
        // Admin pode ver qualquer praga
        $stmt = $pdo->prepare("SELECT 
                                ID, Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, 
                                ID_Praga, Localidade, Data_Aparicao, Observacoes, ID_Usuario,
                                media_pragas_planta, severidade
                              FROM Pragas_Surtos 
                              WHERE ID = :id");
        $stmt->bindParam(':id', $pragaID, PDO::PARAM_INT);
    } else {
        // Usuário comum pode ver suas pragas E pragas de outros usuários da mesma região
        // Primeiro buscar a localização do usuário
        $localidadeUsuario = '';
        try {
            $stmtLocalizacao = $pdo->prepare("SELECT localizacao FROM Usuarios WHERE id = :usuarioID");
            $stmtLocalizacao->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
            $stmtLocalizacao->execute();
            $resultadoLocalizacao = $stmtLocalizacao->fetch(PDO::FETCH_ASSOC);
            if ($resultadoLocalizacao && !empty($resultadoLocalizacao['localizacao'])) {
                $localidadeUsuario = trim($resultadoLocalizacao['localizacao']);
            }
        } catch (PDOException $e) {
            // Ignorar erro
        }
        
        // Buscar a praga: pode ser do próprio usuário OU de outro usuário da mesma região
        if (!empty($localidadeUsuario)) {
            $stmt = $pdo->prepare("SELECT 
                                    ID, Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, 
                                    ID_Praga, Localidade, Data_Aparicao, Observacoes, ID_Usuario,
                                    media_pragas_planta, severidade
                                  FROM Pragas_Surtos 
                                  WHERE ID = :id 
                                  AND (ID_Usuario = :usuarioID 
                                       OR (LOWER(Localidade) = LOWER(:localidadeUsuario) 
                                           OR LOWER(Localidade) LIKE LOWER(:localidadeUsuarioLike)))");
            $stmt->bindParam(':id', $pragaID, PDO::PARAM_INT);
            $stmt->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
            $stmt->bindParam(':localidadeUsuario', $localidadeUsuario, PDO::PARAM_STR);
            $localidadeUsuarioLike = '%' . $localidadeUsuario . '%';
            $stmt->bindParam(':localidadeUsuarioLike', $localidadeUsuarioLike, PDO::PARAM_STR);
        } else {
            // Se não tiver localização, apenas suas pragas
            $stmt = $pdo->prepare("SELECT 
                                    ID, Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, 
                                    ID_Praga, Localidade, Data_Aparicao, Observacoes, ID_Usuario,
                                    media_pragas_planta, severidade
                                  FROM Pragas_Surtos 
                                  WHERE ID = :id AND ID_Usuario = :usuarioID");
            $stmt->bindParam(':id', $pragaID, PDO::PARAM_INT);
            $stmt->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
        }
    }
    $stmt->execute();
    $praga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$praga) {
        $dashboardUrl = $isAdmin ? "dashboardadm.php" : "dashboard.php";
        header("Location: " . $dashboardUrl . "?erro=praga_nao_encontrada");
        exit;
    }
} catch (PDOException $e) {
    die("Erro ao buscar praga: " . $e->getMessage());
}

// Buscar dados do usuário que cadastrou a praga (não necessariamente o usuário logado)
$usuarioPragaID = $praga['ID_Usuario'] ?? $usuarioID;
try {
    $stmtUsuario = $pdo->prepare("SELECT id, usuario, email FROM Usuarios WHERE id = :id");
    $stmtUsuario->bindParam(':id', $usuarioPragaID, PDO::PARAM_INT);
    $stmtUsuario->execute();
    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuario = ['usuario' => 'Usuário', 'email' => ''];
}

// Data atual para o relatório
date_default_timezone_set('America/Sao_Paulo');  
$dataRelatorio = date('d/m/Y H:i:s');

// Função para identificar a praga e gerar soluções específicas
function identificarPragaEGerarSolucoes($nomePraga, $plantaHospedeira = '') {
    $nomeLower = strtolower(trim($nomePraga));
    $plantaLower = strtolower(trim($plantaHospedeira));
    $solucoes = [
        'tipo' => 'Geral',
        'descricao' => '',
        'metodos_controle' => [],
        'produtos_recomendados' => [],
        'praticas_preventivas' => [],
        'tratamentos_especificos' => [],
        'informacoes_tecnicas' => []
    ];
    
    // Identificação de Lagartas
    if (strpos($nomeLower, 'lagarta') !== false || strpos($nomeLower, 'caterpillar') !== false || 
        strpos($nomeLower, 'spodoptera') !== false || strpos($nomeLower, 'helicoverpa') !== false ||
        strpos($nomeLower, 'anticarsia') !== false || strpos($nomeLower, 'elasmo') !== false) {
        $solucoes['tipo'] = 'Lagarta';
        $solucoes['descricao'] = 'Lagartas são larvas de lepidópteros que se alimentam de folhas, causando desfolhamento e redução da produtividade.';
        $solucoes['metodos_controle'] = [
            'Controle biológico com Bacillus thuringiensis (Bt)',
            'Uso de inseticidas reguladores de crescimento (IGR)',
            'Aplicação de inseticidas químicos em caso de alta infestação',
            'Controle mecânico através de catação manual'
        ];
        $solucoes['produtos_recomendados'] = [
            'Bacillus thuringiensis var. kurstaki (Dipel, Xentari)',
            'Metarhizium anisopliae',
            'Inseticidas à base de espinosade (Tracer, Success)',
            'Inseticidas à base de clorantraniliprole (Coragen, Premio)',
            'Inseticidas à base de indoxacarbe (Avaunt)'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento semanal com armadilhas de feromônios',
            'Eliminação de plantas hospedeiras alternativas',
            'Rotação de culturas',
            'Manutenção de áreas de refúgio para inimigos naturais',
            'Uso de variedades resistentes quando disponíveis'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar inseticidas no início da manhã ou fim da tarde',
            'Fazer aplicações quando detectar 5-10 lagartas pequenas por metro linear',
            'Alternar princípios ativos para evitar resistência',
            'Respeitar o período de carência antes da colheita'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 30-45 dias dependendo da temperatura',
            'Danos: Desfolhamento de até 80% em altas infestações',
            'Período crítico: Fase vegetativa e início do florescimento',
            'Nível de ação: 5-10 lagartas pequenas por metro linear'
        ];
    }
    // Identificação de Pulgões
    elseif (strpos($nomeLower, 'pulgão') !== false || strpos($nomeLower, 'aphid') !== false ||
            strpos($nomeLower, 'afídeo') !== false || strpos($nomeLower, 'afideo') !== false) {
        $solucoes['tipo'] = 'Pulgão';
        $solucoes['descricao'] = 'Pulgões são insetos sugadores que se alimentam da seiva das plantas, causando deformações, murcha e transmissão de vírus.';
        $solucoes['metodos_controle'] = [
            'Controle biológico com joaninhas, crisopídeos e parasitoides',
            'Aplicação de sabão inseticida ou óleo de neem',
            'Uso de inseticidas sistêmicos',
            'Eliminação de formigas que protegem os pulgões'
        ];
        $solucoes['produtos_recomendados'] = [
            'Óleo de neem (Azamax, Neemazal)',
            'Sabão inseticida',
            'Inseticidas à base de imidacloprido (Confidor, Gaucho)',
            'Inseticidas à base de tiametoxam (Actara)',
            'Inseticidas à base de acetamiprido (Mospilan)'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento visual semanal nas folhas novas',
            'Eliminação de plantas daninhas hospedeiras',
            'Uso de barreiras físicas (telas)',
            'Manutenção de plantas atrativas para predadores',
            'Controle de formigas cortadeiras'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar inseticidas quando detectar colônias nas folhas',
            'Fazer pulverizações direcionadas nas partes afetadas',
            'Tratar formigueiros próximos às culturas',
            'Usar produtos sistêmicos em caso de alta infestação'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 7-14 dias em condições favoráveis',
            'Danos: Transmissão de vírus, redução de crescimento',
            'Período crítico: Todo o ciclo da cultura',
            'Nível de ação: 10-20 pulgões por folha ou presença de mela'
        ];
    }
    // Identificação de Ácaros
    elseif (strpos($nomeLower, 'ácaro') !== false || strpos($nomeLower, 'acaro') !== false ||
            strpos($nomeLower, 'mite') !== false || strpos($nomeLower, 'tetranychus') !== false ||
            strpos($nomeLower, 'rajado') !== false) {
        $solucoes['tipo'] = 'Ácaro';
        $solucoes['descricao'] = 'Ácaros são aracnídeos que se alimentam das folhas, causando manchas, bronzeamento e redução da fotossíntese.';
        $solucoes['metodos_controle'] = [
            'Controle biológico com ácaros predadores (Phytoseiulus)',
            'Aplicação de acaricidas específicos',
            'Uso de enxofre ou óleos minerais',
            'Aumento da umidade relativa do ar'
        ];
        $solucoes['produtos_recomendados'] = [
            'Abamectina (Vertimec, Agrimec)',
            'Etoxazol (Zeal)',
            'Spirodiclofeno (Envidor)',
            'Hexitiazox (Savey)',
            'Óleo mineral ou enxofre'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento com lupa (10-20x) nas folhas',
            'Irrigação por aspersão para aumentar umidade',
            'Eliminação de plantas daninhas',
            'Evitar estresse hídrico nas plantas',
            'Uso de variedades tolerantes'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar acaricidas quando detectar 2-3 ácaros por folha',
            'Fazer pulverizações com boa cobertura das folhas',
            'Alternar princípios ativos (máximo 2 aplicações por produto)',
            'Aplicar no início da manhã ou fim da tarde'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 5-10 dias em condições quentes e secas',
            'Danos: Bronzeamento foliar, redução da fotossíntese',
            'Período crítico: Condições de baixa umidade e alta temperatura',
            'Nível de ação: 2-3 ácaros por folha ou início de bronzeamento'
        ];
    }
    // Identificação de Percevejos
    elseif (strpos($nomeLower, 'percevejo') !== false || strpos($nomeLower, 'stink bug') !== false ||
            strpos($nomeLower, 'euschistus') !== false || strpos($nomeLower, 'piezodorus') !== false ||
            strpos($nomeLower, 'neotibraca') !== false) {
        $solucoes['tipo'] = 'Percevejo';
        $solucoes['descricao'] = 'Percevejos são insetos sugadores que se alimentam de grãos e frutos, causando chochamento, deformações e redução da qualidade.';
        $solucoes['metodos_controle'] = [
            'Controle químico com inseticidas de contato',
            'Uso de armadilhas com feromônios',
            'Catação manual em pequenas áreas',
            'Eliminação de plantas hospedeiras'
        ];
        $solucoes['produtos_recomendados'] = [
            'Inseticidas à base de neonicotinoides (Confidor, Actara)',
            'Inseticidas à base de piretroides (Decis, Karate)',
            'Inseticidas à base de organofosforados (Lorsban)',
            'Inseticidas à base de espinosade (Tracer)'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento com pano de batida',
            'Eliminação de plantas daninhas hospedeiras',
            'Colheita antecipada quando possível',
            'Uso de variedades de ciclo curto',
            'Manutenção de áreas limpas ao redor da cultura'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar inseticidas quando detectar 2 percevejos por metro linear',
            'Fazer aplicações no início da manhã quando os insetos estão mais ativos',
            'Focar aplicações nas bordas da lavoura',
            'Alternar princípios ativos para evitar resistência'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 30-50 dias',
            'Danos: Chochamento de grãos, deformação de frutos',
            'Período crítico: Fase reprodutiva (florescimento e enchimento de grãos)',
            'Nível de ação: 2 percevejos por metro linear ou 1 percevejo por 2 plantas'
        ];
    }
    // Identificação de Mosca Branca
    elseif (strpos($nomeLower, 'mosca branca') !== false || strpos($nomeLower, 'mosca-branca') !== false ||
            strpos($nomeLower, 'whitefly') !== false || strpos($nomeLower, 'bemisia') !== false ||
            strpos($nomeLower, 'aleurodídeo') !== false) {
        $solucoes['tipo'] = 'Mosca Branca';
        $solucoes['descricao'] = 'Moscas brancas são insetos sugadores que se alimentam da seiva, causando enfraquecimento das plantas e transmissão de vírus.';
        $solucoes['metodos_controle'] = [
            'Controle biológico com parasitoides (Encarsia, Eretmocerus)',
            'Uso de armadilhas adesivas amarelas',
            'Aplicação de inseticidas sistêmicos',
            'Eliminação de plantas hospedeiras'
        ];
        $solucoes['produtos_recomendados'] = [
            'Inseticidas à base de imidacloprido (Confidor)',
            'Inseticidas à base de tiametoxam (Actara)',
            'Inseticidas à base de buprofezina (Applaud)',
            'Inseticidas à base de pimetrozina (Plenum)',
            'Óleo de neem'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento com armadilhas adesivas amarelas',
            'Eliminação de plantas daninhas hospedeiras',
            'Uso de mudas sadias',
            'Eliminação de restos culturais',
            'Rotação de culturas'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar inseticidas quando detectar 5-10 adultos por armadilha',
            'Fazer pulverizações na parte inferior das folhas',
            'Usar produtos sistêmicos para controle de ninfas',
            'Alternar princípios ativos regularmente'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 20-30 dias em condições favoráveis',
            'Danos: Transmissão de vírus, mela, enfraquecimento',
            'Período crítico: Todo o ciclo, especialmente fase vegetativa',
            'Nível de ação: 5-10 adultos por armadilha adesiva amarela'
        ];
    }
    // Identificação de Tripes
    elseif (strpos($nomeLower, 'tripe') !== false || strpos($nomeLower, 'thrips') !== false ||
            strpos($nomeLower, 'frankliniella') !== false || strpos($nomeLower, 'scirtothrips') !== false) {
        $solucoes['tipo'] = 'Tripe';
        $solucoes['descricao'] = 'Tripes são insetos pequenos que raspam e sugam o conteúdo celular, causando manchas prateadas, deformações e transmissão de vírus.';
        $solucoes['metodos_controle'] = [
            'Controle biológico com ácaros predadores',
            'Uso de armadilhas adesivas azuis',
            'Aplicação de inseticidas sistêmicos',
            'Aumento da umidade relativa'
        ];
        $solucoes['produtos_recomendados'] = [
            'Inseticidas à base de espinosade (Tracer, Success)',
            'Inseticidas à base de imidacloprido (Confidor)',
            'Inseticidas à base de fipronil (Regent)',
            'Inseticidas à base de abamectina (Vertimec)'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento com armadilhas adesivas azuis',
            'Eliminação de plantas daninhas',
            'Irrigação por aspersão para aumentar umidade',
            'Uso de variedades resistentes',
            'Eliminação de restos culturais'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar inseticidas quando detectar 5-10 tripes por flor ou folha',
            'Fazer pulverizações com boa cobertura',
            'Focar aplicações nas flores e brotos',
            'Alternar princípios ativos'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 15-20 dias',
            'Danos: Manchas prateadas, deformações, transmissão de vírus',
            'Período crítico: Fase de florescimento',
            'Nível de ação: 5-10 tripes por flor ou folha'
        ];
    }
    // Identificação de Besouros
    elseif (strpos($nomeLower, 'besouro') !== false || strpos($nomeLower, 'beetle') !== false ||
            strpos($nomeLower, 'coleóptero') !== false || strpos($nomeLower, 'coleoptero') !== false ||
            strpos($nomeLower, 'diabrotica') !== false || strpos($nomeLower, 'cerotoma') !== false) {
        $solucoes['tipo'] = 'Besouro';
        $solucoes['descricao'] = 'Besouros são insetos mastigadores que se alimentam de folhas, raízes e grãos, causando desfolhamento e redução da produtividade.';
        $solucoes['metodos_controle'] = [
            'Controle químico com inseticidas de contato e sistêmicos',
            'Uso de armadilhas luminosas',
            'Catação manual',
            'Tratamento de sementes'
        ];
        $solucoes['produtos_recomendados'] = [
            'Inseticidas à base de piretroides (Decis, Karate)',
            'Inseticidas à base de neonicotinoides (Gaucho, Cruiser)',
            'Inseticidas à base de fipronil (Regent)',
            'Tratamento de sementes com tiametoxam ou imidacloprido'
        ];
        $solucoes['praticas_preventivas'] = [
            'Tratamento de sementes',
            'Eliminação de plantas daninhas',
            'Rotação de culturas',
            'Uso de armadilhas luminosas para monitoramento',
            'Preparo adequado do solo'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Aplicar inseticidas quando detectar danos visíveis',
            'Fazer tratamento de sementes para controle de larvas',
            'Aplicar inseticidas no início da manhã ou fim da tarde',
            'Focar aplicações nas bordas da lavoura'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Ciclo de vida: 30-60 dias dependendo da espécie',
            'Danos: Desfolhamento, danos em raízes e grãos',
            'Período crítico: Fase vegetativa e reprodutiva',
            'Nível de ação: Danos visíveis ou 5-10 besouros por metro quadrado'
        ];
    }
    // Recomendações genéricas caso não identifique a praga
    else {
        $solucoes['tipo'] = 'Praga Agrícola';
        $solucoes['descricao'] = 'Praga agrícola que requer monitoramento e controle adequado para evitar danos à produção.';
        $solucoes['metodos_controle'] = [
            'Monitoramento regular da área afetada',
            'Uso de controle integrado (biológico + químico)',
            'Aplicação de produtos registrados para a cultura',
            'Consultar agrônomo para identificação precisa'
        ];
        $solucoes['produtos_recomendados'] = [
            'Produtos registrados no Ministério da Agricultura para a cultura',
            'Consultar bula do produto para verificar eficácia contra a praga',
            'Seguir recomendações técnicas de empresas especializadas'
        ];
        $solucoes['praticas_preventivas'] = [
            'Monitoramento semanal da lavoura',
            'Eliminação de plantas daninhas',
            'Rotação de culturas',
            'Uso de sementes certificadas',
            'Manutenção de áreas limpas ao redor da cultura'
        ];
        $solucoes['tratamentos_especificos'] = [
            'Identificar corretamente a praga antes de aplicar produtos',
            'Seguir as recomendações do fabricante do produto',
            'Respeitar o período de carência',
            'Alternar princípios ativos para evitar resistência'
        ];
        $solucoes['informacoes_tecnicas'] = [
            'Recomenda-se consultar um agrônomo para identificação precisa',
            'Monitorar regularmente para detectar infestações no início',
            'Manter registros de ocorrências e tratamentos realizados'
        ];
    }
    
    return $solucoes;
}

// Identificar a praga e gerar soluções
$solucoesPraga = identificarPragaEGerarSolucoes($praga['Nome'], $praga['Planta_Hospedeira']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório - <?php echo htmlspecialchars($praga['Nome']); ?> - SMCPA</title>
    <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 20px;
            }
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .relatorio-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header-relatorio {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo-relatorio {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .info-box {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-left: 3px solid #6c757d;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .section-title {
            color: #495057;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        .solution-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .solution-item:last-child {
            border-bottom: none;
        }
        .imagem-praga {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .footer-relatorio {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="relatorio-container">
        <!-- Botões de ação (não aparecem na impressão) -->
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Imprimir / Salvar PDF
            </button>
            <a href="<?= $isAdmin ? 'dashboardadm.php' : 'dashboard.php'; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
            </a>
        </div>

        <!-- Cabeçalho do Relatório -->
        <div class="header-relatorio">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 style="color: #495057; font-weight: 600; margin-bottom: 5px; font-size: 1.8rem;">SMCPA</h1>
                    <p style="color: #6c757d; margin-bottom: 0; font-size: 0.95rem;">Sistema de Monitoramento e Controle de Pragas Agrícolas</p>
                </div>
                <div class="col-md-4 text-end">
                    <p style="margin-bottom: 5px; color: #6c757d;"><strong>Data:</strong> <?= $dataRelatorio; ?></p>
                </div>
            </div>
        </div>

        <!-- Título do Relatório -->
        <div class="text-center mb-4" style="padding-bottom: 20px; border-bottom: 1px solid #dee2e6;">
            <h2 style="color: #495057; font-weight: 600; margin-bottom: 10px;">Relatório de Praga Agrícola</h2>
            <h3 style="color: #212529; font-weight: 500; font-size: 1.3rem;"><?= htmlspecialchars($praga['Nome']); ?></h3>
        </div>

        <!-- Informações da Praga -->
        <div class="row">
            <div class="col-md-6">
                <div class="info-box">
                    <div class="section-title">Informações Básicas</div>
                    <p class="mb-2"><strong>Nome da Praga:</strong> <?= htmlspecialchars($praga['Nome']); ?></p>
                    <p class="mb-2"><strong>ID da Praga:</strong> <?= htmlspecialchars($praga['ID_Praga'] ?? 'N/A'); ?></p>
                    <p class="mb-0"><strong>Planta Hospedeira:</strong> <?= htmlspecialchars($praga['Planta_Hospedeira']); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box">
                    <div class="section-title">Localização</div>
                    <p class="mb-2"><strong>Localidade:</strong> <?= htmlspecialchars($praga['Localidade']); ?></p>
                    <p class="mb-0"><strong>Data de Aparição:</strong> <?= date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Descrição -->
        <?php if (!empty($praga['Descricao'])): ?>
        <div class="info-box mt-3">
            <div class="section-title">Descrição</div>
            <p style="margin-bottom: 0; line-height: 1.6;"><?= nl2br(htmlspecialchars($praga['Descricao'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Imagem da Praga -->
        <?php if (!empty($praga['Imagem_Not_Null'])): ?>
        <div class="text-center mt-4">
            <div class="section-title text-center" style="border-bottom: none; margin-bottom: 15px;">Imagem da Praga</div>
            <img src="/uploads/pragas/<?= htmlspecialchars($praga['Imagem_Not_Null']); ?>" 
                 alt="Imagem da praga <?= htmlspecialchars($praga['Nome']); ?>" 
                 class="imagem-praga">
        </div>
        <?php endif; ?>

        <!-- Informações Quantitativas (se disponíveis) -->
        <?php if (!empty($praga['media_pragas_planta']) || !empty($praga['severidade'])): ?>
        <div class="info-box mt-4">
            <div class="section-title">Análise Quantitativa do Ataque</div>
            <div class="row">
                <?php if (!empty($praga['media_pragas_planta'])): ?>
                <div class="col-md-6 mb-3">
                    <p class="mb-1"><strong>Média de Pragas por Planta:</strong></p>
                    <p class="mb-0" style="font-size: 1.1rem; color: #495057;"><?= number_format($praga['media_pragas_planta'], 2, ',', '.'); ?> pragas/planta</p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($praga['severidade'])): ?>
                <div class="col-md-6 mb-3">
                    <p class="mb-1"><strong>Severidade:</strong></p>
                    <p class="mb-0">
                        <span class="badge <?php 
                            $severidade = $praga['severidade'];
                            if ($severidade == 'Baixa') echo 'bg-success';
                            elseif ($severidade == 'Média') echo 'bg-warning';
                            elseif ($severidade == 'Alta') echo 'bg-danger';
                            else echo 'bg-dark';
                        ?>" style="font-size: 0.9rem; padding: 6px 12px;">
                            <?= htmlspecialchars($praga['severidade']); ?>
                        </span>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Observações -->
        <?php if (!empty($praga['Observacoes'])): ?>
        <div class="info-box mt-4">
            <div class="section-title">Observações</div>
            <p style="margin-bottom: 0; line-height: 1.6;"><?= nl2br(htmlspecialchars($praga['Observacoes'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Identificação e Soluções da Praga -->
        <div class="mt-5" style="border-top: 2px solid #dee2e6; padding-top: 30px;">
            <h3 style="color: #495057; font-weight: 600; margin-bottom: 25px;">Recomendações Técnicas</h3>

            <!-- Tipo de Praga Identificada -->
            <div class="info-box">
                <div class="section-title">Tipo de Praga</div>
                <p style="font-size: 1.05rem; font-weight: 500; color: #212529; margin-bottom: 10px;"><?= htmlspecialchars($solucoesPraga['tipo']); ?></p>
                <p style="color: #6c757d; margin-bottom: 0;"><?= htmlspecialchars($solucoesPraga['descricao']); ?></p>
            </div>

            <!-- Métodos de Controle -->
            <div class="info-box">
                <div class="section-title">Métodos de Controle</div>
                <div>
                    <?php foreach ($solucoesPraga['metodos_controle'] as $metodo): ?>
                        <div class="solution-item">
                            <?= htmlspecialchars($metodo); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Produtos Recomendados -->
            <div class="info-box">
                <div class="section-title">Produtos Recomendados</div>
                <div style="background-color: #fff3cd; padding: 12px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem; color: #856404;">
                    <strong>Nota:</strong> Consulte um agrônomo e verifique o registro do produto para sua cultura e região. Siga as instruções da bula.
                </div>
                <div>
                    <?php foreach ($solucoesPraga['produtos_recomendados'] as $produto): ?>
                        <div class="solution-item">
                            <?= htmlspecialchars($produto); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Práticas Preventivas -->
            <div class="info-box">
                <div class="section-title">Práticas Preventivas</div>
                <div>
                    <?php foreach ($solucoesPraga['praticas_preventivas'] as $pratica): ?>
                        <div class="solution-item">
                            <?= htmlspecialchars($pratica); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tratamentos Específicos -->
            <div class="info-box">
                <div class="section-title">Tratamentos Específicos</div>
                <div>
                    <?php foreach ($solucoesPraga['tratamentos_especificos'] as $tratamento): ?>
                        <div class="solution-item">
                            <?= htmlspecialchars($tratamento); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Informações Técnicas -->
            <div class="info-box">
                <div class="section-title">Informações Técnicas</div>
                <div>
                    <?php foreach ($solucoesPraga['informacoes_tecnicas'] as $info): ?>
                        <div class="solution-item">
                            <?= htmlspecialchars($info); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Aviso Final -->
            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; border-left: 3px solid #dc3545; padding: 20px; margin-top: 30px; border-radius: 4px;">
                <div style="font-weight: 600; color: #721c24; margin-bottom: 12px;">Avisos Importantes</div>
                <ul style="margin-bottom: 0; padding-left: 20px; color: #721c24;">
                    <li style="margin-bottom: 8px;">Consulte um agrônomo registrado antes de aplicar produtos químicos.</li>
                    <li style="margin-bottom: 8px;">Verifique o registro do produto no Ministério da Agricultura.</li>
                    <li style="margin-bottom: 8px;">Siga rigorosamente as instruções da bula (dosagem, intervalo, período de carência).</li>
                    <li style="margin-bottom: 8px;">Use equipamentos de proteção individual (EPI) durante aplicações.</li>
                    <li style="margin-bottom: 8px;">Respeite o período de carência antes da colheita.</li>
                    <li>Alternar princípios ativos para evitar resistência.</li>
                </ul>
            </div>
        </div>

        <!-- Informações do Usuário -->
        <div class="info-box mt-4">
            <div class="section-title">Responsável pelo Registro</div>
            <p class="mb-2"><strong>Nome:</strong> <?= htmlspecialchars($usuario['usuario'] ?? 'N/A'); ?></p>
            <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($usuario['email'] ?? 'N/A'); ?></p>
        </div>

        <!-- Rodapé -->
        <div class="footer-relatorio">
            <p class="mb-0">
                <?php if ($geradoAutomaticamente): ?>
                    <i class="bi bi-info-circle"></i> Este relatório foi gerado automaticamente após a atualização da praga.
                <?php else: ?>
                    Este relatório foi gerado pelo sistema SMCPA
                <?php endif; ?>
            </p>
            <p class="mb-0">Relatório ID: <?= $praga['ID']; ?> | Gerado em: <?= $dataRelatorio; ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

