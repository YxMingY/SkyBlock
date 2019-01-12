<?php
/**
 *  _____    ____    ____   __  __  __  ______
 * |  __ \  / __ \  / __ \ |  \/  |/_ ||____  |
 * | |__) || |  | || |  | || \  / | | |    / /
 * |  _  / | |  | || |  | || |\/| | | |   / /
 * | | \ \ | |__| || |__| || |  | | | |  / /
 * |_|  \_\ \____/  \____/ |_|  |_| |_| /_/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace room17\SkyBlock\isle;


use pocketmine\level\Level;
use room17\SkyBlock\event\isle\IsleCreateEvent;
use room17\SkyBlock\event\isle\IsleDisbandEvent;
use room17\SkyBlock\event\isle\IsleOpenEvent;
use room17\SkyBlock\event\isle\IsleCloseEvent;
use room17\SkyBlock\generator\IsleGenerator;
use room17\SkyBlock\session\iSession;
use room17\SkyBlock\session\Session;
use room17\SkyBlock\SkyBlock;

class IsleManager {
    
    /** @var SkyBlock */
    private $plugin;
    
    /** @var Isle[] */
    private $isles = [];
    
    /**
     * IsleManager constructor.
     * @param SkyBlock $plugin
     */
    public function __construct(SkyBlock $plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * @return SkyBlock
     */
    public function getPlugin(): SkyBlock {
        return $this->plugin;
    }
    
    /**
     * @return Isle[]
     */
    public function getIsles(): array {
        return $this->isles;
    }
    
    /**
     * @param string $identifier
     * @return null|Isle
     */
    public function getIsle(string $identifier): ?Isle {
        return $this->isles[$identifier] ?? null;
    }
    
    /**
     * @param Session $session
     * @param string $type
     */
    public function createIsleFor(Session $session, string $type): void {
        $identifier = SkyBlock::generateUniqueId();

        $generatorManager = $this->plugin->getGeneratorManager();
        if($generatorManager->isGenerator($type)) {
            $generator = $generatorManager->getGenerator($type);
        } else {
            $generator = $generatorManager->getGenerator("Basic");
        }
    
        $server = $this->plugin->getServer();
        $server->generateLevel($identifier, null, $generator);
        $server->loadLevel($identifier);
        $level = $server->getLevelByName($identifier);
        /** @var IsleGenerator $generator */
        $level->setSpawnLocation($generator::getWorldSpawn());
        
        $this->openIsle($identifier, [$session->getOffline()], true, $type, $level, 0);
        $session->setIsle($isle = $this->isles[$identifier]);
        $session->setRank(iSession::RANK_FOUNDER);
        $session->save();
        $isle->save();
        $session->setLastIslandCreationTime(microtime(true));
        $server->getPluginManager()->callEvent(new IsleCreateEvent($isle));
    }
    
    /**
     * @param Isle $isle
     */
    public function disbandIsle(Isle $isle): void {
        foreach($isle->getLevel()->getPlayers() as $player) {
            $player->teleport($player->getServer()->getDefaultLevel()->getSpawnLocation());
        }
        foreach($isle->getMembers() as $offlineMember) {
            $onlineSession = $offlineMember->getSession();
            if($onlineSession != null) {
                $onlineSession->setIsle(null);
                $onlineSession->setRank(Session::RANK_DEFAULT);
                $onlineSession->save();
                $onlineSession->sendTranslatedMessage("ISLE_DISBANDED");
            } else {
                $offlineMember->setIsleId(null);
                $offlineMember->setRank(Session::RANK_DEFAULT);
                $offlineMember->save();
            }
        }
        $isle->setMembers([]);
        $isle->save();
        $this->closeIsle($isle);
        $this->plugin->getServer()->getPluginManager()->callEvent(new IsleDisbandEvent($isle));
    }
    
    /**
     * @param string $identifier
     * @param array $members
     * @param bool $locked
     * @param string $type
     * @param Level $level
     * @param int $blocksBuilt
     */
    public function openIsle(string $identifier, array $members, bool $locked, string $type, Level $level, int $blocksBuilt): void {
        $this->isles[$identifier] = new Isle($this, $identifier, $members, $locked, $type, $level, $blocksBuilt);
        $this->plugin->getServer()->getPluginManager()->callEvent(new IsleOpenEvent($this->isles[$identifier]));
    }
    
    /**
     * @param Isle $isle
     */
    public function closeIsle(Isle $isle): void {
        $isle->save();
        $server = $this->plugin->getServer();
        $server->getPluginManager()->callEvent(new IsleCloseEvent($isle));
        $server->unloadLevel($isle->getLevel());
        unset($this->isles[$isle->getIdentifier()]);
    }
    
}