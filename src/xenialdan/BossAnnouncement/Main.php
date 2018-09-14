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
	
	public $entityRuntimeId = null, $headBar = "", $cmessages = [], $changeSpeed = 0, $i = 0, $killchat, $economyapi, $pureperms;

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->headBar = $this->getConfig()->get("head-message", "");
		$this->cmessages = $this->getConfig()->get("changing-messages", []);
		$this->changeSpeed = $this->getConfig()->get("change-speed", 0);
		if($this->killchat = $this->getServer()->getPluginManager()->getPlugin("KillChat")) $this->getLogger()->notice("KillChat found");
		if($this->economyapi = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI")) $this->getLogger()->notice("EconomyAPI found");
		if($this->pureperms = $this->getServer()->getPluginManager()->getPlugin("PurePerms")) $this->getLogger()->notice("PurePerms found");
		if($this->changeSpeed > 0) $this->getScheduler()->scheduleRepeatingTask(new SendTask($this), 20 * $this->changeSpeed);
		$this->getLogger()->info("Enabled");
	}

	public function onJoin(PlayerJoinEvent $ev){
		if (in_array($ev->getPlayer()->getLevel(), $this->getWorlds())){
			if ($this->entityRuntimeId === null){
				$this->entityRuntimeId = API::addBossBar([$ev->getPlayer()]);
				$this->getServer()->getLogger()->debug($this->entityRuntimeId === NULL ? "Couldn\"t add BossAnnouncement" : "Successfully added BossAnnouncement with EID: " . $this->entityRuntimeId);
			} else{
				API::sendBossBarToPlayer($ev->getPlayer(), $this->entityRuntimeId, $this->getText($ev->getPlayer()));
				$this->getServer()->getLogger()->debug("Sendt BossAnnouncement with existing EID: " . $this->entityRuntimeId);
			}
		}
	}

	public function onLevelChange(EntityLevelChangeEvent $ev){
		if ($ev->isCancelled() || !$ev->getEntity() instanceof Player) return;
		if (in_array($ev->getTarget(), $this->getWorlds())){
			if ($this->entityRuntimeId === null){
				$this->entityRuntimeId = API::addBossBar($ev->getEntity());
				$this->getServer()->getLogger()->debug($this->entityRuntimeId === NULL ? "Couldn\"t add BossAnnouncement" : "Successfully added BossAnnouncement with EID: " . $this->entityRuntimeId);
			} else{
				API::removeBossBar([$ev->getEntity()], $this->entityRuntimeId);
				API::sendBossBarToPlayer($ev->getEntity(), $this->entityRuntimeId, $this->getText($ev->getEntity()));
				$this->getServer()->getLogger()->debug("Sendt BossAnnouncement with existing EID: " . $this->entityRuntimeId);
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
		$text = "";
		if (!empty($this->headBar)) $text .= $this->formatText($player, $this->headBar) . "\n" . "\n" . TextFormat::RESET;
		$currentMSG = $this->cmessages[$this->i % count($this->cmessages)];
		if (strpos($currentMSG, "%") > -1){
			$percentage = substr($currentMSG, 1, strpos($currentMSG, "%") - 1);
			if (is_numeric($percentage)) API::setPercentage(intval($percentage) + 0.5, $this->entityRuntimeId);
			$currentMSG = substr($currentMSG, strpos($currentMSG, "%") + 2);
		}
		$text .= $this->formatText($player, $currentMSG);
		return mb_convert_encoding($text, "UTF-8");
	}
	
	public function getUptime(){
        $time = microtime(true) - \pocketmine\START_TIME;
        $seconds = floor($time % 60);
        $minutes = null;
        $hours = null;
        $days = null;
        if ($time >= 60) {
            $minutes = floor(($time % 3600) / 60);
            if ($time >= 3600) {
                $hours = floor(($time % (3600 * 24)) / 3600);
                if ($time >= 3600 * 24) {
                    $days = floor($time / (3600 * 24));
                }
            }
        }
        $uptime = ($minutes !== null ?
                ($hours !== null ?
                    ($days !== null ?
                        "$days days "
                        : "") . "$hours hours "
                    : "") . "$minutes minutes "
                : "") . "$seconds seconds";
        return $uptime;
    }


	/**
	 * Formats the string
	 *
	 * @param Player $player
	 * @param string $text
	 * @return string
	 */
	public function formatText(Player $player, string $text){
		if($this->killchat){
			$kills = $this->killchat->getKills($player->getName());
			$deaths = $this->killchat->getDeaths($player->getName());
		}else{
			$kills = "NoPlugin";
			$deaths = "NoPlugin";
		}
		if($this->pureperms){
			$group = $this->pureperms->getUserDataMgr()->getGroup($player)->getName();
		}else{
			$group = "NoPlugin";
		}
		if($this->economyapi){
			$money = $this->economyapi->myMoney($player);
		}else{
			$money = "NoPlugin";
		}
		if(($inventory = $player->getInventory()) !== null){
			$item = $inventory->getItemInHand();
			$id = $item->getId();
			$meta = $item->getDamage();
		}else{
			$id = $meta = 0;
		}
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
			$kills,
			$deaths,
			$group,
			$money,
			$player->getLevel()->getName(),
			count($player->getLevel()->getPlayers()),
			$id,
			$meta,
			(int)$player->getX(),
			(int)$player->getY(),
			(int)$player->getZ(),
			date("H"),
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
			case 0:
				$worlds = $this->getServer()->getLevels();
				break;
			case 1:
				foreach ($worldnames as $name){
					if (!is_null($level = $this->getServer()->getLevelByName($name))) $worlds[] = $level;
					else $this->getLogger()->warning("Config error! World " . $name . " not found!");
				}
				break;
			case 2:
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
