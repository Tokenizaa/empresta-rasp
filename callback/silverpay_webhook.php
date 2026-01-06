<?php
// Define o tipo de conteúdo como JSON para a resposta da API
header('Content-Type: application/json');

// Desabilita o cache para garantir que a resposta seja sempre nova
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// --- Configuração e Conexão com o Banco de Dados ---
// Define o caminho para o arquivo de log. Certifique-se de que o diretório tem permissão de escrita.
$logFile = __DIR__ . '/webhook_log_silverpay.txt';

// Função para logar mensagens no arquivo de forma padronizada
function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}

// Loga o início da execução do script para rastrear o momento da chamada
logMessage("Script do webhook iniciado.");

// Recebe o corpo da requisição POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se o JSON foi recebido corretamente
if (!$data || !isset($data['transactionType'])) {
    logMessage("Dados do webhook inválidos ou ausentes: " . $input);
    http_response_code(400); // Responde com erro de requisição
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

// Inclui o arquivo de conexão com o banco de dados. Ajuste o caminho se necessário.
require_once __DIR__ . '/../conexao.php';

// Loga o payload completo recebido para depuração
logMessage("Webhook recebido: " . $input);

try {
    // Inicia uma transação no banco de dados para garantir que todas as operações sejam atômicas
    $pdo->beginTransaction();

    switch ($data['transactionType']) {
        case 'RECEIVEPIX':
            if ($data['status'] === 'PAID') {
                $transactionId = $data['transactionId'] ?? null;
                $amount = $data['amount'] ?? 0;
                $externalId = $data['external_id'] ?? null;

                // Validação de dados essenciais para o processamento
                if (empty($transactionId) || empty($externalId) || $amount <= 0) {
                    logMessage("Dados essenciais ausentes na notificação RECEIVEPIX.");
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
                    exit;
                }

                // Busca a transação no banco de dados usando a chave de idempotência (external_id)
                logMessage("Buscando depósito no banco de dados para external_id: " . $externalId);
                $stmt = $pdo->prepare("SELECT id, user_id, valor, status FROM depositos WHERE idempotency_key = :external_id LIMIT 1");
                $stmt->bindParam(':external_id', $externalId, PDO::PARAM_STR);
                $stmt->execute();
                $deposito = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$deposito) {
                    logMessage("Depósito não encontrado para external_id: " . $externalId);
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Depósito não encontrado']);
                    exit;
                }

                // Verifica se o depósito já foi processado para evitar duplicação
                if ($deposito['status'] === 'PAID') {
                    logMessage("Depósito já processado para external_id: " . $externalId);
                    http_response_code(200);
                    echo json_encode(['status' => 'success', 'message' => 'Depósito já processado']);
                    exit;
                }

                // Verificação de segurança: compara o valor do webhook com o valor salvo no banco
                if (number_format($amount, 2) != number_format($deposito['valor'], 2)) {
                    logMessage("Aviso de segurança: Valor do webhook ({$amount}) não corresponde ao valor no banco de dados ({$deposito['valor']}). Transação: " . $deposito['id']);
                }

                logMessage("Depósito encontrado. Atualizando status e saldo para o usuário ID: " . $deposito['user_id']);

                // Atualiza o status do depósito para "PAID"
                $stmt = $pdo->prepare("UPDATE depositos SET status = 'PAID', transactionId = :transaction_id WHERE id = :id");
                $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_STR);
                $stmt->bindParam(':id', $deposito['id'], PDO::PARAM_INT);
                $stmt->execute();

                // Adiciona o valor ao saldo do usuário
                $stmt = $pdo->prepare("UPDATE usuarios SET saldo = saldo + :amount WHERE id = :user_id");
                $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
                $stmt->bindParam(':user_id', $deposito['user_id'], PDO::PARAM_INT);
                $stmt->execute();

                // Confirma as alterações no banco de dados
                $pdo->commit();
                logMessage("Sucesso: Depósito de R$ " . $amount . " para o usuário ID " . $deposito['user_id'] . " confirmado e saldo atualizado.");

                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Pagamento processado com sucesso']);
            } else {
                // Se a notificação não for de pagamento pago, ignora
                logMessage("Notificação RECEIVEPIX com status diferente de PAID. Status: " . ($data['status'] ?? 'N/A'));
                http_response_code(200);
                echo json_encode(['status' => 'ignored', 'message' => 'Status do pagamento não requer ação']);
            }
            break;
        
        default:
            // Lida com tipos de transação desconhecidos
            logMessage("Tipo de transação desconhecido: " . $data['transactionType']);
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de transação não suportado']);
            break;
    }

} catch (Exception $e) {
    // Em caso de erro, desfaz todas as operações no banco de dados
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logMessage("Erro inesperado no processamento do webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor']);
}

logMessage("Script finalizado.");
?>