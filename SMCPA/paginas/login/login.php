<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../../config.php');
include_once(BASE_URL.'/database/conexao.php');

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email'], $_POST['senha'])) {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        // Cria uma instância da classe Database e faz a conexão
        $db = new Database();
        $conn = $db->conexao();

        try {
            // Prepara a consulta SQL - Busca também o campo is_admin
            $stmt = $conn->prepare("SELECT id, usuario, email, senha, is_admin FROM Usuarios WHERE email = :email");
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
                    
                    // Verifica se o usuário é administrador
                    $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
                    $_SESSION['is_admin'] = $isAdmin;

                    // Redireciona conforme o tipo de usuário
                    if ($isAdmin) {
                        // Se for admin, vai para dashboardadm.php
                        header('Location: /SMCPA/paginas/dashboard/dashboardadm.php');
                    } else {
                        // Se não for admin, vai para dashboard.php
                        header('Location: /SMCPA/paginas/dashboard/dashboard.php');
                    }
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
    
    <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
      <div style="background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #c3e6cb;">
        Você saiu com sucesso! Faça login novamente para continuar.
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'acesso_negado'): ?>
      <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #f5c6cb;">
        Acesso negado! Apenas administradores podem acessar essa página.
      </div>
    <?php endif; ?>
    
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
      <div class="h1"><a href="../esqsenha/altsenha.php">Esqueceu a senha?</a></div>
      <button type="button" onclick="window.location.href='/SMCPA/paginas/cadastro/cadastro.php'" style="margin-top: 10px; background: #6c757d; color: #fff; border: none; border-radius: 6px; cursor: pointer; width: 100%; padding: 10px; font-weight: 500;" onmouseover="this.style.background='#5a6268'" onmouseout="this.style.background='#6c757d'">Ir para o Cadastro</button>
    </form>
  </div>
</body>
</html>