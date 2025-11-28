<?php
require_once('../../config.php');
include_once(BASE_URL.'/conexao/conexao.php');

// Função opcional para "limpar" o nome do arquivo
function normalizaString($str) {
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    $str = trim($str, '-');
    return $str;
}

// Variáveis para manter os dados do formulário em caso de erro
$usuario = '';
$email = '';
$senha = '';
$localizacao = '';
$mensagemErro = '';
$cadastroSucesso = false;

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verifica se as variáveis foram definidas corretamente
    if (isset($_POST['usuario'], $_POST['senha'], $_POST['email'])) {
        // Obtém os dados do formulário (para manter preenchidos em caso de erro)
        $usuario = $_POST['usuario'];
        $senha   = $_POST['senha'];
        $email   = $_POST['email'];
        $localizacao = trim($_POST['localizacao'] ?? '');

        // Cria uma instância da classe Database e faz a conexão
        $db   = new Database();
        $conn = $db->conexao();

        // ====== TRATAMENTO DA IMAGEM (OPCIONAL) ======
        $arquivo     = isset($_FILES['imagem']) ? $_FILES['imagem'] : null;
        $nomeImagem  = null; // valor padrão (sem imagem)
        $erroImagem  = false; // flag para controlar se houve erro na imagem

        // Configurações de validação
        $larguraMax  = 1920;
        $alturaMax   = 1080;
        $tamanhoMax  = 5 * 1024 * 1024; // 5MB
        $errosUpload = [];

        // Se o usuário escolheu um arquivo, valida obrigatoriamente
        if ($arquivo && !empty($arquivo['name'])) {
            
            // Verifica se o arquivo foi enviado corretamente
            if (empty($arquivo['tmp_name']) || !file_exists($arquivo['tmp_name'])) {
                $errosUpload[] = "Erro no upload do arquivo. Tente novamente.";
                $erroImagem = true;
            } else {
                // Verifica tipo
                if (!preg_match('/^(image)\/(jpeg|png|jpg)$/i', $arquivo['type'])) {
                    $errosUpload[] = "A imagem deve ser JPG ou PNG.";
                    $erroImagem = true;
                }

                // Verifica dimensões
                if (!$erroImagem) {
                    $dimensoes = @getimagesize($arquivo['tmp_name']);
                    if ($dimensoes !== false) {
                        if ($dimensoes[0] > $larguraMax || $dimensoes[1] > $alturaMax) {
                            $errosUpload[] = "A imagem precisa ter no máximo 1920x1080 pixels.";
                            $erroImagem = true;
                        }
                    } else {
                        $errosUpload[] = "Não foi possível ler as dimensões da imagem. Arquivo inválido.";
                        $erroImagem = true;
                    }
                }

                // Verifica tamanho
                if (!$erroImagem && $arquivo['size'] > $tamanhoMax) {
                    $errosUpload[] = "A imagem precisa ser menor que 5MB.";
                    $erroImagem = true;
                }

                // Se não teve erro, faz o upload
                if (!$erroImagem && count($errosUpload) == 0) {
                    $extensao   = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
                    $baseNome   = normalizaString($usuario); // usa o nome do usuário no arquivo
                    $nomeImagem = $baseNome . '_' . time() . '.' . $extensao;

                    // Diretório de upload
                    $diretorio = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/usuarios/';
                    if (!is_dir($diretorio)) {
                        if (!@mkdir($diretorio, 0777, true)) {
                            $errosUpload[] = "Erro: Não foi possível criar o diretório de upload.";
                            $erroImagem = true;
                        }
                    }

                    // Se o diretório existe, tenta fazer o upload
                    if (!$erroImagem && is_dir($diretorio)) {
                        $caminho = $diretorio . $nomeImagem;

                        if (!move_uploaded_file($arquivo['tmp_name'], $caminho)) {
                            $errosUpload[] = "Erro ao salvar a imagem no servidor.";
                            $erroImagem = true;
                            $nomeImagem = null;
                        }
                    } else if (!is_dir($diretorio)) {
                        $errosUpload[] = "Erro: Diretório de upload não encontrado.";
                        $erroImagem = true;
                        $nomeImagem = null;
                    }
                }
            }
            
            // Se houve erros na imagem, bloqueia o cadastro
            if ($erroImagem && count($errosUpload) > 0) {
                $mensagemErro = $errosUpload[0]; // Pega o primeiro erro
            }
        }

        // Só prossegue com o cadastro se não houver erro na imagem
        if (!$erroImagem) {
            try {
                // Verificar se a coluna localizacao existe, se não, criar
                try {
                    $stmtCheck = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'localizacao'");
                    $colunaExiste = $stmtCheck->rowCount() > 0;
                    
                    if (!$colunaExiste) {
                        $conn->exec("ALTER TABLE usuarios ADD COLUMN localizacao VARCHAR(255) DEFAULT NULL");
                    }
                } catch (PDOException $e) {
                    // Ignorar erro - coluna provavelmente já existe
                }
                
                // Criptografa a senha com password_hash()
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);  // Gerando o hash da senha

                // INSERT com a coluna Imagem e Localizacao
                $stmt = $conn->prepare("
                    INSERT INTO usuarios (usuario, senha, Email, Imagem, localizacao) 
                    VALUES (:usuario, :senha, :Email, :Imagem, :localizacao)
                ");

                // Vincula os parâmetros
                $stmt->bindParam(':usuario', $usuario);
                $stmt->bindParam(':senha',   $senha_hash);  // Usando a senha hash
                $stmt->bindParam(':Email',   $email);
                $stmt->bindParam(':Imagem',  $nomeImagem);  // pode ser null
                $stmt->bindParam(':localizacao', $localizacao);  // localização do usuário

                // Executa a consulta
                $stmt->execute();

                // Mensagem de sucesso e limpa os campos
                $cadastroSucesso = true;
                $usuario = '';
                $email = '';
                $senha = '';
                $localizacao = '';
                $mensagemErro = 'Usuário cadastrado com sucesso!';
            } catch (PDOException $e) {
                // Em caso de erro, mantém os dados preenchidos
                $mensagemErro = 'Erro ao cadastrar o usuário: ' . $e->getMessage();
            }
        }
    } else {
        $mensagemErro = 'Erro: Todos os campos devem ser preenchidos!';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../../css/cadastro.css">  <!-- Caminho correto para o CSS -->
  <title>SMCPA - Sistema de Monitoramento e Controle de Pragas Agrícolas</title>
</head>
<body>
  <div class="container">
    <img src="/SMCPA/imgs/logotrbf.png" alt="Logo SMCPA" class="logo">
    <h1>Cadastrar-se</h1>                                                                                                                                                                                                                                                                                                                                                                                                      
    
    <!-- Exibe mensagem de erro ou sucesso -->
    <?php if (!empty($mensagemErro)): ?>
      <div class="mensagem <?= $cadastroSucesso ? 'sucesso' : 'erro' ?>" style="padding: 10px; margin-bottom: 15px; border-radius: 5px; background-color: <?= $cadastroSucesso ? '#d4edda' : '#f8d7da' ?>; color: <?= $cadastroSucesso ? '#155724' : '#721c24' ?>; border: 1px solid <?= $cadastroSucesso ? '#c3e6cb' : '#f5c6cb' ?>;">
        <?= htmlspecialchars($mensagemErro) ?>
      </div>
    <?php endif; ?>
    
    <!-- Formulário de cadastro -->
    <!-- IMPORTANTE: enctype para upload de arquivo -->
    <form action="../cadastro/cadastro.php" method="POST" enctype="multipart/form-data" id="formCadastro">
      <label for="usuario">Nome</label>
      <input type="text" id="usuario" name="usuario" placeholder="Digite seu nome" value="<?= htmlspecialchars($usuario) ?>" required>

      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" placeholder="Digite seu e-mail" value="<?= htmlspecialchars($email) ?>" required>

      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" placeholder="Digite sua senha" value="<?= htmlspecialchars($senha) ?>" required>

      <label for="localizacao">Localização/Região <span style="color: #dc3545;">*</span></label>
      <input type="text" id="localizacao" name="localizacao" placeholder="Ex: Zona Rural de São Paulo, Fazenda ABC, etc." value="<?= htmlspecialchars($localizacao) ?>" required>
      <small style="display: block; margin-top: 5px; color: #666;">Esta informação será usada para mostrar surtos próximos na sua região</small>
      
      <!-- Campo opcional de imagem -->
      <label for="imagem">Foto de perfil (opcional) - JPG ou PNG até 5MB</label>
      <input type="file" id="imagem" name="imagem" accept="image/jpeg, image/png, image/jpg">
      <small style="display: block; margin-top: 5px; color: #666;">Formatos aceitos: JPG, PNG. Tamanho máximo: 5MB. Dimensões máximas: 1920x1080px</small>

      <button type="submit">Cadastrar</button>
      <button type="button" onclick="window.location.href='../login/login.php'" style="margin-top: 10px; background: #6c757d; color: #fff; border: none; border-radius: 6px; cursor: pointer; width: 100%; padding: 10px; font-weight: 500;" onmouseover="this.style.background='#5a6268'" onmouseout="this.style.background='#6c757d'">Voltar</button>
    </form>
  </div>
  
  <script>
    // Limpa o campo de arquivo em caso de sucesso
    <?php if ($cadastroSucesso): ?>
      document.getElementById('formCadastro').reset();
      document.getElementById('senha').value = '';
    <?php endif; ?>
  </script>
</body>
</html>
