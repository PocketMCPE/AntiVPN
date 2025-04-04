<?php

namespace PocketDev\AntiVPN\API;

class ProxyCheck {

    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiUrl = "https://proxycheck.io/v2/";

    /**
     * Construtor
     * @param string $apiKey Chave de API (opcional)
     */
    public function __construct($apiKey = '') {
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
                'api_used' => 'proxycheck'
            ];
        }

        try {
            $url = $this->apiUrl . $ip;
            $query = [
                'vpn' => 1,
                'risk' => 1,
                'port' => 1,
                'seen' => 1,
                'days' => 7,
                'tag' => 'PocketVPNChecker'
            ];

            if (!empty($this->apiKey)) {
                $query['key'] = $this->apiKey;
            }

            $url .= '?' . http_build_query($query);

            $response = $this->makeHttpRequest($url);

            if ($response === null) {
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['status']) || $data['status'] !== 'ok' || !isset($data[$ip])) {
                return null;
            }

            $ipData = $data[$ip];

            $isProxy = isset($ipData['proxy']) && $ipData['proxy'] === 'yes';
            $isVPN = $isProxy;

            if (isset($ipData['type'])) {
                $proxyType = $ipData['type'];
            } else {
                $proxyType = $isProxy ? 'unknown' : 'none';
            }

            return [
                'is_vpn' => $isVPN,
                'provider' => isset($ipData['provider']) ? $ipData['provider'] : 'Unknown',
                'country_code' => isset($ipData['isocode']) ? $ipData['isocode'] : 'XX',
                'proxy_type' => $proxyType,
                'risk' => isset($ipData['risk']) ? (int)$ipData['risk'] : 0,
                'api_used' => 'proxycheck'
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Faz uma requisição HTTP
     *
     * @param string $url
     * @return string|null
     */
    private function makeHttpRequest($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
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

        return $response;
    }
}