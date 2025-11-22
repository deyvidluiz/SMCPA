<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <?php

    include_once('../trabalhofinal/conexao/conexao.php');

    $db = new Database();
    $conn = $db->conexao();

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $usuario = $_POST['usuario'];
        $senha = $_POST['senha'];
        $email = $_POST['email'];

        try {
            $stmt = $conn->prepare("INSERT INTO usuarios (usuario, senha, Email) VALUES (:usuario, :senha, :Email)");

            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindParam(':senha', $senha);
            $stmt->bindParam(':Email', $email);

            $stmt->execute();

            echo "Usuário cadastrado com sucesso!";
        } catch (PDOException $e) {
            echo "Erro ao cadastrar o usuário: " . $e->getMessage();
        }
    }
    ?>

    <a href="index.php">
        <button>Voltar para o Cadastro</button>
    </a>
</body>

</html>