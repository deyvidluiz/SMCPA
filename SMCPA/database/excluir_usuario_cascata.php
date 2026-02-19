<?php
/**
 * Exclui um usuário e todas as dependências (FKs) em cascata.
 * Uso: excluir_usuario_cascata($pdo, $usuarioId);
 * Retorna true em sucesso. Lança PDOException em falha.
 * A exclusão da imagem do usuário e das imagens de pragas deve ser feita pelo chamador.
 */
function excluir_usuario_cascata(PDO $pdo, $usuarioId) {
  $uid = (int) $usuarioId;
  if ($uid <= 0) {
    return true;
  }

  // 1) Snapshot do autor nos feedbacks (para admin continuar vendo após exclusão)
  try {
    $stmtUser = $pdo->prepare("SELECT usuario, Email, localizacao, Data_Cadastro FROM Usuarios WHERE id = :id");
    $stmtUser->bindParam(':id', $uid, PDO::PARAM_INT);
    $stmtUser->execute();
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($userRow) {
      $stmtUpdateFb = $pdo->prepare("
        UPDATE Feedback SET Autor_Nome = :nome, Autor_Email = :email, Autor_Localizacao = :loc, Autor_Data_Cadastro = :data, Usuario = NULL WHERE Usuario = :uid
      ");
      $stmtUpdateFb->bindValue(':nome', $userRow['usuario'] ?? null, PDO::PARAM_STR);
      $stmtUpdateFb->bindValue(':email', $userRow['Email'] ?? null, PDO::PARAM_STR);
      $stmtUpdateFb->bindValue(':loc', $userRow['localizacao'] ?? null, PDO::PARAM_STR);
      $stmtUpdateFb->bindValue(':data', !empty($userRow['Data_Cadastro']) ? $userRow['Data_Cadastro'] : null, PDO::PARAM_STR);
      $stmtUpdateFb->bindParam(':uid', $uid, PDO::PARAM_INT);
      $stmtUpdateFb->execute();
    }
  } catch (PDOException $e) {
    // Colunas Autor_* podem não existir; tenta apenas SET NULL se a FK permitir
    try {
      $pdo->prepare("UPDATE Feedback SET Usuario = NULL WHERE Usuario = :uid")->execute([':uid' => $uid]);
    } catch (PDOException $e2) {
      // Segue; outras tabelas serão limpas
    }
  }

  // 2) IDs das pragas deste usuário
  $stmtPragas = $pdo->prepare("SELECT ID FROM Pragas_Surtos WHERE ID_Usuario = :uid");
  $stmtPragas->bindParam(':uid', $uid, PDO::PARAM_INT);
  $stmtPragas->execute();
  $pragaIds = array_column($stmtPragas->fetchAll(PDO::FETCH_ASSOC), 'ID');

  if (!empty($pragaIds)) {
    $placeholders = implode(',', array_fill(0, count($pragaIds), '?'));
    $params = $pragaIds;

    try { $pdo->prepare("DELETE FROM Conselho_Manejo WHERE fk_Pragas_Surtos_ID IN ($placeholders)")->execute($params); } catch (PDOException $e) {}
    try { $pdo->prepare("DELETE FROM Recomendacao WHERE fk_Praga IN ($placeholders)")->execute($params); } catch (PDOException $e) {}
    try { $pdo->prepare("DELETE FROM Alerta WHERE fk_Pragas_Surtos_ID IN ($placeholders)")->execute($params); } catch (PDOException $e) {}
    try { $pdo->prepare("DELETE FROM Imagem WHERE fk_Pragas_Surtos_ID IN ($placeholders)")->execute($params); } catch (PDOException $e) {}
    try { $pdo->prepare("DELETE FROM Registra_Historico_Pragas WHERE fk_Pragas_Surtos_ID IN ($placeholders)")->execute($params); } catch (PDOException $e) {}
    try { $pdo->prepare("DELETE FROM Cadastra_Surtos_Admin WHERE fk_Surto_ID IN ($placeholders)")->execute($params); } catch (PDOException $e) {}
  }

  // 3) Histórico do usuário (Registra_Historico_Pragas depois Historico)
  try {
    $stmtH = $pdo->prepare("SELECT ID FROM Historico WHERE ID_Usuario = :uid");
    $stmtH->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmtH->execute();
    $histIds = array_column($stmtH->fetchAll(PDO::FETCH_ASSOC), 'ID');
    if (!empty($histIds)) {
      $ph = implode(',', array_fill(0, count($histIds), '?'));
      $pdo->prepare("DELETE FROM Registra_Historico_Pragas WHERE fk_Historico_ID IN ($ph)")->execute($histIds);
    }
  } catch (PDOException $e) {}
  try {
    $st = $pdo->prepare("DELETE FROM Historico WHERE ID_Usuario = :uid");
    $st->execute(['uid' => $uid]);
  } catch (PDOException $e) {}

  // 4) Pragas do usuário
  try {
    $st = $pdo->prepare("DELETE FROM Pragas_Surtos WHERE ID_Usuario = :uid");
    $st->execute(['uid' => $uid]);
  } catch (PDOException $e) {
    throw $e;
  }

  // 5) Tabelas que referenciam Usuarios(ID)
  $run = function ($sql) use ($pdo, $uid) {
    try {
      $st = $pdo->prepare($sql);
      $st->execute(['uid' => $uid]);
    } catch (PDOException $e) {}
  };
  $run("DELETE FROM Avalia WHERE fk_Usuarios_ID = :uid");
  $run("DELETE FROM Comenta WHERE fk_Usuarios_ID = :uid");
  $run("DELETE FROM Visualiza_Tutorial WHERE fk_Usuarios_ID = :uid");
  $run("DELETE FROM Visualiza_Historico WHERE fk_Usuarios_ID = :uid");
  $run("DELETE FROM Avisa WHERE fk_Usuarios_ID = :uid");
  $run("DELETE FROM Cadastra_Surtos_Admin WHERE fk_Usuarios_ID = :uid");
  $run("DELETE FROM recuperacao_senha WHERE ID_Usuario = :uid");

  // 6) Excluir o usuário
  $stmtDel = $pdo->prepare("DELETE FROM Usuarios WHERE id = :id");
  $stmtDel->bindParam(':id', $uid, PDO::PARAM_INT);
  $stmtDel->execute();

  return true;
}
