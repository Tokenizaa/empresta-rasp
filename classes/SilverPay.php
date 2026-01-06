<?php

class SilverPay
{
    private $clientId;
    private $clientSecret;
    private $apiUrl = 'https://silverpay.io/v3';

    public function __construct($clientId, $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Obtém o saldo da conta na SilverPay
     * Retorna float com o saldo ou null em caso de erro
     */
    public function getBalance()
    {
        // Endpoint provável para saldo - PODE PRECISAR DE AJUSTE
        $endpoint = $this->apiUrl . '/balance'; // ou /pix/balance

        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_TIMEOUT => 3, // Timeout curto (3s) para não travar o jogo
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            // Assumindo que o retorno seja {"balance": 100.00} ou similar
            if (isset($data['balance'])) {
                return (float) $data['balance'];
            }
            if (isset($data['saldo'])) {
                return (float) $data['saldo'];
            }
        }

        return null; // Falha na consulta
    }
}
