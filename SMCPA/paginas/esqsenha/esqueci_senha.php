<?php

/**
 * Esqueci minha senha - Solicita redefinição por email.
 * Gera um token, grava em recuperacao_senha e envia link por email (SMTP Gmail no localhost).
 */
require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');
require_once(BASE_URL . '/enviar_email_smtp.php');

$sucesso = false;
$erro = '';
$email_enviado = false;
$link_para_teste = null; // em localhost exibimos o link na tela para testar sem email

// Garantir que a tabela existe (compatível com quem ainda não rodou o bancodedados.sql atualizado)
try {
  $db = new Database();
  $pdo = $db->conexao();
  $pdo->exec("
        CREATE TABLE IF NOT EXISTS recuperacao_senha (
            ID INT NOT NULL AUTO_INCREMENT,
            ID_Usuario INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expira_em DATETIME NOT NULL,
            usado TINYINT(1) DEFAULT 0,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            UNIQUE KEY uk_token (token),
            KEY idx_expira (expira_em),
            KEY idx_usado (usado),
            FOREIGN KEY (ID_Usuario) REFERENCES Usuarios(ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ");
} catch (PDOException $e) {
  // Tabela pode já existir com outra estrutura
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
  $email = trim($_POST['email']);

  try {
    $db = new Database();
    $pdo = $db->conexao();

    $stmt = $pdo->prepare("SELECT ID, usuario, Email FROM Usuarios WHERE Email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
      $erro = 'Não existe conta cadastrada com este email.';
    } else {
      $token = bin2hex(random_bytes(32));
      $expira_em = date('Y-m-d H:i:s', strtotime('+1 hour'));

      $ins = $pdo->prepare("INSERT INTO recuperacao_senha (ID_Usuario, token, expira_em) VALUES (:id_usuario, :token, :expira_em)");
      $ins->bindParam(':id_usuario', $usuario['ID'], PDO::PARAM_INT);
      $ins->bindParam(':token', $token);
      $ins->bindParam(':expira_em', $expira_em);
      $ins->execute();

      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $config_email = is_file(BASE_URL . '/config_email.php') ? include BASE_URL . '/config_email.php' : [];
      $url_base = !empty($config_email['url_base']) ? rtrim($config_email['url_base'], '/') : ($scheme . '://' . $host /**. '/SMCPA' */);
      $linkRedefinir = $url_base . '/SMCPA/paginas/esqsenha/redefinir_senha.php?token=' . urlencode($token);

      $assunto = 'Redefinição de senha - SMCPA';
      $mensagem = "Olá, " . $usuario['usuario'] . ",\n\n";
      $mensagem .= "Você solicitou a redefinição de senha no SMCPA.\n\n";
      $mensagem .= "Clique no link abaixo para definir uma nova senha (válido por 1 hora):\n";
      $mensagem .= $linkRedefinir . "\n\n";
      $mensagem .= "Se você não solicitou isso, ignore este email. O link expira em 1 hora.\n\n";
      $mensagem .= "Sistema SMCPA - Monitoramento e Controle de Pragas Agrícolas";

      $enviado = enviar_email_smtp($usuario['Email'], $assunto, $mensagem);

      $sucesso = true;
      $email_enviado = $enviado;
      $eh_localhost = in_array($host, ['localhost', '127.0.0.1'], true)
        || (strpos($host, 'localhost') !== false) || (strpos($host, '127.0.0.1') !== false);
      if ($eh_localhost && !$enviado) {
        $link_para_teste = $linkRedefinir;
      }
    }
  } catch (PDOException $e) {
    $erro = 'Erro ao processar. Tente novamente mais tarde.';
    error_log('Esqueci senha: ' . $e->getMessage());
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Esqueci minha senha - SMCPA</title>
  <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="/SMCPA/paginas/login/style.css">
</head>

<body>
  <div class="container-login">
    <img src="/SMCPA/imgs/logotrbf.png" alt="Logo SMCPA" class="logo">
    <h1 class="h1">Esqueci minha senha</h1>

    <?php if ($sucesso): ?>
      <div style="background-color: #d4edda; color: #155724; padding: 12px; margin-bottom: 16px; border-radius: 8px; border: 1px solid #c3e6cb;">
        <?php if ($email_enviado): ?>
          Enviamos um link para o email cadastrado. Verifique sua caixa de entrada (e a pasta de spam) e clique no link para redefinir sua senha. O link expira em 1 hora.
        <?php elseif (!empty($link_para_teste)): ?>
          <strong>Modo localhost:</strong> o email pode não ter sido enviado. Use o link abaixo para redefinir a senha (válido por 1 hora):
          <p style="margin: 12px 0 8px;"><a href="<?= htmlspecialchars($link_para_teste) ?>" style="word-break: break-all;"><?= htmlspecialchars($link_para_teste) ?></a></p>
        <?php else: ?>
          Se existir uma conta com esse email, você receberá em instantes um link para redefinir a senha. Verifique a pasta de spam. O link expira em 1 hora.
        <?php endif; ?>
      </div>
      <p style="text-align: center; margin-top: 12px;">
        <a href="../login/login.php">Voltar ao login</a>
      </p>
    <?php else: ?>
      <?php if ($erro): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #f5c6cb;">
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <p style="color: #4b5563; font-size: 0.95rem; margin-bottom: 16px;">
        Informe o email cadastrado. Enviaremos um link para você redefinir sua senha.
      </p>
      <form action="esqueci_senha.php" method="post">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Digite o email da sua conta" required
          value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        <button type="submit">Enviar link para redefinir senha</button>
      </form>
      <p style="text-align: center; margin-top: 16px;">
        <a href="../login/login.php">Voltar ao login</a>
      </p>
    <?php endif; ?>
  </div>
</body>

</html>