<?php
session_start();
header('Content-Type: application/json');

// Função para registrar logs
function log_payment_data($message, $level = 'INFO') {
    $log_file = __DIR__ . '/payment.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = sprintf("[%s] [%s] %s\n", $timestamp, $level, $message);
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

log_payment_data('Nova requisição POST recebida.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_payment_data('Método não permitido: ' . $_SERVER['REQUEST_METHOD'], 'ERROR');
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

sleep(2);

$amount = isset($_POST['amount']) ? floatval(str_replace(',', '.', $_POST['amount'])) : 0;
$cpf = isset($_POST['cpf']) ? preg_replace('/\D/', '', $_POST['cpf']) : '';

if ($amount <= 0 || strlen($cpf) !== 11) {
    log_payment_data('Dados de entrada inválidos. Valor: ' . $amount . ', CPF: ' . $cpf, 'WARNING');
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

try {
    log_payment_data('Iniciando o processo de pagamento. Valor: ' . $amount . ', CPF: ' . $cpf);

    // Verificar autenticação do usuário
    if (!isset($_SESSION['usuario_id'])) {
        log_payment_data('Tentativa de pagamento por usuário não autenticado.', 'ERROR');
        throw new Exception('Usuário não autenticado.');
    }

    $usuario_id = $_SESSION['usuario_id'];
    log_payment_data('Usuário autenticado. ID: ' . $usuario_id);

    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch();

    if (!$usuario) {
        log_payment_data('Usuário com ID ' . $usuario_id . ' não encontrado no banco de dados.', 'ERROR');
        throw new Exception('Usuário não encontrado.');
    }

    // Buscar credenciais da SilverPay
    $stmt = $pdo->query("SELECT client_id, client_secret, urlnoty FROM silverpay LIMIT 1");
    $silverpay = $stmt->fetch();

    if (!$silverpay || empty($silverpay['client_id']) || empty($silverpay['client_secret'])) {
        log_payment_data('Credenciais da SilverPay não configuradas.', 'ERROR');
        throw new Exception('Credenciais da SilverPay não configuradas. Por favor, configure-as no painel administrativo.');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $base = $protocol . $host;

    // Endereço da API da SilverPay
    $apiUrl = 'https://silverpay.io/v3/pix/qrcode';

    // Preparar dados para a requisição
    $postData = [
        'client_id' => $silverpay['client_id'],
        'client_secret' => $silverpay['client_secret'],
        'nome' => $usuario['nome'],
        'cpf' => $cpf,
        'valor' => number_format($amount, 2, '.', ''),
        'descricao' => 'Depósito para ' . ($nomeSite ?? 'Raspadinha'),
        'urlnoty' => $silverpay['urlnoty']
    ];

    log_payment_data('Enviando requisição cURL para a SilverPay. Dados: ' . json_encode($postData));

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: SeuApp/1.0'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $pixData = json_decode($response, true);

    log_payment_data('Resposta da SilverPay recebida. Código HTTP: ' . $httpCode . ', Resposta: ' . $response);

    // Verificar a resposta da API
    if ($httpCode !== 200 || !isset($pixData['qrcode'])) {
        $errorMessage = isset($pixData['message']) ? $pixData['message'] : 'Erro desconhecido ao gerar QR Code.';
        throw new Exception($errorMessage);
    }

    // Salvar no banco
    $stmt = $pdo->prepare("
        INSERT INTO depositos (transactionId, user_id, nome, cpf, valor, status, qrcode, gateway, idempotency_key)
        VALUES (:transactionId, :user_id, :nome, :cpf, :valor, 'PENDING', :qrcode, 'silverpay', :idempotency_key)
    ");

    $stmt->execute([
        ':transactionId' => $pixData['transactionId'],
        ':user_id' => $usuario_id,
        ':nome' => $usuario['nome'],
        ':cpf' => $cpf,
        ':valor' => $amount,
        ':qrcode' => $pixData['qrcode'],
        ':idempotency_key' => $pixData['external_id'] // CORRIGIDO: Pega o external_id da resposta da SilverPay
    ]);

    $_SESSION['transactionId'] = $pixData['transactionId'];

    log_payment_data('Transação salva no banco de dados com sucesso. TransactionId: ' . $pixData['transactionId'] . ', IdempotencyKey: ' . $pixData['external_id']);

    echo json_encode([
        'qrcode' => $pixData['qrcode'],
        'gateway' => 'silverpay'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>