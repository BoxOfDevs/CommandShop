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
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\Inventoy;
use pocketmine\Item;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use onebone\economyapi\EconomyAPI;

class main extends PluginBase implements Listener{
     
     const PREFIX = TF::YELLOW . "[CommandShop]" . TF::WHITE . " ";
     const ERROR = TF::YELLOW . "[CommandShop]" . TF::RED . " [ERROR]" . TF::WHITE . " ";
     public $signsetters;

     public function onEnable(){
          $this->getServer()->getPluginManager()->registerEvents($this,$this);
          $this->getLogger()->info("CommandShop by BoxOfDevs enabled!");
          $this->saveDefaultConfig();
          $this->config = new Config($this->getDataFolder(). "config.yml", Config::YAML);
          if($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") != null){
               $this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
               $this->getLogger()->notice("EconomyAPI successfully detected!");
          }else{
               $this->economy = null;
               $this->getLogger()->warning("Failed to load EconomyAPI! Only item-pay mode is avaiable.");
          }
     }

     public function getMessage(string $msg, array $replacers = []): string{
          $msg = $this->getConfig()->get($msg);
          $msg = str_ireplace(array_keys($replacers), array_values($replacers), $msg);
          $values = [
               "{prefix}" => self::PREFIX,
               "{error}" => self::ERROR,
               "{line}" => "\n"
          ];
          $msg = str_ireplace(array_keys($values), array_values($values), $msg);
          return $msg;
     }
     
     public function getItem(string $name): Item{
          if(strpos($name, ":") != false){
               $arr = explode(":", $name);
               $name = $arr[0];
               $dmg = (integer) $arr[1];
               if(isset($arr[2])){
                    $count = (integer) $arr[2];
               }else{
                    $count = 1;
               }
          }else{
               $dmg = 0;
               $count = 1;
          }
          if(!is_numeric($name)){
               $item = Item::fromString($name);
          }else{
               $item = Item::get($name);
          }
          $item->setDamage($dmg);
          $item->setCount($count);
          return $item;
     }

     public function executeCommands(array $cmds, Player $p){
          $cmds = str_replace("{player}", $p->getName(), $cmds);
          $cmds = str_replace("{level}", $p->getLevel()->getName(), $cmds);
          $cmds = str_replace("{x}", round($p->x, 0), $cmds);
          $cmds = str_replace("{y}", round($p->x, 0), $cmds);
          $cmds = str_replace("{z}", round($p->x, 0), $cmds);
          foreach ($cmds as $cmd) {
               $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd);
          }
          return;
     }

     public function buyCmd(string $cmd, Player $p): bool{
          if($p instanceof Player){
               $name = $p->getName();
               $cmd = strtolower($cmd);
               $cmds = $this->getConfig()->get("commands", []);
               if(isset($cmds[$cmd])){
                    $cmda = $cmds[$cmd];
                    $commands = $cmda["cmds"];
                    $price = $cmda["price"];
                    if($price["paytype"] === "economyapi"){
                         if($this->economy != null){
                              $amount = $price["amount"];
                              if($this->economy->reduceMoney($p, $amount) === 1){
                                   $this->executeCommands($commands, $p);
                                   $replacers = ["{cmd}" => $cmd, "{amount}" => $amount, "{unit}" => $this->economy->getMonetaryUnit()];
                                   $p->sendMessage($this->getMessage("buy.money.success", $replacers));
                                   $this->getLogger()->debug("The player $name has bought the command $cmd for $amount $unit via EconomyAPI.");
                                   return true;
                              }else{
                                   $replacers = ["{amount}" => $amount, "{unit}" => $this->economy->getMonetaryUnit()];
                                   $p->sendMessage($this->getMessage("buy.money.miss", $replacers));
                              }
                         }else{
                              $msg = self::ERROR . "Command couldn't be bought because EconomyAPI isn't loaded.";
                              $this->getLogger()->warning($msg);
                              $p->sendMessage($msg . $this->getMessage("buy.contactadmin"));
                         }
                    }elseif($price["paytype"] === "item"){
                         $item = $price["item"];
                         if(!$p->getInventory()->contains(getItem($item))){
                              $replacers = ["{item}" => $item->getName(), "{amount}" => $item->getCount()];
                              $p->sendMessage($this->getMessage("buy.item.miss", $replacers));
                         }else{
                              $p->getInventory()->remove($item);
                              $this->executeCommands($commands, $p);
                              $replacers = ["{cmd}" => $cmd, "{item}" => $item->getName()];
                              $p->sendMessage($this->getMessage("buy.item.success", $replacers));
                              $this->getLogger()->debug("The player $name has bought the command $cmd with the item $item.");
                              return true;
                         }
                    }
               }else{
                    $replacers = ["{cmd}" => $cmd];
                    $p->sendMessage($this->getMessage("command.notfound", $replacers) . $this->getMessage("buy.contactadmin"));
               }
          }else{
               $this->getLogger()->warning(self::ERROR . "The following error happened while trying to execute the function buyCmd(): The variable \$p wasn't a Player Object. If you think you didn't cause this problem, please open an issue on the GitHub repository: https://github.com/BoxOfDevs/CommandShop");
          }
          return false;
     }

     public function onCommand(CommandSender $sender, Command $command, $label, array $args){
          switch($command->getName()){
               case "cshop":
                    $subcmd = strtolower(array_shift($args));
                    switch($subcmd){
                         case "add":
                              if(count($args) < 2) return false;
                              $name = strtolower(array_shift($args));
                              $cmd = strtolower(implode(" ", $args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(!isset($cmds[$name])){
                                   $cmds[$name]["cmds"] = [$cmd];
                                   $cmds[$name]["buycmd"] = true;
                                   $this->getConfig()->set("commands", $cmds);
                                   $this->getConfig()->save();
                                   $sender->sendMessage(self::PREFIX . TF::GREEN . "Command $name has been successfully added to the list of buyable commands!");
                                   $sender->sendMessage(self::PREFIX . TF::AQUA . "Infos:");
                                   $sender->sendMessage(self::PREFIX . "Name: " . $name);
                                   $sender->sendMessage(self::PREFIX . "Command: " . $cmd);
                                   $sender->sendMessage(self::PREFIX . "Please set a price now using " . TF::AQUA . "/cshop setprice" . TF::WHITE . ". Or add more commands to be executed using " . TF::AQUA . "/cshop addcmd" . TF::WHITE . ".");
                              }else{
                                   $sender->sendMessage(self::ERROR . "That command does already exist!");
                              }
                         case "remove":
                              if(count($args) < 1) return false;
                              $name = strtolower(array_shift($args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(isset($cmds[$name])){
                                   unset($cmds[$name]);
                                   $this->getConfig()->set("commands", $cmds);
                                   $this->getConfig()->save();
                                   $sender->sendMessage(self::PREFIX . TF::GREEN . "Command $name has been successfully removed from the list of buyable commands!");
                              }else{
                                   $replacers = ["{cmd}" => $name];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                         case "setprice":
                              if(count($args) < 3) return false;
                              $cmd = strtolower(array_shift($args));
                              $type = strtolower(array_shift($args));
                              if($type === "money"){
                                   if($this->economy != null){
                                        $amount = array_shift($args);
                                        if(!is_numeric($amount)) return false;
                                        $cmds = $this->getConfig()->get("commands", []);
                                        if(isset($cmds[$cmd])){
                                             $cmds[$cmd]["price"]["paytype"] = "money";
                                             $cmds[$cmd]["price"]["amount"] = $amount;
                                             $this->getConfig()->set("commands", $cmds);
                                             $this->getConfig()->save();
                                             $unit = $this->economy->getMonetaryUnit();
                                             $sender->sendMessage(self::PREFIX . TF::GREEN . "The price of $amount $unit has successfully been set to the command $cmd!");
                                        }else{
                                             $replacers = ["{cmd}" => $cmd];
                                             $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                                        }
                                   }else{
                                        $sender->sendMessage(self::ERROR . "Please install EconomyAPI by onebone in order to be able to pay for commands with money!");
                                   }
                              }elseif($type === "item"){
                                   $item = array_shift($args);
                                   $cmds = $this->getConfig()->get("commands", []);
                                   if(isset($cmds[$cmd])){
                                        $cmds[$cmd]["price"]["paytype"] = "item";
                                        $cmds[$cmd]["price"]["item"] = $item;
                                        $this->getConfig()->set("commands", $cmds);
                                        $this->getConfig()->save();
                                        $sender->sendMessage(self::PREFIX . TF::GREEN . "The item-price of $item has successfully been set to the command $cmd!");
                                   }else{
                                        $replacers = ["{cmd}" => $cmd];
                                        $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                                   }
                              }else{
                                   return false;
                              }
                         case "sign":
                              if(count($args) < 1) return false;
                              $cmd = strtolower(array_shift($args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(isset($cmds[$cmd])){
                                   $this->signsetters[$sender->getName()] = $cmd;
                                   $sender->sendMessage(self::PREFIX . "Please tap a sign now!");
                              }else{
                                   $replacers = ["{cmd}" => $cmd];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                         case "buycmd":
                              if(count($args) < 2) return false;
                              $cmds = $this->getConfig()->get("commands", []);
                              $cmd = strtolower(array_shift($args));
                              $bool = strtolower(array_shift($args));
                              if(isset($cmds[$cmd])){
                                   if($bool === "true"){
                                        $cmds[$cmd]["buycmd"] = true;
                                        $this->getConfig()->get("commands", $cmds);
                                        $this->getConfig()->save();
                                        $sender->sendMessage(self::PREFIX . "/buycmd has been successfully enabled for $cmd");
                                   }elseif($bool === "false"){
                                        $cmds[$cmd]["buycmd"] = false;
                                        $this->getConfig()->get("commands", $cmds);
                                        $this->getConfig()->save();
                                        $sender->sendMessage(self::PREFIX . "/buycmd has been successfully disabled for $cmd");
                                   }else{
                                        return false;
                                   }
                              }else{
                                   $replacers = ["{cmd}" => $cmd];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                         case "list":
                              $cmds = $this->getConfig()->get("commands", []);
                              if($cmds != []){
                                   $msg = implode(", ", array_keys($cmds));
                                   $sender->sendMessage(self::PREFIX . "List of all buyable commands:");
                                   $sender->sendMessage($msg);
                              }else{
                                   $sender->sendMessage(self::ERROR . "You haven't created any buyable commands yet, please create one using " . TF::AQUA . "/cshop add" . TF::WHITE . ".");
                              }
                         case "info":
                              if(count($args) < 1) return false;
                              $cmd = strtolower(array_shift($args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(isset($cmds[$cmd])){
                                   $cmd = $cmds[$cmd];
                                   $sender->sendMessage(self::PREFIX . "Information for the command $cmd:");
                                   $commands = "Commands: \n" . implode("\n", $cmd["cmds"]);
                                   $sender->sendMessage($commands);
                                   if(isset($cmd["price"])){
                                        $paytype = $cmd["price"]["paytype"];
                                        if($paytype === "money"){
                                             $amount = $cmd["price"]["amount"];
                                             $sender->sendMessage("Paytype: Money (EconomyAPI)");
                                             $sender->sendMessage("Amount: $amount");
                                        }elseif($paytype === "item"){
                                             $item = $cmd["price"]["item"];
                                             $item = $this->getItem($item);
                                             $sender->sendMessage("Paytype: Items");
                                             $sender->sendMessage("Item: " . $item->getName() . "Damage: " . $item->getDamage() . "Amount: " . $item->getCount());
                                        }else{
                                             $sender->sendMessage(self::ERROR, "Invalid paytype, please use " . TF::AQUA . "/cshop setprice" . TF::WHITE . " to set the price for this command!");
                                        }
                                   }else{
                                        $sender->sendMessage(self::ERROR, "No price has been set for this command, please use " . TF::AQUA . "/cshop setprice" . TF::WHITE . " to set the price for this command!");
                                   }
                              }else{
                                   $replacers = ["{cmd}" => $cmd];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                         case "help":
                              $sender->sendMessage("Please visit the wiki for this plugin here: https://github.com/BoxOfDevs/CommandShop/wiki");
                         default:
                              return false;
                    }
               case "buycmd":
                    if(count($args) < 1) return false;
                    $cmd = strtolower(array_shift($args));
                    $cmds = $this->getConfig()->get("commands", []);
                    if(isset($cmds[$cmd])){
                         if($cmds[$cmd] === true){
                              $this->buyCmd($cmd, $sender);
                         }else{
                              $replacers = ["{cmd}" => $cmd];
                              $sender->sendMessage($this->getMessage("buycmd.disabled", $replacers));
                         }
                    }else{
                         $replacers = ["{cmd}" => $cmd];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                    }
          }
          return true;
     }

     public function onSignTouch(PlayerInteractEvent $event){
          if($event->getBlock()->getId() != Block::SIGN_POST && $event->getBlock()->getId() != Block::WALL_SIGN) return;
          $p = $event->getPlayer();
          $sign = $p->getLevel()->getTile($event->getBlock());
          $level = $p->getLevel()->getName();
          if(!($sign instanceof Sign)) return;
          $x = $sign->getBlock()->getX();
          $y = $sign->getBlock()->getY();
          $z = $sign->getBlock()->getZ();
          $signs = $this->getConfig()->get("signs", []);
          if(isset($this->signsetters[$p->getName()])){
               $index = count($signs + 1);
               $signs[$index]["x"] = $x;
               $signs[$index]["y"] = $y;
               $signs[$index]["z"] = $z;
               $signs[$index]["level"] = $level;
               $signs[$index]["cmd"] = $this->signsetters[$p->getName()];
               $this->getConfig()->set("signs", $signs);
               $this->getConfig()->save();
               $p->sendMessage(self::PREFIX . TF::GREEN . "Sign has been successfully created!");
               return;
          }else{
               foreach($signs as $s){
                    if($s["x"] === $x && $s["y"] === $y && $s["z"] === $z && $s["level"] === $level){
                         if($p->hasPermission("cshop.buy.sign")){
                              $this->buyCmd($s["cmd"], $p);
                         }else{
                              $p->sendMessage($this->getMessage("sign.noperm"));
                         }
                         return;
                    }
               }
          }
          return;
     }
}
