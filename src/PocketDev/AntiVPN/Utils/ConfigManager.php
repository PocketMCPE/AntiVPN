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
     * Verifica se um jogador está na lista de permissões pelo apelido
     * @param string $playerName
     * @return bool
     */
    public function isNicknameWhitelisted($playerName) {
        if (!isset($this->config['nicknames-whitelist']) || !is_array($this->config['nicknames-whitelist'])) {
            return false;
        }

        return in_array(strtolower($playerName), array_map('strtolower', $this->config['nicknames-whitelist']));
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
     * Adiciona um apelido à lista de permissões
     * @param string $playerName
     * @return bool
     */
    public function addToNicknamesWhitelist($playerName) {
        if (!isset($this->config['nicknames-whitelist']) || !is_array($this->config['nicknames-whitelist'])) {
            $this->config['nicknames-whitelist'] = [];
        }

        $lowercasePlayerName = strtolower($playerName);
        $lowercaseWhitelist = array_map('strtolower', $this->config['nicknames-whitelist']);

        if (!in_array($lowercasePlayerName, $lowercaseWhitelist)) {
            $this->config['nicknames-whitelist'][] = $playerName;
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
     * Remove um apelido da lista de permissões
     * @param string $playerName
     * @return bool
     */
    public function removeFromNicknamesWhitelist($playerName) {
        if (!isset($this->config['nicknames-whitelist']) || !is_array($this->config['nicknames-whitelist'])) {
            return false;
        }

        $lowercasePlayerName = strtolower($playerName);
        $lowercaseWhitelist = array_map('strtolower', $this->config['nicknames-whitelist']);

        $key = array_search($lowercasePlayerName, $lowercaseWhitelist);
        if ($key !== false) {
            unset($this->config['nicknames-whitelist'][$key]);
            $this->config['nicknames-whitelist'] = array_values($this->config['nicknames-whitelist']);
            $this->saveConfig();
            return true;
        }

        return false;
    }

    /**
     * Define a API primária
     * @param string $api
     * @return bool
     */
    public function setPrimaryApi($api) {
        $validApis = ['proxycheck', 'iphub'];
        if (!in_array($api, $validApis)) {
            return false;
        }

        $this->config['primary-api'] = $api;
        $this->saveConfig();
        return true;
    }

    /**
     * Define a API de fallback
     * @param string $api
     * @return bool
     */
    public function setFallbackApi($api) {
        $validApis = ['proxycheck', 'iphub'];
        if (!in_array($api, $validApis)) {
            return false;
        }

        $this->config['fallback-api'] = $api;
        $this->saveConfig();
        return true;
    }

    /**
     * Habilita ou desabilita uma API
     * @param string $api
     * @param bool $enabled
     * @return bool
     */
    public function setApiEnabled($api, $enabled) {
        $validApis = ['proxycheck', 'iphub'];
        if (!in_array($api, $validApis)) {
            return false;
        }

        if (!isset($this->config['api']) || !is_array($this->config['api'])) {
            $this->config['api'] = [];
        }

        if (!isset($this->config['api'][$api]) || !is_array($this->config['api'][$api])) {
            $this->config['api'][$api] = [];
        }

        $this->config['api'][$api]['enabled'] = (bool)$enabled;
        $this->saveConfig();
        return true;
    }

    /**
     * Define a chave de API
     * @param string $api
     * @param string $key
     * @return bool
     */
    public function setApiKey($api, $key) {
        $validApis = ['proxycheck', 'iphub'];
        if (!in_array($api, $validApis)) {
            return false;
        }

        if (!isset($this->config['api']) || !is_array($this->config['api'])) {
            $this->config['api'] = [];
        }

        if (!isset($this->config['api'][$api]) || !is_array($this->config['api'][$api])) {
            $this->config['api'][$api] = ['enabled' => true];
        }

        $this->config['api'][$api]['api-key'] = $key;
        $this->saveConfig();
        return true;
    }

    /**
     * Salva as configurações no arquivo
     */
    private function saveConfig() {
        $this->plugin->getConfig()->setAll($this->config);
        $this->plugin->getConfig()->save();
    }
}