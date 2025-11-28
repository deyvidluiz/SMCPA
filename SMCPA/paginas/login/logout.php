<?php
// Configurar cookie de sessão
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Se é desejado matar a sessão, também delete o cookie de sessão.
// Nota: Isto destruirá a sessão, e não apenas os dados da sessão!
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destruir a sessão
session_destroy();

// Headers para prevenir cache e garantir que o botão voltar não funcione
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Redirecionar para a página de login
header("Location: /SMCPA/paginas/login/login.php?logout=success");
exit();
?>

