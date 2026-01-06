<?php
require_once 'conexao.php';

try {
    // Adiciona coluna chance_loss_percent (Porcentagem de derrota forçada - Padrão 70%)
    $pdo->exec("ALTER TABLE config ADD COLUMN IF NOT EXISTS chance_loss_percent INT DEFAULT 70");

    // Adiciona coluna max_prize_percent (Porcentagem máxima do saldo do usuário que pode ser ganha - Padrão 30%)
    $pdo->exec("ALTER TABLE config ADD COLUMN IF NOT EXISTS max_prize_percent INT DEFAULT 30");

    echo "Tabela 'config' atualizada com sucesso!<br>";
    echo "Colunas 'chance_loss_percent' e 'max_prize_percent' adicionadas.";

} catch (PDOException $e) {
    die("Erro ao atualizar banco de dados: " . $e->getMessage());
}
?>