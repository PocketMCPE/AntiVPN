<?php

namespace PocketDev\AntiVPN\Events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat as TF;

use PocketDev\AntiVPN\Main;
use PocketDev\AntiVPN\Tasks\LoginCheckTask;

class PlayerJoinListener implements Listener {

    /** @var Main */
    private $plugin;

    /**
     * Construtor
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Handler para evento de pré-login
     * @param PlayerPreLoginEvent $event
     * @priority HIGHEST
     */
    public function onPlayerPreLogin(PlayerPreLoginEvent $event) {
        $player = $event->getPlayer();
        $ip = $player->getAddress();
        $playerName = $player->getName();

        if ($player->hasPermission("antivpn.bypass")) {
            $this->logAction($playerName, $ip, "VPN bypass permitido por permissão");
            return;
        }

        if ($this->plugin->getConfigManager()->isWhitelisted($ip) ||
            $this->plugin->getConfigManager()->isNicknameWhitelisted($playerName)) {
            $this->logAction($playerName, $ip, "IP ou nickname na whitelist");
            return;
        }

        if ($this->plugin->isVPN($ip) === true) {
            $event->setCancelled(true);
            $event->setKickMessage($this->plugin->getConfigManager()->getKickMessage());
            $this->logAction($playerName, $ip, "Kickado - VPN detectada (cache)");
            return;
        }

        $this->plugin->getServer()->getScheduler()->scheduleAsyncTask(
            new LoginCheckTask(
                $ip,
                $playerName,
                $this->plugin->getConfigManager()->getApiConfig(),
                $this->plugin->getConfigManager()->getKickMessage()
            )
        );
    }

    /**
     * Handler para evento de join (após login bem-sucedido)
     * @param PlayerJoinEvent $event
     * @priority MONITOR
     */
    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $ip = $player->getAddress();
        $playerName = $player->getName();

        if ($player->hasPermission("antivpn.bypass")) {
            return;
        }

        $this->plugin->checkVPNAsync($ip, function($ip, $isVPN, $details) use ($playerName) {
            if ($isVPN === true) {
                $message = TF::RED . "Alerta: " . TF::YELLOW . "$playerName" . TF::RED . " pode estar usando VPN.";

                foreach ($this->plugin->getServer()->getOnlinePlayers() as $admin) {
                    if ($admin->hasPermission("antivpn.admin")) {
                        $admin->sendMessage($message);
                    }
                }

                $this->logAction($playerName, $ip, "Possível VPN detectada após login");
            }
        });
    }

    /**
     * Log de ações para depuração
     * @param string $playerName
     * @param string $ip
     * @param string $action
     */
    private function logAction($playerName, $ip, $action) {
        if ($this->plugin->getConfigManager()->isLoggingEnabled()) {
            $this->plugin->getLogger()->info("[$playerName/$ip] $action");
        }
    }
}