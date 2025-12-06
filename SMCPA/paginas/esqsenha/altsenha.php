<?php
require_once('../../config.php');
include_once(BASE_URL.'/database/conexao.php');

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verifica se as variáveis foram definidas corretamente
    if (isset($_POST['usuario'], $_POST['senha_atual'], $_POST['nova_senha'], $_POST['confirmar_senha'])) {
        // Obtém os dados do formulário
        $usuario = $_POST['usuario'];
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        // Cria uma instância da classe Database e faz a conexão
        $db = new Database();
        $conn = $db->conexao();

        // Verifica se as senhas novas coincidem
        if ($nova_senha !== $confirmar_senha) {
            echo "<script>alert('As senhas não coincidem!');</script>";
        } else {
            try {
                // Busca a senha atual do banco de dados
                $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE usuario = :usuario");
                $stmt->bindParam(':usuario', $usuario);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Verifica se a senha atual está correta
                if ($user && password_verify($senha_atual, $user['senha'])) {
                    // Criptografa a nova senha
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

                    // Atualiza a senha no banco de dados
                    $update_stmt = $conn->prepare("UPDATE usuarios SET senha = :senha WHERE usuario = :usuario");
                    $update_stmt->bindParam(':senha', $senha_hash);
                    $update_stmt->bindParam(':usuario', $usuario);
                    $update_stmt->execute();

                    // Exibe a mensagem de sucesso com o link para a página inicial
                    echo "<script>
                            alert('Senha alterada com sucesso!');
                            window.location.href = '../inicial/inicial.html'; // Redireciona para a página inicial
                          </script>";
                } else {
                    echo "<script>alert('Senha atual incorreta!');</script>";
                }
            } catch (PDOException $e) {
                echo "<script>alert('Erro ao alterar a senha: " . $e->getMessage() . "');</script>";
            }
        }
    } else {
        echo "<script>alert('Erro: Todos os campos devem ser preenchidos!');</script>";
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
  <title>Alterar Senha - SMCPA</title>
</head>
<body>
  <div class="container">
    <img src="/SMCPA/imgs/logotrbf.png" alt="Logo SMCPA" class="logo">
    <h1>Alterar Senha</h1>
    <!-- Formulário de alteração de senha -->
    <form action="altsenha.php" method="POST">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" placeholder="Digite seu nome de usuário" required>

      <label for="senha_atual">Senha Atual</label>
      <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual" required>

      <label for="nova_senha">Nova Senha</label>
      <input type="password" id="nova_senha" name="nova_senha" placeholder="Digite a nova senha" required>

      <label for="confirmar_senha">Confirmar Nova Senha</label>
      <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Confirme a nova senha" required>

      <button type="submit">Alterar Senha</button>
    </form>
  </div>
</body>
</html>
