<?php

namespace PocketDev\AntiVPN;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

use PocketDev\AntiVPN\Events\PlayerJoinListener;
use PocketDev\AntiVPN\Utils\ConfigManager;
use PocketDev\AntiVPN\Utils\VPNChecker;
use PocketDev\AntiVPN\Tasks\CacheCleanupTask;
use PocketDev\AntiVPN\Tasks\CheckIPTask;

class Main extends PluginBase {

    /** @var ConfigManager */
    private $configManager;

    /** @var VPNChecker */
    private $vpnChecker;

    /** @var array<string, array> */
    private $ipCache = [];

    /** @var array<string, callable> */
    private $pendingChecks = [];

    /** @var string */
    private $cacheFile;

    /**
     * Inicializa o plugin, carrega configurações, listeners e tarefas agendadas.
     */
    public function onEnable() {
        @mkdir($this->getDataFolder());

        $this->cacheFile = $this->getDataFolder() . "cache.json";
        $this->loadCache();

        $this->saveDefaultConfig();
        $this->configManager = new ConfigManager($this);

        $this->vpnChecker = new VPNChecker($this);

        $this->getServer()->getPluginManager()->registerEvents(
            new PlayerJoinListener($this),
            $this
        );

        $cleanupInterval = $this->configManager->getCacheCleanupInterval() * 1200;
        $this->getServer()->getScheduler()->scheduleRepeatingTask(
            new CacheCleanupTask($this),
            $cleanupInterval
        );

        $this->getLogger()->info(TF::GREEN . "AntiVPN ativado com sucesso!");
    }

    /**
     * Carrega o cache de IPs do arquivo.
     */
    private function loadCache() {
        if (file_exists($this->cacheFile)) {
            try {
                $content = file_get_contents($this->cacheFile);
                $data = json_decode($content, true);

                if (is_array($data)) {
                    $this->ipCache = $data;
                    $this->getLogger()->info("Cache de IP carregado: " . count($this->ipCache) . " entradas");
                }
            } catch (\Exception $e) {
                $this->getLogger()->warning("Erro ao carregar cache: " . $e->getMessage());
            }
        }
    }

    /**
     * Salva o cache de IPs em disco.
     */
    private function saveCache() {
        try {
            file_put_contents($this->cacheFile, json_encode($this->ipCache));
        } catch (\Exception $e) {
            $this->getLogger()->warning("Erro ao salvar cache: " . $e->getMessage());
        }
    }

    /** @return ConfigManager */
    public function getConfigManager() {
        return $this->configManager;
    }

    /** @return VPNChecker */
    public function getVPNChecker() {
        return $this->vpnChecker;
    }

    /**
     * Verifica se um IP está usando VPN com base no cache e chamadas diretas.
     *
     * @param string $ip
     * @return bool
     */
    public function isVPN($ip) {
        if (isset($this->ipCache[$ip])) {
            $cacheData = $this->ipCache[$ip];
            if (time() - $cacheData['time'] < $this->configManager->getCacheTime()) {
                return $cacheData['result'];
            }
        }

        if ($this->configManager->isWhitelisted($ip)) {
            return false;
        }

        $result = $this->vpnChecker->checkIP($ip);

        $this->ipCache[$ip] = [
            'result' => $result,
            'time' => time()
        ];

        $this->saveCache();

        return $result;
    }

    /**
     * Executa uma checagem de VPN assíncrona e chama o callback ao finalizar.
     *
     * @param string $ip
     * @param callable $callback
     */
    public function checkVPNAsync($ip, callable $callback) {
        if (isset($this->ipCache[$ip])) {
            $cacheData = $this->ipCache[$ip];
            if (time() - $cacheData['time'] < $this->configManager->getCacheTime()) {
                call_user_func($callback, $ip, $cacheData['result'], $cacheData);
                return;
            }
        }

        if ($this->configManager->isWhitelisted($ip)) {
            call_user_func($callback, $ip, false, ['source' => 'whitelist']);
            return;
        }

        $taskId = uniqid('vpncheck_');
        $this->pendingChecks[$taskId] = $callback;

        $this->getServer()->getScheduler()->scheduleAsyncTask(
            new CheckIPTask(
                $taskId,
                $ip,
                $this->configManager->getApiConfig()
            )
        );
    }

    /**
     * Processa o resultado de uma checagem assíncrona.
     *
     * @param string $taskId
     * @param string $ip
     * @param bool $isVPN
     * @param array $details
     */
    public function processAsyncResult($taskId, $ip, $isVPN, $details) {
        $this->ipCache[$ip] = [
            'result' => $isVPN,
            'time' => time(),
            'details' => $details
        ];

        $this->saveCache();

        if (isset($this->pendingChecks[$taskId])) {
            call_user_func($this->pendingChecks[$taskId], $ip, $isVPN, $details);
            unset($this->pendingChecks[$taskId]);
        }
    }

    /**
     * Remove entradas antigas do cache de IPs.
     */
    public function cleanupCache() {
        $now = time();
        $cacheTime = $this->configManager->getCacheTime();
        $cacheSize = count($this->ipCache);
        $removed = 0;

        foreach ($this->ipCache as $ip => $data) {
            if ($now - $data['time'] >= $cacheTime) {
                unset($this->ipCache[$ip]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->saveCache();
        }

        if ($this->configManager->isLoggingEnabled()) {
            $this->getLogger()->debug("Limpeza de cache concluída. Removidas: $removed. Restantes: " . count($this->ipCache));
        }
    }

    /**
     * Executa comandos do plugin como /antivpn.
     *
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if (strtolower($command->getName()) !== "antivpn") {
            return false;
        }

        if (!$sender->hasPermission("antivpn.admin")) {
            $sender->sendMessage(TF::RED . "Você não tem permissão para usar este comando.");
            return true;
        }

        if (empty($args[0])) {
            $sender->sendMessage(TF::GREEN . "==== AntiVPN ====");
            $sender->sendMessage(TF::YELLOW . "/antivpn check <player> " . TF::WHITE . "- Verifica se um jogador está usando VPN");
            $sender->sendMessage(TF::YELLOW . "/antivpn checkip <ip> " . TF::WHITE . "- Verifica se um IP está usando VPN");
            $sender->sendMessage(TF::YELLOW . "/antivpn reload " . TF::WHITE . "- Recarrega a configuração");
            $sender->sendMessage(TF::YELLOW . "/antivpn clearcache " . TF::WHITE . "- Limpa o cache de verificações");
            $sender->sendMessage(TF::YELLOW . "/antivpn stats " . TF::WHITE . "- Exibe estatísticas das APIs");
            $sender->sendMessage(TF::YELLOW . "/antivpn whitelist <add|remove> <ip> " . TF::WHITE . "- Gerencia IPs na whitelist");
            $sender->sendMessage(TF::YELLOW . "/antivpn nickwhitelist <add|remove> <nickname> " . TF::WHITE . "- Gerencia nicknames na whitelist");
            $sender->sendMessage(TF::YELLOW . "/antivpn savecache " . TF::WHITE . "- Força o salvamento do cache");
            $sender->sendMessage(TF::YELLOW . "/antivpn setprimary <api> " . TF::WHITE . "- Define a API primária");
            $sender->sendMessage(TF::YELLOW . "/antivpn setfallback <api> " . TF::WHITE . "- Define a API secundária");
            $sender->sendMessage(TF::YELLOW . "/antivpn api <api> <enable|disable> " . TF::WHITE . "- Ativa ou desativa uma API");
            $sender->sendMessage(TF::YELLOW . "/antivpn setkey <api> <key> " . TF::WHITE . "- Define a chave para uma API");
            return true;
        }

        switch (strtolower($args[0])) {
            case "reload":
                $this->reloadConfig();
                $this->configManager->reload();
                $sender->sendMessage(TF::GREEN . "Configuração recarregada com sucesso!");
                break;

            case "clearcache":
                $count = count($this->ipCache);
                $this->ipCache = [];
                $this->saveCache();
                $sender->sendMessage(TF::GREEN . "Cache limpo! $count entradas removidas.");
                break;

            case "savecache":
                $this->saveCache();
                $sender->sendMessage(TF::GREEN . "Cache salvo manualmente com " . count($this->ipCache) . " entradas.");
                break;

            case "stats":
                $stats = $this->vpnChecker->getApiStats();
                $sender->sendMessage(TF::GREEN . "==== AntiVPN Estatísticas ====");
                $sender->sendMessage(TF::YELLOW . "APIs disponíveis: " . TF::WHITE . implode(", ", $stats['instances']));
                $sender->sendMessage(TF::YELLOW . "Falhas por API: ");

                foreach ($stats['failures'] as $api => $failures) {
                    $sender->sendMessage("  " . TF::WHITE . $api . ": " . ($failures > 0 ? TF::RED : TF::GREEN) . $failures);
                }

                $sender->sendMessage(TF::YELLOW . "Entradas em cache: " . TF::WHITE . count($this->ipCache));
                break;

            case "whitelist":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn whitelist <add|remove> <ip>");
                    return true;
                }

                $action = strtolower($args[1]);
                $ip = $args[2];

                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $sender->sendMessage(TF::RED . "Endereço IP inválido.");
                    return true;
                }

                if ($action === "add") {
                    if ($this->configManager->addToWhitelist($ip)) {
                        $sender->sendMessage(TF::GREEN . "IP $ip adicionado à whitelist.");
                    } else {
                        $sender->sendMessage(TF::YELLOW . "IP $ip já está na whitelist.");
                    }
                } else if ($action === "remove") {
                    if ($this->configManager->removeFromWhitelist($ip)) {
                        $sender->sendMessage(TF::GREEN . "IP $ip removido da whitelist.");
                    } else {
                        $sender->sendMessage(TF::YELLOW . "IP $ip não estava na whitelist.");
                    }
                } else {
                    $sender->sendMessage(TF::RED . "Ação inválida. Use 'add' ou 'remove'.");
                }
                break;

            case "nickwhitelist":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn nickwhitelist <add|remove> <nickname>");
                    return true;
                }

                $action = strtolower($args[1]);
                $nickname = $args[2];

                if ($action === "add") {
                    if ($this->configManager->addToNicknamesWhitelist($nickname)) {
                        $sender->sendMessage(TF::GREEN . "Nickname $nickname adicionado à whitelist.");
                    } else {
                        $sender->sendMessage(TF::YELLOW . "Nickname $nickname já está na whitelist.");
                    }
                } else if ($action === "remove") {
                    if ($this->configManager->removeFromNicknamesWhitelist($nickname)) {
                        $sender->sendMessage(TF::GREEN . "Nickname $nickname removido da whitelist.");
                    } else {
                        $sender->sendMessage(TF::YELLOW . "Nickname $nickname não estava na whitelist.");
                    }
                } else {
                    $sender->sendMessage(TF::RED . "Ação inválida. Use 'add' ou 'remove'.");
                }
                break;

            case "check":
                if (empty($args[1])) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn check <player>");
                    return true;
                }

                $player = $this->getServer()->getPlayer($args[1]);
                if (!$player) {
                    $sender->sendMessage(TF::RED . "Jogador não encontrado.");
                    return true;
                }

                $ip = $player->getAddress();
                $playerName = $player->getName();

                $sender->sendMessage(TF::YELLOW . "Verificando jogador $playerName...");

                $this->checkVPNAsync($ip, function($ip, $isVPN, $details) use ($sender, $playerName) {
                    if ($isVPN === null) {
                        $sender->sendMessage(TF::YELLOW . "Não foi possível determinar se o jogador $playerName está usando VPN.");
                        return;
                    }

                    if ($isVPN) {
                        $sender->sendMessage(TF::RED . "O jogador $playerName parece estar usando VPN.");
                        if (!empty($details)) {
                            $sender->sendMessage(TF::GRAY . "Detalhes: " . json_encode($details));
                        }
                    } else {
                        $sender->sendMessage(TF::GREEN . "O jogador $playerName não parece estar usando VPN.");
                    }
                });
                break;

            case "checkip":
                if (empty($args[1])) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn checkip <ip>");
                    return true;
                }

                $ip = $args[1];
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $sender->sendMessage(TF::RED . "Endereço IP inválido.");
                    return true;
                }

                $sender->sendMessage(TF::YELLOW . "Verificando IP $ip...");

                $this->checkVPNAsync($ip, function($ip, $isVPN, $details) use ($sender) {
                    if ($isVPN === null) {
                        $sender->sendMessage(TF::YELLOW . "Não foi possível determinar se o IP $ip está usando VPN.");
                        return;
                    }

                    if ($isVPN) {
                        $sender->sendMessage(TF::RED . "O IP $ip parece estar usando VPN.");
                        if (!empty($details)) {
                            $sender->sendMessage(TF::GRAY . "Detalhes: " . json_encode($details));
                        }
                    } else {
                        $sender->sendMessage(TF::GREEN . "O IP $ip não parece estar usando VPN.");
                    }
                });
                break;

            case "setprimary":
                if (count($args) < 2) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn setprimary <api>");
                    $sender->sendMessage(TF::YELLOW . "APIs disponíveis: proxycheck, iphub");
                    return true;
                }

                $api = strtolower($args[1]);
                if ($this->configManager->setPrimaryApi($api)) {
                    $sender->sendMessage(TF::GREEN . "API primária definida para: " . TF::WHITE . $api);
                    $this->vpnChecker = new VPNChecker($this); // Reinicializa o checker com nova config
                } else {
                    $sender->sendMessage(TF::RED . "API inválida. Use proxycheck ou iphub.");
                }
                break;

            case "setfallback":
                if (count($args) < 2) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn setfallback <api>");
                    $sender->sendMessage(TF::YELLOW . "APIs disponíveis: proxycheck, iphub");
                    return true;
                }

                $api = strtolower($args[1]);
                if ($this->configManager->setFallbackApi($api)) {
                    $sender->sendMessage(TF::GREEN . "API de fallback definida para: " . TF::WHITE . $api);
                    $this->vpnChecker = new VPNChecker($this); // Reinicializa o checker com nova config
                } else {
                    $sender->sendMessage(TF::RED . "API inválida. Use proxycheck ou iphub.");
                }
                break;

            case "api":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn api <api> <enable|disable>");
                    $sender->sendMessage(TF::YELLOW . "APIs disponíveis: proxycheck, iphub");
                    return true;
                }

                $api = strtolower($args[1]);
                $action = strtolower($args[2]);

                $enabled = false;
                if ($action === "enable") {
                    $enabled = true;
                } else if ($action !== "disable") {
                    $sender->sendMessage(TF::RED . "Ação inválida. Use 'enable' ou 'disable'.");
                    return true;
                }

                if ($this->configManager->setApiEnabled($api, $enabled)) {
                    $status = $enabled ? "habilitada" : "desabilitada";
                    $sender->sendMessage(TF::GREEN . "API " . $api . " " . $status . " com sucesso!");
                    $this->vpnChecker = new VPNChecker($this); // Reinicializa o checker com nova config
                } else {
                    $sender->sendMessage(TF::RED . "API inválida. Use proxycheck ou iphub.");
                }
                break;

            case "setkey":
                if (count($args) < 3) {
                    $sender->sendMessage(TF::RED . "Uso: /antivpn setkey <api> <key>");
                    $sender->sendMessage(TF::YELLOW . "APIs disponíveis: proxycheck, iphub");
                    return true;
                }

                $api = strtolower($args[1]);
                $key = $args[2];

                if ($this->configManager->setApiKey($api, $key)) {
                    $sender->sendMessage(TF::GREEN . "Chave de API para " . $api . " definida com sucesso!");
                    $this->vpnChecker = new VPNChecker($this); // Reinicializa o checker com nova config
                } else {
                    $sender->sendMessage(TF::RED . "API inválida. Use proxycheck ou iphub.");
                }
                break;

            default:
                $sender->sendMessage(TF::RED . "Comando desconhecido. Use /antivpn para ajuda.");
        }

        return true;
    }

    /**
     * Executado quando o plugin é desativado.
     */
    public function onDisable() {
        $this->saveCache();
        $this->pendingChecks = [];
        $this->getLogger()->info(TF::RED . "AntiVPN desativado.");
    }
}