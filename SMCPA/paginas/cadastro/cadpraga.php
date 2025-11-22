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
include_once(BASE_URL.'/conexao/conexao.php');

// ====== CLASSE UPLOAD ADAPTADA PARA Pragas_Surtos ======
class Upload {

    private $con;
    private $usuarioLogado; // NOVO: armazena o ID do usuário logado

    private $ID;
    private $Nome;
    private $Planta_Hospedeira;
    private $Descricao;
    private $Imagem_Not_Null;
    private $ID_Praga;
    private $Localidade;
    private $Data_Aparicao;
    private $Observacoes;
    private $ID_Usuario; // NOVO: campo para armazenar o usuário

    public function __construct($usuarioID = null) {
        $this->con = new Database();
        $this->usuarioLogado = $usuarioID; // NOVO: recebe o ID do usuário
    }
    
    // FUNÇÕES AUXILIARES
    private function tratarCaracter($valor, $tipo = 1) {
        if ($tipo == 1) {
            return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
        }
        return trim($valor);
    }
    
    private function normalizaString($str) {
        $str = strtolower($str);
        $str = preg_replace('/[^a-z0-9]+/', '-', $str);
        $str = trim($str, '-');
        return $str;
    }

    public function __set($atributo, $valor) {
        $this->$atributo = $valor;
    }

    public function __get($atributo) {
        return $this->$atributo;
    }

    // LISTAR TODOS (apenas do usuário logado)
    public function querySelect() {
        try {
            $cst = $this->con->conexao()->prepare("SELECT 
                    ID, Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, ID_Praga, 
                    Localidade, Data_Aparicao, Observacoes, ID_Usuario 
                    FROM Pragas_Surtos 
                    WHERE ID_Usuario = :usuarioID 
                    ORDER BY ID DESC");
            $cst->bindParam(':usuarioID', $this->usuarioLogado, PDO::PARAM_INT);
            $cst->execute();
            return $cst->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            return [];
        }
    }

    // BUSCAR 1
    public function querySelecionar($vlr) {
        try {
            $this->ID = $vlr;
            $cst = $this->con->conexao()->prepare("SELECT 
                    ID, Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, ID_Praga, 
                    Localidade, Data_Aparicao, Observacoes, ID_Usuario 
                    FROM Pragas_Surtos 
                    WHERE ID = :id AND ID_Usuario = :usuarioID");
            $cst->bindParam(':id', $this->ID, PDO::PARAM_INT);
            $cst->bindParam(':usuarioID', $this->usuarioLogado, PDO::PARAM_INT);
            $cst->execute();
            return $cst->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $ex) {
            return null;
        }
    }

    // INSERIR - CORRIGIDO COM ID_USUARIO
    public function queryInsert() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo '<script type="text/javascript">alert("Método inválido.");</script>';
                return;
            }

            // Dados do formulário
            $this->Nome              = isset($_POST['nome']) ? $_POST['nome'] : '';
            $this->Planta_Hospedeira = isset($_POST['planta_hospedeira']) ? $_POST['planta_hospedeira'] : '';
            $this->Descricao         = isset($_POST['descricao']) ? $_POST['descricao'] : '';
            $this->ID_Praga          = isset($_POST['id_praga']) ? $_POST['id_praga'] : '';
            $this->Localidade        = isset($_POST['localidade']) ? $_POST['localidade'] : '';
            $this->Data_Aparicao     = isset($_POST['data_aparicao']) ? $_POST['data_aparicao'] : '';
            $this->Observacoes       = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';
            $this->ID_Usuario        = $this->usuarioLogado; // NOVO: define o usuário

            $arquivo   = isset($_FILES['imagem']) ? $_FILES['imagem'] : null;

            $largura   = 1920;
            $altura    = 1080;
            $tamanho   = 5 * 1024 * 1024; // 5MB
            $erros     = [];

            if ($arquivo && !empty($arquivo['name'])) {

                // Tipo de arquivo
                if (!preg_match('/^(image)\/(jpeg|png|jpg)$/', $arquivo['type'])) {
                    $erros[] = "Só pode ser enviado imagens JPG ou PNG.";
                }

                // Dimensões da imagem
                $dimensoes = getimagesize($arquivo['tmp_name']);
                if ($dimensoes !== false) {
                    if ($dimensoes[0] > $largura || $dimensoes[1] > $altura) {
                        $erros[] = "A imagem precisa estar nas dimensões máximas de 1920x1080 pixels.";
                    }
                } else {
                    $erros[] = "Não foi possível ler as dimensões da imagem.";
                }

                // Tamanho do arquivo
                if ($arquivo['size'] > $tamanho) {
                    $erros[] = "A imagem precisa ser menor que 5MB.";
                }

                if (count($erros) == 0) {

                    $ext         = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
                    $nome_base   = $this->normalizaString($this->Nome);
                    $timestamp   = time();
                    $nome_imagem = $nome_base.'_'.$timestamp.'.'.$ext;

                    $diretorio = $_SERVER['DOCUMENT_ROOT'].'/uploads/pragas/';
                    if (!is_dir($diretorio)) {
                        mkdir($diretorio, 0755, true);
                    }

                    $caminho_imagem = $diretorio.$nome_imagem;

                    if (move_uploaded_file($arquivo['tmp_name'], $caminho_imagem)) {

                        $this->Imagem_Not_Null = $nome_imagem;

                        // QUERY CORRIGIDA COM ID_USUARIO
                        $cst = $this->con->conexao()->prepare("
                            INSERT INTO Pragas_Surtos (
                                Nome, Planta_Hospedeira, Descricao, Imagem_Not_Null, ID_Praga, 
                                Localidade, Data_Aparicao, Observacoes, ID_Usuario
                            ) VALUES (
                                :Nome, :Planta_Hospedeira, :Descricao, :Imagem_Not_Null, 
                                :ID_Praga, :Localidade, :Data_Aparicao, :Observacoes, :ID_Usuario
                            )
                        ");

                        $nome_tratado        = $this->tratarCaracter($this->Nome, 1);
                        $planta_tratada      = $this->tratarCaracter($this->Planta_Hospedeira, 1);
                        $descricao_tratada   = $this->tratarCaracter($this->Descricao, 1);
                        $id_praga_tratado    = $this->tratarCaracter($this->ID_Praga, 1);
                        $localidade_tratada  = $this->tratarCaracter($this->Localidade, 1);
                        $observacoes_tratadas= $this->tratarCaracter($this->Observacoes, 1);

                        $cst->bindParam(':Nome',              $nome_tratado, PDO::PARAM_STR);
                        $cst->bindParam(':Planta_Hospedeira', $planta_tratada, PDO::PARAM_STR);
                        $cst->bindParam(':Descricao',         $descricao_tratada, PDO::PARAM_STR);
                        $cst->bindParam(':Imagem_Not_Null',   $this->Imagem_Not_Null, PDO::PARAM_STR);
                        $cst->bindParam(':ID_Praga',          $id_praga_tratado, PDO::PARAM_STR);
                        $cst->bindParam(':Localidade',        $localidade_tratada, PDO::PARAM_STR);
                        $cst->bindParam(':Data_Aparicao',     $this->Data_Aparicao, PDO::PARAM_STR);
                        $cst->bindParam(':Observacoes',       $observacoes_tratadas, PDO::PARAM_STR);
                        $cst->bindParam(':ID_Usuario',        $this->ID_Usuario, PDO::PARAM_INT); // NOVO

                        if ($cst->execute()) {
                            echo '<script type="text/javascript">
                                alert("Praga cadastrada com sucesso!");
                                window.location.href = "../dashboard/dashboard.php";
                            </script>';
                            exit;
                        } else {
                            echo '<script type="text/javascript">alert("Erro ao armazenar os dados no banco.");</script>';
                        }

                    } else {
                        echo '<script type="text/javascript">alert("Erro ao mover o arquivo de imagem.");</script>';
                    }

                } else {
                    echo '<script type="text/javascript">alert("'.implode(' ', $erros).'");</script>';
                }

            } else {
                echo '<script type="text/javascript">alert("Escolha um arquivo de imagem para upload.");</script>';
            }

        } catch (PDOException $ex) {
            echo '<script type="text/javascript">alert("Error: '.$ex->getMessage().'");</script>';
        }
    }

    // DELETAR
    public function queryDelete($vlr) {
        try {
            $this->ID = $vlr;

            $rst = $this->querySelecionar($this->ID);
            
            if (!$rst) {
                echo '<script type="text/javascript">alert("Registro não encontrado ou sem permissão.");</script>';
                return;
            }

            $diretorio = $_SERVER['DOCUMENT_ROOT'].'/uploads/pragas/';

            if (!empty($rst['Imagem_Not_Null']) && file_exists($diretorio.$rst['Imagem_Not_Null'])) {
                @unlink($diretorio.$rst['Imagem_Not_Null']);
            }

            $cst = $this->con->conexao()->prepare("DELETE FROM Pragas_Surtos WHERE ID = :ID AND ID_Usuario = :usuarioID");
            $cst->bindParam(':ID', $this->ID, PDO::PARAM_INT);
            $cst->bindParam(':usuarioID', $this->usuarioLogado, PDO::PARAM_INT);

            if ($cst->execute()) {
                header('location: cadpraga.php?sucesso=deletado');
                exit;
            } else {
                echo '<script type="text/javascript">alert("Erro ao deletar o registro.");</script>';
            }

        } catch (PDOException $ex) {
            echo '<script type="text/javascript">alert("Error: '.$ex->getMessage().'");</script>';
        }
    }
}

// ====== CONTROLE SIMPLES (INSERIR / DELETAR) ======
$upload = new Upload($usuarioID); // PASSA O ID DO USUÁRIO

// Inserção
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'inserir') {
    $upload->queryInsert();
}

// Deletar
if (isset($_GET['delete'])) {
    $upload->queryDelete((int)$_GET['delete']);
}

// Buscar lista para exibir (apenas do usuário)
$lista = $upload->querySelect();
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
  <link rel="stylesheet" href="../../css/cadpragas.css">
  <title>Cadastro de Pragas - SMCPA</title>
</head>
<body>
    <div class="h1">
        <h1>Cadastro de Pragas / Surtos</h1>
        <p class="text-muted">Usuário: <?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Não identificado'); ?></p>
    </div>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Operação realizada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- FORMULÁRIO DE CADASTRO -->
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="inserir">

        <div class="nome">
            <label>Nome comum da praga:
                <input type="text" name="nome" required>
            </label>
        </div>

        <div class="hosp">
            <label>Planta hospedeira:
                <input type="text" name="planta_hospedeira" required>
            </label>
        </div>

        <div class="desc">
            <label>Descrição detalhada:
                <textarea name="descricao" rows="3"></textarea>
            </label>
        </div>

        <div class="loca"> 
            <label>Localidade:
                <input type="text" name="localidade">
            </label>
        </div>

        <div class="data">
            <label>Data de aparição:
                <input type="date" name="data_aparicao">
            </label>
        </div>

        <div class="obs">
            <label>Observações:
                <textarea name="observacoes" rows="3"></textarea>
            </label>
        </div>

        <div class="arqimg">
            <label>Imagem (até 1920x1080, JPG ou PNG, máx. 5MB)*:
                <input type="file" name="imagem" accept="image/jpeg,image/png,image/jpg" required>
            </label>
        </div>

        <div class="but">
            <button type="submit">Salvar praga</button>
        </div>
    </form>

    <!-- LISTA DE PRAGAS CADASTRADAS -->
    <?php if (!empty($lista)): ?>
        <div class="mt-4">
            <h3>Minhas Pragas Cadastradas (<?php echo count($lista); ?>)</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagem</th>
                        <th>Nome</th>
                        <th>Planta</th>
                        <th>Localidade</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $praga): ?>
                        <tr>
                            <td><?php echo $praga['ID']; ?></td>
                            <td>
                                <?php if (!empty($praga['Imagem_Not_Null'])): ?>
                                    <img src="/uploads/pragas/<?php echo htmlspecialchars($praga['Imagem_Not_Null']); ?>" 
                                         style="width:50px; height:50px; object-fit:cover;" alt="Praga">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($praga['Nome']); ?></td>
                            <td><?php echo htmlspecialchars($praga['Planta_Hospedeira']); ?></td>
                            <td><?php echo htmlspecialchars($praga['Localidade']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($praga['Data_Aparicao'])); ?></td>
                            <td>
                                <a href="?delete=<?php echo $praga['ID']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Tem certeza que deseja excluir?');">
                                    Excluir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

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
                        <a href="../dashboard/dashboard.php">
                            <span class="icon"><i class="fa-solid fa-home"></i></span>
                            <span class="txt-link">Home</span>
                        </a>
                    </li>
                    <li class="item-menu">
                        <a href="../dashboard/dashboardadm.php">
                            <span class="icon"><i class="bi bi-columns-gap"></i></span>
                            <span class="txt-link">Dashboard</span>
                        </a>
                    </li>
                    <li class="item-menu ativo">
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
                        <a href="../dashboard/perfil.php">
                            <span class="icon"><i class="bi bi-person-lines-fill"></i></span>
                            <span class="txt-link">Conta</span>
                        </a>
                    </li>
                </ul>
            </nav> 
        </aside>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>