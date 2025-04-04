<?php

namespace PocketDev\AntiVPN\API;

class IPHub {

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiUrl = "https://v2.api.iphub.info/ip/";

    /**
     * Construtor
     * @param string $apiKey Chave de API (obrigatória)
     */
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Verifica se um IP está usando VPN/proxy
     *
     * @param string $ip Endereço IP para verificar
     * @return array|null Array com resultados ou null em caso de erro
     */
    public function checkIP($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return [
                'is_vpn' => false,
                'provider' => 'Local IP',
                'country_code' => 'LOCAL',
                'proxy_type' => 'N/A',
                'api_used' => 'iphub'
            ];
        }

        if (empty($this->apiKey)) {
            return null;
        }

        try {
            $url = $this->apiUrl . $ip;

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'X-Key: ' . $this->apiKey,
                        'User-Agent: PocketMine-MP/AntiVPN-Plugin',
                        'Accept: application/json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['block'])) {
                return null;
            }

            $blockScore = (int)$data['block'];

            $isVPN = ($blockScore === 1);

            return [
                'is_vpn' => $isVPN,
                'provider' => isset($data['isp']) ? $data['isp'] : 'Unknown',
                'country_code' => isset($data['countryCode']) ? $data['countryCode'] : 'XX',
                'proxy_type' => $isVPN ? 'proxy/vpn/hosting' : 'none',
                'block_score' => $blockScore,
                'api_used' => 'iphub'
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}