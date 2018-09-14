<?php

namespace xenialdan\BossAnnouncement;

use pocketmine\scheduler\Task;

class SendTask extends Task{
	
	private $plugin;
	
	public function __construct(Main $plugin){
		$this->plugin = $plugin;
	}
	
	public function onRun(int $currentTick){
		$this->plugin->sendBossBar();
	}
}
