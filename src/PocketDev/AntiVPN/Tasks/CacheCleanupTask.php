<?php

namespace PocketDev\AntiVPN\Tasks;

use pocketmine\scheduler\PluginTask;
use PocketDev\AntiVPN\Main;

class CacheCleanupTask extends PluginTask {

    /**
     * Construtor
     * @param Main $plugin
     */
    public function __construct(Main $plugin) {
        parent::__construct($plugin);
    }

    /**
     * Executa a tarefa de limpeza do cache
     * @param int $currentTick
     */
    public function onRun($currentTick) {
        /** @var Main */
        $plugin = $this->getOwner();

        $plugin->cleanupCache();
    }
}