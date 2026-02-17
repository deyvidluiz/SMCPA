<?php
/**
 * Redefinir senha - Acesso via link do email (token na URL).
 * Valida o token, exibe formulário de nova senha e atualiza no banco.
 */
require_once('../../config.php');
include_once(BASE_URL . '/database/conexao.php');

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$erro = '';
$sucesso = false;
$token_valido = false;
$id_usuario = null;

if ($token !== '') {
    try {
        $db = new Database();
        $pdo = $db->conexao();
        $stmt = $pdo->prepare("
            SELECT r.ID, r.ID_Usuario, r.expira_em, r.usado
            FROM recuperacao_senha r
            WHERE r.token = :token
            LIMIT 1
        ");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ((int) $row['usado'] === 1) {
                $erro = 'Este link já foi utilizado. Solicite uma nova redefinição de senha.';
            } elseif (strtotime($row['expira_em']) < time()) {
                $erro = 'Este link expirou. Solicite uma nova redefinição de senha na tela de login.';
            } else {
                $token_valido = true;
                $id_usuario = (int) $row['ID_Usuario'];
            }
        } else {
            $erro = 'Link inválido. Solicite uma nova redefinição de senha.';
        }
    } catch (PDOException $e) {
        $erro = 'Erro ao validar o link. Tente novamente.';
        error_log('Redefinir senha: ' . $e->getMessage());
    }
} else {
    $erro = 'Link inválido. Acesse o link que enviamos por email.';
}

if ($token_valido && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_senha'], $_POST['confirmar_senha'])) {
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    if (strlen($nova_senha) < 6) {
        $erro = 'A nova senha deve ter no mínimo 6 caracteres.';
    } elseif ($nova_senha !== $confirmar_senha) {
        $erro = 'A nova senha e a confirmação não coincidem.';
    } else {
        try {
            $db = new Database();
            $pdo = $db->conexao();
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE Usuarios SET senha = :senha WHERE ID = :id");
            $upd->bindParam(':senha', $hash);
            $upd->bindParam(':id', $id_usuario, PDO::PARAM_INT);
            $upd->execute();

            $marco = $pdo->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE token = :token");
            $marco->bindParam(':token', $token);
            $marco->execute();
            $pdo->commit();

            $sucesso = true;
        } catch (PDOException $e) {
            if (isset($pdo)) $pdo->rollBack();
            $erro = 'Erro ao salvar a senha. Tente novamente.';
            error_log('Redefinir senha update: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha - SMCPA</title>
    <link rel="shortcut icon" href="/SMCPA/imgs/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/SMCPA/paginas/login/style.css">
</head>
<body>
  <div class="container-login">
    <img src="/SMCPA/imgs/logotrbf.png" alt="Logo SMCPA" class="logo">
    <h1 class="h1">Redefinir senha</h1>

    <?php if ($sucesso): ?>
      <div style="background-color: #d4edda; color: #155724; padding: 12px; margin-bottom: 16px; border-radius: 8px; border: 1px solid #c3e6cb;">
        Senha alterada com sucesso! Faça login com sua nova senha.
      </div>
      <p style="text-align: center;">
        <a href="../login/login.php">Ir para o login</a>
      </p>
    <?php elseif (!$token_valido && $token !== ''): ?>
      <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #f5c6cb;">
        <?= htmlspecialchars($erro) ?>
      </div>
      <p style="text-align: center;">
        <a href="esqueci_senha.php">Solicitar novo link</a> &nbsp;|&nbsp; <a href="../login/login.php">Voltar ao login</a>
      </p>
    <?php elseif ($token === ''): ?>
      <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px;">
        <?= htmlspecialchars($erro) ?>
      </div>
      <p style="text-align: center;">
        <a href="esqueci_senha.php">Esqueci minha senha</a> &nbsp;|&nbsp; <a href="../login/login.php">Login</a>
      </p>
    <?php else: ?>
      <?php if ($erro): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #f5c6cb;">
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form action="redefinir_senha.php?token=<?= htmlspecialchars(urlencode($token)) ?>" method="post">
        <label for="nova_senha">Nova senha</label>
        <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo 6 caracteres" minlength="6" required>
        <label for="confirmar_senha">Confirmar nova senha</label>
        <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a nova senha" minlength="6" required>
        <button type="submit">Redefinir senha</button>
      </form>
      <p style="text-align: center; margin-top: 16px;">
        <a href="../login/login.php">Voltar ao login</a>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
