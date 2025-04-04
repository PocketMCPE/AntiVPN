<?php

namespace PocketDev\AntiVPN\Tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use PocketDev\AntiVPN\API\ProxyCheck;
use PocketDev\AntiVPN\API\IPHub;
use PocketDev\AntiVPN\Main;

class CheckIPTask extends AsyncTask {

    /** @var string */
    private $taskId;

    /** @var string */
    private $ip;

    /** @var string */
    private $apiConfig;

    /**
     * Construtor
     * @param string $taskId Identificador único da tarefa
     * @param string $ip IP a ser verificado
     * @param array $apiConfig Configurações das APIs
     */
    public function __construct($taskId, $ip, $apiConfig) {
        $this->taskId = $taskId;
        $this->ip = $ip;
        $this->apiConfig = serialize($apiConfig);
    }

    /**
     * Executa a verificação de forma assíncrona
     */
    public function onRun() {
        $apiConfig = unserialize($this->apiConfig);
        $result = null;
        $details = [];

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
                }
                break;

            case 'iphub':
                if (isset($apiConfig['iphub']['api-key']) && !empty($apiConfig['iphub']['api-key'])) {
                    $api = new IPHub($apiConfig['iphub']['api-key']);
                    $check = $api->checkIP($this->ip);
                    if ($check !== null) {
                        $result = $check['is_vpn'];
                        $details = $check;
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
                        }
                        break;

                    case 'iphub':
                        if (isset($apiConfig['iphub']['api-key']) && !empty($apiConfig['iphub']['api-key'])) {
                            $api = new IPHub($apiConfig['iphub']['api-key']);
                            $check = $api->checkIP($this->ip);
                            if ($check !== null) {
                                $result = $check['is_vpn'];
                                $details = $check;
                            }
                        }
                        break;
                }
            }
        }

        $this->setResult([
            'task_id' => $this->taskId,
            'ip' => $this->ip,
            'is_vpn' => $result,
            'details' => $details
        ]);
    }

    /**
     * Processa o resultado após a execução assíncrona
     * @param Server $server
     */
    public function onCompletion(Server $server) {
        $plugin = $server->getPluginManager()->getPlugin("AntiVPN");

        if ($plugin instanceof Main && $plugin->isEnabled()) {
            $result = $this->getResult();

            $plugin->processAsyncResult(
                $result['task_id'],
                $result['ip'],
                $result['is_vpn'],
                $result['details']
            );
        }
    }
}