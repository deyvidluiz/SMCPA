<?php
require_once('../../config.php');
include_once(BASE_URL.'/conexao/conexao.php');

// Verifica se o ID foi passado (por exemplo, via GET ou POST)
if (isset($_GET['id'])) {
    $id = $_GET['id'];  // ID do usuário a ser editado

    // Cria uma instância da classe Database e faz a conexão
    $db = new Database();
    $conn = $db->conexao();

    try {
        // Consulta para buscar os dados do usuário no banco
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Verifica se o usuário foi encontrado
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $usuario_predefinido = $usuario['usuario'];
            $email_predefinido = $usuario['Email'];
        } else {
            echo "<script>alert('Usuário não encontrado!'); window.location.href = 'dashboard.php';</script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao buscar o usuário: " . $e->getMessage() . "'); window.location.href = 'dashboard.php';</script>";
        exit;
    }

    // Verifica se o formulário foi enviado
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Verifica se as variáveis foram definidas corretamente
        if (isset($_POST['usuario'], $_POST['senha'], $_POST['email'])) {
            // Obtém os dados do formulário
            $usuario = $_POST['usuario'];
            $senha = $_POST['senha'];
            $email = $_POST['email'];

            // Criptografa a nova senha com password_hash() (se a senha não estiver vazia)
            if (!empty($senha)) {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);  // Gerando o hash da senha
            } else {
                $senha_hash = $usuario['senha'];  // Mantém a senha anterior se não for fornecida uma nova
            }

            try {
                // Prepara a consulta SQL para atualizar os dados no banco de dados
                $stmt = $conn->prepare("UPDATE usuarios SET usuario = :usuario, senha = :senha, Email = :Email WHERE id = :id");

                // Vincula os parâmetros
                $stmt->bindParam(':usuario', $usuario);
                $stmt->bindParam(':senha', $senha_hash);  // Usando a senha hash
                $stmt->bindParam(':Email', $email);
                $stmt->bindParam(':id', $id);

                // Executa a consulta
                $stmt->execute();

                echo "<script>alert('Usuário editado com sucesso!'); window.location.href = 'dashboard.php';</script>";
            } catch (PDOException $e) {
                // Em caso de erro, exibe a mensagem de erro
                echo "<script>alert('Erro ao editar o usuário: " . $e->getMessage() . "');</script>";
            }
        } else {
            echo "<script>alert('Erro: Todos os campos devem ser preenchidos!');</script>";
        }
    }
} else {
    echo "<script>alert('Erro: ID não fornecido!'); window.location.href = 'dashboard.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../../css/cadastro.css">  <!-- Caminho correto para o CSS -->
  <title>Editar Cadastro - SMCPA</title>
</head>
<body>
  <div class="container">
    <img src="/SMCPA/imgs/logotrbf.png" alt="Logo SMCPA" class="logo">
    <h1>Editar Cadastro</h1>
    <!-- Formulário de edição -->
    <form action="editar.php?id=<?php echo $id; ?>" method="POST">  <!-- Ação para o próprio arquivo com ID -->
      <label for="usuario">Nome</label>
      <input type="text" id="usuario" name="usuario" placeholder="Digite seu nome" value="<?php echo $usuario_predefinido; ?>" required>

      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" placeholder="Digite seu e-mail" value="<?php echo $email_predefinido; ?>" required>

      <!-- Campo para a nova senha -->
      <label for="senha">Nova Senha</label>
      <input type="password" id="senha" name="senha" placeholder="Digite sua nova senha">

      <button type="submit">Salvar alterações</button>
    </form>
  </div>
</body>
</html>
