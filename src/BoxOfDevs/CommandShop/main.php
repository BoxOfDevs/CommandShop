<?php
namespace BoxOfDevs\CommandShop;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use onebone\economyapi\EconomyAPI;

class main extends PluginBase implements Listener{
     
     const PREFIX = TF::YELLOW . "[CommandShop]" . TF::WHITE . " ";

     public function onEnable(){
          $this->getServer()->getPluginManager()->registerEvents($this,$this);
          $this->getLogger()->info("CommandShop by BoxOfDevs enabled!");
          $this->saveDefaultConfig();
          $this->config = new Config($this->getDataFolder(). "config.yml", Config::YAML);
          if($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") != null){
               $this->economyapi = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
               $this->getLogger()->notice("EconomyAPI successfully detected!");
          }else{
               $this->economyapi = null;
               $this->getLogger()->warning("Failed to load EconomyAPI!");
          }
     }

     public function onCommand(CommandSender $sender, Command $command, $label, array $args){
          switch($command->getName()){
               case "cshop":
                    $subcmd = strtolower(array_shift($args));
                    switch($subcmd){
                         case "add":
                              if(count($args) <= 2) return false;
                              $name = strtolower(array_shift($args));
                              $cmd = strtolower(implode(" ", $args));1
                              $cmds = $this->getConfig()->get("commands", []);
                              $cmds[$name]["cmd"] = $cmd;
                              $this->getConfig()->set("commands", $cmds);
                              $sender->sendMessage(self::PREFIX . TF::GREEN . "Command $name has been successfully added to the list of buyable commands!");
                              $sender->sendMessage(self::PREFIX . TF::AQUA . "Infos:");
                              $sender->sendMessage(self::PREFIX . "Name: " . $name);
                              $sender->sendMessage(self::PREFIX . "Command: " . $cmd);
                    }
               case "buycmd":
                    // do stuff, will cdoe when i have time
          }
          return true;
     }

     public function onSignTouch(PlayerInteractEvent $event){
          if($event->getBlock()->getId() != Block::SIGN_POST && $event->getBlock()->getId() != Block::WALL_SIGN) return;
          $p = $event->getPlayer();
          $sign = $p->getLevel()->getTile($event->getBlock());
          if(!($sign instanceof Sign)) return;
          // do stuff, will code when i have time
     }
}
