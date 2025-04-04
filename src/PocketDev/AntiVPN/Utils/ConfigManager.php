<?php

namespace PocketDev\AntiVPN\Utils;

use PocketDev\AntiVPN\Main;

class ConfigManager {

    /** @var Main */
    private $plugin;

    /** @var array */
    private $config;

    /**
     * Construtor
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->reload();
    }

    /**
     * Recarrega a configuração
     */
    public function reload() {
        $this->plugin->reloadConfig();
        $this->config = $this->plugin->getConfig()->getAll();
    }

    /**
     * Obtém a mensagem de kick para usuários com VPN
     * @return string
     */
    public function getKickMessage() {
        return isset($this->config['kick-message']) ? $this->config['kick-message'] : "§cVPN detectada!";
    }

    /**
     * Verifica se um IP está na whitelist
     * @param string $ip
     * @return bool
     */
    public function isWhitelisted($ip) {
        if (!isset($this->config['ip-whitelist']) || !is_array($this->config['ip-whitelist'])) {
            return false;
        }

        return in_array($ip, $this->config['ip-whitelist']);
    }

    /**
     * Obtém as configurações das APIs
     * @return array
     */
    public function getApiConfig() {
        $result = [
            'primary-api' => isset($this->config['primary-api']) ? $this->config['primary-api'] : 'proxycheck',
            'fallback-api' => isset($this->config['fallback-api']) ? $this->config['fallback-api'] : 'iphub',
            'retry-limit' => isset($this->config['api-retry-limit']) ? (int)$this->config['api-retry-limit'] : 3,
            'proxycheck' => [
                'enabled' => true,
                'api-key' => ''
            ],
            'iphub' => [
                'enabled' => true,
                'api-key' => ''
            ]
        ];

        if (isset($this->config['api']) && is_array($this->config['api'])) {
            foreach (['proxycheck', 'iphub'] as $api) {
                if (isset($this->config['api'][$api]) && is_array($this->config['api'][$api])) {
                    foreach ($this->config['api'][$api] as $key => $value) {
                        $result[$api][$key] = $value;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Obtém o tempo de cache em segundos
     * @return int
     */
    public function getCacheTime() {
        return isset($this->config['cache-time']) ? (int)$this->config['cache-time'] : 3600;
    }

    /**
     * Obtém o intervalo de limpeza do cache em minutos
     * @return int
     */
    public function getCacheCleanupInterval() {
        return isset($this->config['cache-cleanup-interval']) ? (int)$this->config['cache-cleanup-interval'] : 30;
    }

    /**
     * Verifica se o logging está habilitado
     * @return bool
     */
    public function isLoggingEnabled() {
        return isset($this->config['enable-logs']) ? (bool)$this->config['enable-logs'] : true;
    }

    /**
     * Obtém o limite de tentativas por API
     * @return int
     */
    public function getApiRetryLimit() {
        return isset($this->config['api-retry-limit']) ? (int)$this->config['api-retry-limit'] : 3;
    }

    /**
     * Adiciona um IP à whitelist
     * @param string $ip
     * @return bool
     */
    public function addToWhitelist($ip) {
        if (!isset($this->config['ip-whitelist']) || !is_array($this->config['ip-whitelist'])) {
            $this->config['ip-whitelist'] = [];
        }

        if (!in_array($ip, $this->config['ip-whitelist'])) {
            $this->config['ip-whitelist'][] = $ip;
            $this->saveConfig();
            return true;
        }

        return false;
    }

    /**
     * Remove um IP da whitelist
     * @param string $ip
     * @return bool
     */
    public function removeFromWhitelist($ip) {
        if (!isset($this->config['ip-whitelist']) || !is_array($this->config['ip-whitelist'])) {
            return false;
        }

        $key = array_search($ip, $this->config['ip-whitelist']);
        if ($key !== false) {
            unset($this->config['ip-whitelist'][$key]);
            $this->config['ip-whitelist'] = array_values($this->config['ip-whitelist']); // Reindexar array
            $this->saveConfig();
            return true;
        }

        return false;
    }

    /**
     * Salva as configurações no arquivo
     */
    private function saveConfig() {
        $this->plugin->getConfig()->setAll($this->config);
        $this->plugin->getConfig()->save();
    }
}