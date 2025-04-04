<?php

namespace PocketDev\AntiVPN\Utils;

use PocketDev\AntiVPN\Main;
use PocketDev\AntiVPN\API\ProxyCheck;
use PocketDev\AntiVPN\API\IPHub;

class VPNChecker {

    /** @var Main */
    private $plugin;

    /** @var array */
    private $apiInstances = [];

    /** @var array */
    private $apiFailures = [];

    /**
     * Construtor
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->initializeAPIs();
    }

    /**
     * Inicializa as instâncias de API
     */
    private function initializeAPIs() {
        $apiConfig = $this->plugin->getConfigManager()->getApiConfig();

        if ($apiConfig['proxycheck']['enabled']) {
            $this->apiInstances['proxycheck'] = new ProxyCheck(
                $apiConfig['proxycheck']['api-key']
            );
        }

        if ($apiConfig['iphub']['enabled']) {
            $this->apiInstances['iphub'] = new IPHub(
                $apiConfig['iphub']['api-key']
            );
        }

        foreach (array_keys($this->apiInstances) as $apiName) {
            $this->apiFailures[$apiName] = 0;
        }
    }

    /**
     * Verifica se um IP está usando VPN
     *
     * @param string $ip Endereço IP para verificar
     * @return bool|null true se for VPN, false se não for, null se não puder determinar
     */
    public function checkIP($ip) {
        $apiConfig = $this->plugin->getConfigManager()->getApiConfig();
        $primaryApi = $apiConfig['primary-api'];
        $fallbackApi = $apiConfig['fallback-api'];
        $retryLimit = $apiConfig['retry-limit'];

        if (isset($this->apiInstances[$primaryApi]) && $this->apiFailures[$primaryApi] < $retryLimit) {
            $result = $this->checkWithApi($this->apiInstances[$primaryApi], $ip, $primaryApi);
            if ($result !== null) {
                $this->apiFailures[$primaryApi] = 0;
                return $result;
            }
        }

        if ($primaryApi !== $fallbackApi && isset($this->apiInstances[$fallbackApi]) && $this->apiFailures[$fallbackApi] < $retryLimit) {
            $result = $this->checkWithApi($this->apiInstances[$fallbackApi], $ip, $fallbackApi);
            if ($result !== null) {
                $this->apiFailures[$fallbackApi] = 0;
                return $result;
            }
        }

        return null;
    }

    /**
     * Verifica um IP usando uma API específica
     *
     * @param object $api Instância da API
     * @param string $ip Endereço IP
     * @param string $apiName Nome da API para contagem de falhas
     * @return bool|null
     */
    private function checkWithApi($api, $ip, $apiName) {
        try {
            $result = $api->checkIP($ip);

            if ($result === null) {
                $this->apiFailures[$apiName]++;
                return null;
            }

            return $result['is_vpn'];
        } catch (\Exception $e) {
            $this->apiFailures[$apiName]++;

            if ($this->plugin->getConfigManager()->isLoggingEnabled()) {
                $this->plugin->getLogger()->warning("Erro ao verificar IP com API $apiName: " . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Obtém estatísticas sobre falhas de API
     *
     * @return array
     */
    public function getApiStats() {
        return [
            'failures' => $this->apiFailures,
            'instances' => array_keys($this->apiInstances)
        ];
    }

    /**
     * Reseta os contadores de falha
     */
    public function resetFailureCounters() {
        foreach (array_keys($this->apiFailures) as $apiName) {
            $this->apiFailures[$apiName] = 0;
        }
    }
}