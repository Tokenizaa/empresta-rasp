<?php
require_once 'conexao.php';

try {
    $id = 4;
    $nome = 'Chefe';
    $telefone = '(11) 99999-9999';
    $email = 'netto@lojaon.shop';
    $senhaPlain = 'Netto@964212#';

    // Gera o hash seguro da senha
    $senhaHash = password_hash($senhaPlain, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, telefone = ?, email = ?, senha = ? WHERE id = ?");
    $stmt->execute([$nome, $telefone, $email, $senhaHash, $id]);

    echo "Admin (ID $id) atualizado com sucesso!\n";
    echo "Nome: $nome\n";
    echo "Email: $email\n";
    echo "Senha atualizada para o hash da senha fornecida.\n";

} catch (PDOException $e) {
    echo "Erro ao atualizar: " . $e->getMessage();
}
?>