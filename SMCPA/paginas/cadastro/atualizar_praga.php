<?php
session_start();
require_once('../../config.php');
include_once(BASE_URL.'/database/conexao.php');

// Verificar se o usuário está logado
if (!isset($_SESSION['id'])) {
    header("Location: ../login/login.php");
    exit;
}

$usuarioID = $_SESSION['id'];
$pragaID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pragaID <= 0) {
    header("Location: cadpraga.php?erro=id_invalido");
    exit;
}

$pdo = new Database();
$pdo = $pdo->conexao();

// Buscar dados da praga original
try {
    $stmt = $pdo->prepare("SELECT * FROM Pragas_Surtos WHERE ID = :id AND ID_Usuario = :usuarioID");
    $stmt->bindParam(':id', $pragaID, PDO::PARAM_INT);
    $stmt->bindParam(':usuarioID', $usuarioID, PDO::PARAM_INT);
    $stmt->execute();
    $pragaOriginal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pragaOriginal) {
        header("Location: cadpraga.php?erro=praga_nao_encontrada");
        exit;
    }
} catch (PDOException $e) {
    header("Location: cadpraga.php?erro=erro_busca");
    exit;
}

// Verificar se a praga já tem média de pragas por planta preenchida para mostrar mensagem adequada
$temMediaPreenchida = !empty($pragaOriginal['media_pragas_planta']) && $pragaOriginal['media_pragas_planta'] > 0;

// Verificar e criar colunas adicionais se não existirem
try {
    $pdo->exec("ALTER TABLE Pragas_Surtos ADD COLUMN media_pragas_planta DECIMAL(10,2) DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE Pragas_Surtos ADD COLUMN severidade VARCHAR(50) DEFAULT NULL");
} catch (PDOException $e) {}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar') {
    try {
        // Dados do formulário
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : $pragaOriginal['Nome'];
        $planta_hospedeira = isset($_POST['planta_hospedeira']) ? trim($_POST['planta_hospedeira']) : $pragaOriginal['Planta_Hospedeira'];
        $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : $pragaOriginal['Descricao'];
        $id_praga = isset($_POST['id_praga']) ? trim($_POST['id_praga']) : $pragaOriginal['ID_Praga'];
        $localidade = isset($_POST['localidade']) ? trim($_POST['localidade']) : $pragaOriginal['Localidade'];
        $data_aparicao = isset($_POST['data_aparicao']) ? trim($_POST['data_aparicao']) : date('Y-m-d');
        $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : $pragaOriginal['Observacoes'];
        
        // Novos campos para melhorar o relatório
        $media_pragas_planta = isset($_POST['media_pragas_planta']) && $_POST['media_pragas_planta'] !== '' ? floatval($_POST['media_pragas_planta']) : null;
        $severidade = isset($_POST['severidade']) ? trim($_POST['severidade']) : null;
        
        // Processar imagem
        $imagemNome = $pragaOriginal['Imagem_Not_Null'];
        $diretorio = $_SERVER['DOCUMENT_ROOT'].'/uploads/pragas/';
        
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $arquivo = $_FILES['imagem'];
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($extensao, $extensoesPermitidas)) {
                $nomeArquivo = uniqid('praga_') . '.' . $extensao;
                $caminhoCompleto = $diretorio . $nomeArquivo;
                
                if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                    $imagemNome = $nomeArquivo;
                }
            }
        }
        
        // Atualizar registro existente (sobrepor)
        $sqlUpdate = "UPDATE Pragas_Surtos SET 
                        Nome = :nome, 
                        Planta_Hospedeira = :planta_hospedeira, 
                        Descricao = :descricao, 
                        ID_Praga = :id_praga, 
                        Localidade = :localidade, 
                        Data_Aparicao = :data_aparicao, 
                        Observacoes = :observacoes,
                        media_pragas_planta = :media_pragas_planta, 
                        severidade = :severidade";
        
        // Se houver nova imagem, atualizar também
        if (!empty($imagemNome)) {
            $sqlUpdate .= ", Imagem_Not_Null = :imagem";
        }
        
        $sqlUpdate .= " WHERE ID = :pragaID AND ID_Usuario = :usuario_id";
        
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->bindParam(':nome', $nome, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':planta_hospedeira', $planta_hospedeira, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':descricao', $descricao, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':id_praga', $id_praga, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':localidade', $localidade, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':data_aparicao', $data_aparicao, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':observacoes', $observacoes, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':usuario_id', $usuarioID, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':pragaID', $pragaID, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':media_pragas_planta', $media_pragas_planta, $media_pragas_planta !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmtUpdate->bindParam(':severidade', $severidade, $severidade !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        if (!empty($imagemNome)) {
            $stmtUpdate->bindParam(':imagem', $imagemNome, PDO::PARAM_STR);
        }
        
        if ($stmtUpdate->execute()) {
            // Não gerar alertas novamente - apenas atualizar o registro existente
            // Os alertas já foram gerados no cadastro original
            
            // Verificar se deve gerar relatório apenas se a média de pragas por planta foi preenchida
            $deveGerarRelatorio = false;
            
            // Se a média de pragas por planta foi preenchida, gerar relatório
            if ($media_pragas_planta !== null && $media_pragas_planta > 0) {
                $deveGerarRelatorio = true;
            }
            
            // Gerar relatório se necessário
            if ($deveGerarRelatorio) {
                // Usar caminho absoluto para evitar problemas com caminhos relativos
                header("Location: /SMCPA/paginas/dashboard/gerar_relatorio.php?id=" . $pragaID . "&auto=1");
                exit;
            }
            
            // Se não gerou relatório, apenas redirecionar (primeira atualização)
            $dashboardUrl = "../dashboard/dashboard.php";
            try {
                $stmtCheckAdmin = $pdo->prepare("SELECT is_admin FROM usuarios WHERE id = :id");
                $stmtCheckAdmin->bindParam(':id', $usuarioID, PDO::PARAM_INT);
                $stmtCheckAdmin->execute();
                $adminResult = $stmtCheckAdmin->fetch(PDO::FETCH_ASSOC);
                if ($adminResult && isset($adminResult['is_admin']) && $adminResult['is_admin'] == 1) {
                    $dashboardUrl = "../dashboard/dashboardadm.php";
                }
            } catch (PDOException $e) {
                // Usa dashboard padrão
            }
            
            echo '<script type="text/javascript">
                alert("Praga atualizada com sucesso!");
                window.location.href = "' . $dashboardUrl . '";
            </script>';
            exit;
        } else {
            echo '<script type="text/javascript">alert("Erro ao atualizar a praga.");</script>';
        }
    } catch (PDOException $e) {
        echo '<script type="text/javascript">alert("Erro: ' . $e->getMessage() . '");</script>';
    }
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
  <link rel="stylesheet" href="../../css/cadpragas.css">
  <title>Atualizar Praga - SMCPA</title>
</head>
<body>
    <div class="h1">
            <strong>Atualização da Praga:</strong> 
    </div>
    
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="atualizar">

        <div class="nome">
            <label>Nome comum da praga:
                <input type="text" name="nome" value="<?= htmlspecialchars($pragaOriginal['Nome']); ?>" required>
            </label>
        </div>

        <div class="hosp">
            <label>Planta hospedeira:
                <input type="text" name="planta_hospedeira" value="<?= htmlspecialchars($pragaOriginal['Planta_Hospedeira']); ?>" required>
            </label>
        </div>

        <div class="desc">
            <label>Descrição:
                <textarea name="descricao" rows="4" required><?= htmlspecialchars($pragaOriginal['Descricao']); ?></textarea>
            </label>
        </div>

        <div class="idpraga">
            <label>ID da Praga (Científico):
                <input type="text" name="id_praga" value="<?= htmlspecialchars($pragaOriginal['ID_Praga'] ?? ''); ?>">
            </label>
        </div>

        <div class="local">
            <label>Localidade:
                <input type="text" name="localidade" value="<?= htmlspecialchars($pragaOriginal['Localidade']); ?>" required>
            </label>
        </div>

        <div class="data">
            <label>Data de Aparição:
                <input type="date" name="data_aparicao" value="<?= htmlspecialchars($pragaOriginal['Data_Aparicao']); ?>" required>
            </label>
        </div>

        <div class="obs">
            <label>Observações:
                <textarea name="observacoes" rows="3"><?= htmlspecialchars($pragaOriginal['Observacoes'] ?? ''); ?></textarea>
            </label>
        </div>

        <!-- Novos campos para melhorar o relatório -->
        <div class="alert alert-info mt-3 mb-3">
            <strong><i class="bi bi-info-circle"></i> Informações Adicionais para o Relatório:</strong>
            <small>Preencha estes campos para gerar um relatório mais completo e preciso.</small>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="media_pragas_planta" class="form-label">
                        Média de Pragas por Planta:
                        <small class="text-muted">(ex: 5.5)</small>
                    </label>
                    <input type="number" 
                           class="form-control" 
                           id="media_pragas_planta" 
                           name="media_pragas_planta" 
                           step="0.1" 
                           min="0"
                           value="<?= htmlspecialchars($pragaOriginal['media_pragas_planta'] ?? ''); ?>"
                           placeholder="Ex: 5.5">
                    <small class="text-muted">Número médio de pragas encontradas por planta</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="severidade" class="form-label">Severidade:</label>
                    <select class="form-select" id="severidade" name="severidade">
                        <option value="">Selecione...</option>
                        <option value="Baixa" <?= (isset($pragaOriginal['severidade']) && $pragaOriginal['severidade'] == 'Baixa') ? 'selected' : ''; ?>>Baixa</option>
                        <option value="Média" <?= (isset($pragaOriginal['severidade']) && $pragaOriginal['severidade'] == 'Média') ? 'selected' : ''; ?>>Média</option>
                        <option value="Alta" <?= (isset($pragaOriginal['severidade']) && $pragaOriginal['severidade'] == 'Alta') ? 'selected' : ''; ?>>Alta</option>
                        <option value="Muito Alta" <?= (isset($pragaOriginal['severidade']) && $pragaOriginal['severidade'] == 'Muito Alta') ? 'selected' : ''; ?>>Muito Alta</option>
                    </select>
                    <small class="text-muted">Nível de severidade do ataque</small>
                </div>
            </div>
        </div>

        <div class="img">
            <label>Nova Imagem (opcional - deixe em branco para manter a atual):
                <input type="file" name="imagem" accept="image/*">
            </label>
            <?php if (!empty($pragaOriginal['Imagem_Not_Null'])): ?>
                <div class="mt-2">
                    <small class="text-muted">Imagem atual:</small><br>
                    <img src="/uploads/pragas/<?= htmlspecialchars($pragaOriginal['Imagem_Not_Null']); ?>" 
                         alt="Imagem atual" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px;">
                </div>
            <?php endif; ?>
        </div>

        <div class="buttons">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Atualizar Praga
            </button>
            <a href="cadpraga.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </form>
</body>
</html>

