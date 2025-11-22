<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../config.php');
include_once(BASE_URL.'/conexao/conexao.php');

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email'], $_POST['senha'])) {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        // Cria uma instância da classe Database e faz a conexão
        $db = new Database();
        $conn = $db->conexao();

        try {
            // Prepara a consulta SQL - CORRIGIDO: email minúsculo na tabela
            $stmt = $conn->prepare("SELECT id, usuario, email, senha FROM usuarios WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            // Verifica se o usuário foi encontrado
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verifica se a senha fornecida é correta
                if (password_verify($senha, $user['senha'])) {
                    // IMPORTANTE: Define todas as variáveis de sessão necessárias
                    $_SESSION['logado'] = true;
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['usuario'] = $user['usuario'];
                    $_SESSION['email'] = $user['email'];

                    // Redireciona para o dashboard
                    header('Location: /SMCPA/paginas/dashboard/dashboard.php');
                    exit();
                } else {
                    $erro_login = "Senha incorreta!";
                }
            } else {
                $erro_login = "Email não encontrado!";
            }
        } catch (PDOException $e) {
            $erro_login = "Erro ao verificar o usuário: " . $e->getMessage();
        }
    } else {
        $erro_login = "Erro: Todos os campos devem ser preenchidos!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMCPA - Login</title>
    <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">    
    <link rel="stylesheet" href="/SMCPA/paginas/login/style.css">
</head>
<body>
  <div class="container-login">
    <img src="/SMCPA/imgs/logotrbf.png" alt="Logo SMCPA" class="logo">

    <h1 class="h1">Login</h1>
    
    <?php if (isset($erro_login)): ?>
      <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #f5c6cb;">
        <?php echo htmlspecialchars($erro_login); ?>
      </div>
    <?php endif; ?>

    <form action="./login.php" method="post">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Digite seu email" required>
      
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
      
      <button type="submit">Entrar</button>
    </form>

    <div class="h1"><a href="../esqsenha/altsenha.php">Esqueceu a senha?</a></div>
  </div>
</body>
</html>