<?php

namespace PocketDev\AntiVPN\Tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use PocketDev\AntiVPN\API\ProxyCheck;
use PocketDev\AntiVPN\API\IPHub;
use PocketDev\AntiVPN\Main;

class LoginCheckTask extends AsyncTask {

    /** @var string */
    private $ip;

    /** @var string */
    private $playerName;

    /** @var string */
    private $apiConfig;

    /** @var string */
    private $kickMessage;

    /**
     * Construtor
     * @param string $ip IP a ser verificado
     * @param string $playerName Nome do jogador
     * @param array $apiConfig Configurações das APIs
     * @param string $kickMessage Mensagem de kick
     */
    public function __construct($ip, $playerName, $apiConfig, $kickMessage) {
        $this->ip = $ip;
        $this->playerName = $playerName;
        $this->apiConfig = serialize($apiConfig);
        $this->kickMessage = $kickMessage;
    }

    /**
     * Executa a verificação de forma assíncrona
     */
    public function onRun() {
        $apiConfig = unserialize($this->apiConfig);
        $result = null;
        $details = [];
        $apiUsed = '';

        $primaryApi = isset($apiConfig['primary-api']) ? $apiConfig['primary-api'] : 'proxycheck';

        switch ($primaryApi) {
            case 'proxycheck':
                $api = new ProxyCheck(
                    isset($apiConfig['proxycheck']['api-key']) ? $apiConfig['proxycheck']['api-key'] : ''
                );
                $check = $api->checkIP($this->ip);
                if ($check !== null) {
                    $result = $check['is_vpn'];
                    $details = $check;
                    $apiUsed = 'proxycheck';
                }
                break;

            case 'iphub':
                if (isset($apiConfig['iphub']['api-key']) && !empty($apiConfig['iphub']['api-key'])) {
                    $api = new IPHub($apiConfig['iphub']['api-key']);
                    $check = $api->checkIP($this->ip);
                    if ($check !== null) {
                        $result = $check['is_vpn'];
                        $details = $check;
                        $apiUsed = 'iphub';
                    }
                }
                break;
        }

        if ($result === null) {
            $fallbackApi = isset($apiConfig['fallback-api']) ? $apiConfig['fallback-api'] : 'iphub';

            if ($primaryApi !== $fallbackApi) {
                switch ($fallbackApi) {
                    case 'proxycheck':
                        $api = new ProxyCheck(
                            isset($apiConfig['proxycheck']['api-key']) ? $apiConfig['proxycheck']['api-key'] : ''
                        );
                        $check = $api->checkIP($this->ip);
                        if ($check !== null) {
                            $result = $check['is_vpn'];
                            $details = $check;
                            $apiUsed = 'proxycheck (fallback)';
                        }
                        break;

                    case 'iphub':
                        if (isset($apiConfig['iphub']['api-key']) && !empty($apiConfig['iphub']['api-key'])) {
                            $api = new IPHub($apiConfig['iphub']['api-key']);
                            $check = $api->checkIP($this->ip);
                            if ($check !== null) {
                                $result = $check['is_vpn'];
                                $details = $check;
                                $apiUsed = 'iphub (fallback)';
                            }
                        }
                        break;
                }
            }
        }

        $this->setResult([
            'ip' => $this->ip,
            'player_name' => $this->playerName,
            'is_vpn' => $result,
            'details' => $details,
            'api_used' => $apiUsed,
            'kick_message' => $this->kickMessage
        ]);
    }

    /**
     * Processa o resultado após a execução assíncrona
     * @param Server $server
     */
    public function onCompletion(Server $server) {
        $plugin = $server->getPluginManager()->getPlugin("AntiVPN");

        if (!($plugin instanceof Main) || !$plugin->isEnabled()) {
            return;
        }

        $result = $this->getResult();
        $playerName = $result['player_name'];
        $player = $server->getPlayerExact($playerName);

        if (!$player) {
            return;
        }

        if ($result['is_vpn'] === true) {
            $player->kick($result['kick_message']);

            if ($plugin->getConfigManager()->isLoggingEnabled()) {
                $apiUsed = $result['api_used'] ?: 'unknown';
                $plugin->getLogger()->info("[$playerName/{$result['ip']}] Kickado - VPN detectada (API: $apiUsed)");

                if (!empty($result['details'])) {
                    $plugin->getLogger()->debug("Detalhes: " . json_encode($result['details']));
                }
            }
        }

        $plugin->processAsyncResult(
            uniqid('login_'),
            $result['ip'],
            $result['is_vpn'],
            $result['details']
        );
    }
}