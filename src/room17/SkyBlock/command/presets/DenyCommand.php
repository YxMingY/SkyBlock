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

namespace room17\SkyBlock\command\presets;


use room17\SkyBlock\command\IsleCommand;
use room17\SkyBlock\session\Session;

class DenyCommand extends IsleCommand {
    
    /**
     * DenyCommand constructor.
     */
    public function __construct() {
        parent::__construct(["deny", "d"], "DENY_USAGE", "DENY_DESCRIPTION");
    }
    
    /**
     * @param Session $session
     * @param array $args
     */
    public function onCommand(Session $session, array $args): void {
        if(!isset($args[0]) and !$session->hasLastInvitation()) {
            $session->sendTranslatedMessage("DENY_USAGE");
            return;
        }
        $isleName = $args[0] ?? $session->getLastInvitation();
        $isle = $session->getInvitation($isleName);
        if($isle == null) {
            return;
        }
        $session->removeInvitation($isleName);
        $session->sendTranslatedMessage("INVITATION_REFUSED");
        $isle->broadcastTranslatedMessage("PLAYER_INVITATION_DENIED", [
            "name" => $session->getUsername()
        ]);
    }
    
}