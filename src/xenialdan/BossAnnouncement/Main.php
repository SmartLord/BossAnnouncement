<?php

namespace xenialdan\BossAnnouncement;

use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use xenialdan\BossBarAPI\API;

class Main extends PluginBase implements Listener{
	public $entityRuntimeId = null, $headBar = '', $cmessages = [], $changeSpeed = 0, $i = 0;

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->headBar = $this->getConfig()->get('head-message', '');
		$this->cmessages = $this->getConfig()->get('changing-messages', []);
		$this->changeSpeed = $this->getConfig()->get('change-speed', 0);
		if ($this->changeSpeed > 0) $this->getServer()->getScheduler()->scheduleRepeatingTask(new SendTask($this), 20 * $this->changeSpeed);
	}

	public function onJoin(PlayerJoinEvent $ev){
		if (in_array($ev->getPlayer()->getLevel(), $this->getWorlds())){
			if ($this->entityRuntimeId === null){
				$this->entityRuntimeId = API::addBossBar($ev->getPlayer());
				$this->getServer()->getLogger()->debug($this->entityRuntimeId === NULL ? 'Couldn\'t add BossAnnouncement' : 'Successfully added BossAnnouncement with EID: ' . $this->entityRuntimeId);
			} else{
				API::sendBossBarToPlayer($ev->getPlayer(), $this->entityRuntimeId, $this->getText($ev->getPlayer()));
				$this->getServer()->getLogger()->debug('Sendt BossAnnouncement with existing EID: ' . $this->entityRuntimeId);
			}
		}
	}

	public function onLevelChange(EntityLevelChangeEvent $ev){
		if ($ev->isCancelled() || !$ev->getEntity() instanceof Player) return;
		if (in_array($ev->getTarget(), $this->getWorlds())){
			if ($this->entityRuntimeId === null){
				$this->entityRuntimeId = API::addBossBar($ev->getEntity());
				$this->getServer()->getLogger()->debug($this->entityRuntimeId === NULL ? 'Couldn\'t add BossAnnouncement' : 'Successfully added BossAnnouncement with EID: ' . $this->entityRuntimeId);
			} else{
				API::removeBossBar([$ev->getEntity()], $this->entityRuntimeId);
				API::sendBossBarToPlayer($ev->getEntity(), $this->entityRuntimeId, $this->getText($ev->getEntity()));
				$this->getServer()->getLogger()->debug('Sendt BossAnnouncement with existing EID: ' . $this->entityRuntimeId);
			}
		} else{
			API::removeBossBar([$ev->getEntity()], $this->entityRuntimeId);
		}
	}


	public function sendBossBar(){
		if ($this->entityRuntimeId === null) return;
		$this->i++;
		$worlds = $this->getWorlds();
		foreach ($worlds as $world){
			foreach ($world->getPlayers() as $player){
				API::setTitle($this->getText($player), $this->entityRuntimeId, [$player]);
			}
		}
	}

	/**
	 * Generates the output
	 *
	 * @param Player $player
	 * @return string
	 */
	public function getText(Player $player){
		$text = '';
		if (!empty($this->headBar)) $text .= $this->formatText($player, $this->headBar) . "\n" . "\n" . TextFormat::RESET;
		$currentMSG = $this->cmessages[$this->i % count($this->cmessages)];
		if (strpos($currentMSG, '%') > -1){
			$percentage = substr($currentMSG, 1, strpos($currentMSG, '%') - 1);
			if (is_numeric($percentage)) API::setPercentage(intval($percentage) + 0.5, $this->entityRuntimeId);
			$currentMSG = substr($currentMSG, strpos($currentMSG, '%') + 2);
		}
		$text .= $this->formatText($player, $currentMSG);
		return mb_convert_encoding($text, 'UTF-8');
	}

	/**
	 * Formats the string
	 *
	 * @param Player $player
	 * @param string $text
	 * @return string
	 */
	public function formatText(Player $player, string $text){
		return str_replace(array(
			"&",
			"%TPS%",
			"%LOAD%",
			"%UPTIME",
			"%MOTD%",
			"%ONLINE%",
			"%MAX_ONLINE%",
			"%SERVER_IP%",
			"%IP%",
			"%PING%",
			"%NAME%",
			"%KILLS%",
			"%DEATHS%",
			"%GROUP%",
			"%MONEY%",
			"%LEVEL_NAME%",
			"%LEVEL_PLAYERS%",
			"%ITEM_ID%",
			"%ITEM_META%",
			"%X%",
			"%Y%",
			"%Z%",
			"%HOUR%",
			"%MINUTE%",
			"%SECOND%"
		), array(
			"\xc2\xa7",
			$this->getServer()->getTicksPerSecond(),
			$this->getServer()->getTickUsage(),
			$this->getUptime(),
			$this->getServer()->getMotd(),
			count($this->getServer()->getOnlinePlayers()),
			$this->getServer()->getMaxPlayers(),
			$this->getServer()->getIp(),
			$player->getAddress(),
			$player->getPing(),
			$player->getName(),
			if($this->killchat) $this->killchat->getKills($player->getName()) else "NoPlugin",
			if($this->killchat) $this->killchat->getDeaths($player->getName()) else "NoPlugin",
			$this->getDeaths($player),
			if($this->pureperms) $this->pureperms->getUserDataMgr()->getGroup($player)->getName() else "NoPlugin",
			if($this->economyapi) $this->economyapi->myMoney($player) else "NoPlugin",
			$player->getLevel()->getName(),
			count($player->getLevel()->getPlayers()),
			if($player->getInventory !== null) $player->getInventory()->getItemInHand()->getId() else 0,
			if($player->getInventory !== null) $player->getInventory()->getItemInHand()->getDamage() else 0,
			(int)$player->getX(),
			(int)$player->getY(),
			(int)$player->getZ(),
			date('H'),
			date("i"),
			date("s")
		), $text);
	}

	/** @return Level[] $worlds */
	private function getWorlds(){
		$mode = $this->getConfig()->get("mode", 0);
		$worldnames = $this->getConfig()->get("worlds", []);
		/** @var Level[] $worlds */
		$worlds = [];
		switch ($mode){
			case 0://Every
				$worlds = $this->getServer()->getLevels();
				break;
			case 1://only
				foreach ($worldnames as $name){
					if (!is_null($level = $this->getServer()->getLevelByName($name))) $worlds[] = $level;
					else $this->getLogger()->warning("Config error! World " . $name . " not found!");
				}
				break;
			case 2://not in
				$worlds = $this->getServer()->getLevels();
				foreach ($worlds as $world){
					if (!in_array(strtolower($world->getName()), $worldnames)){
						$worlds[] = $world;
					}
				}
				break;
		}
		return $worlds;
	}
}
