<?php

namespace BoxOfDevs\CommandShop;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

class CommandShop extends PluginBase implements Listener{
     
     const PREFIX = TF::YELLOW . "[CommandShop]" . TF::WHITE . " ";
     const ERROR = TF::YELLOW . "[CommandShop]" . TF::RED . " [ERROR]" . TF::WHITE . " ";

	/**
	 * @var EconomyAPI
	 */
     private $economy;

     private $usages = [
          "add" => "<name> <command>",
          "remove" => "<name>",
          "setprice" => "<name> <money|item> <money-amount|item-id>",
          "sign" => "<name>",
          "buycmd" => "<name> <false|true>",
          "addcmd" => "<name> <command>",
          "list" => "",
          "info" => "<name>",
          "help" => ""
     ];

	public $signsetters = [];
	public $confirms = [];

     /**
      * When the plugin enables
      */
     public function onEnable(){
          $this->getServer()->getPluginManager()->registerEvents(new CShopListener($this),$this);
          $this->saveDefaultConfig();
          if($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") != null){
               $this->economy = EconomyAPI::getInstance();
               $this->getLogger()->notice("EconomyAPI successfully detected!");
          }else{
               $this->economy = null;
               $this->getLogger()->notice("Failed to load EconomyAPI! Only item-pay mode is avaiable.");
          }
     }

     /**
      * Translates a message from config
      *
      * @param string $msg
      * @param array  $replacers
      * @return string
      */
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

     /**
      * Send the usage of a Command to a Player
      *
      * @param string $cmd
      * @param        $p
      * @return string
      */
     public function sendUsage(string $cmd, CommandSender $p): string{
          $p->sendMessage(self::ERROR . "Usage: /cshop $cmd " . $this->usages[$cmd]);
          return true;
     }

     /**
      * Get an item from a string (used to parse count)
      *
      * @param string $name
      * @return Item
      */
     public function getItem(string $name): Item{
          if(strpos($name, ":") != false){
               $arr = explode(":", $name);
               $name = $arr[0];
               $dmg = (int) $arr[1];
               if(isset($arr[2])){
                    $count = (int) $arr[2];
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

     /**
      * Execute command as console
      *
      * @param array  $cmds
      * @param Player $p
      */
     public function executeCommands(array $cmds, Player $p){
          $cmds = str_replace("{player}", '"'.$p->getName().'"', $cmds);
          $cmds = str_replace("{level}", $p->getLevel()->getName(), $cmds);
          $cmds = str_replace("{x}", round($p->x, 0), $cmds);
          $cmds = str_replace("{y}", round($p->y, 0), $cmds);
          $cmds = str_replace("{z}", round($p->z, 0), $cmds);
          foreach ($cmds as $cmd) {
               $this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd);
          }
          return;
     }

     /**
      * Removes an item from a player's invenotry (solving count issues with soft)
      *
      * @param Item                                $item
      * @param \pocketmine\inventory\BaseInventory $inventory
      */
     public function remove(Item $item, \pocketmine\inventory\BaseInventory $inventory){
        $checkDamage = !$item->hasAnyDamageValue();
        $checkTags = $item->hasCompoundTag();
        $checkCount = $item->getCount() === null ? false : true;
        $count = $item->getCount();

        foreach($inventory->getContents() as $index => $i){
            if($item->equals($i, $checkDamage, $checkTags)){
                if($checkCount && $i->getCount() > $item->getCount()) {
                    $i->setCount($i->getCount() - $count);
                    $inventory->setItem($index, $i);
                    return;
                } elseif($checkCount && $i->getCount() < $item->getCount()) {
                    $count -= $i->getCount();
                    $inventory->clear($index);
                } else {
                    $inventory->clear($index);
                }
            }
        }
    }

     /**
      * Buy a command
      *
      * @param string $cmd
      * @param Player $p
      * @return bool
      */
     public function buyCmd(string $cmd, Player $p): bool{
          if($p instanceof Player){
               $name = $p->getName();
               $cmd = strtolower($cmd);
               $cmds = $this->getConfig()->get("commands", []);
               if(isset($cmds[$cmd])){
                    $cmda = $cmds[$cmd];
                    $commands = $cmda["cmds"];
                    $price = $cmda["price"];
                    if($price["paytype"] === "money"){
                         if($this->economy != null){
                              $amount = $price["amount"];
                              if($this->economy->reduceMoney($p, $amount) === 1){
                                   $this->executeCommands($commands, $p);
                                   $unit =  $this->economy->getMonetaryUnit();
                                   $replacers = ["{cmd}" => $cmd, "{amount}" => $amount, "{unit}" => $unit];
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
                         $item = $this->getItem($price["item"]);
                         if(!$p->getInventory()->contains($item)){
                              $replacers = ["{item}" => $item->getName(), "{amount}" => $item->getCount()];
                              $p->sendMessage($this->getMessage("buy.item.miss", $replacers));
                         }else{
                              $this->remove($item, $p->getInventory());
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

     /**
      * Called when one of the defined commands of the plugin has been called
      *
      * @param CommandSender $sender
      * @param Command       $command
      * @param string        $label
      * @param array         $args
      * @return bool
      */
     public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
          switch($command->getName()){
               case "cshop":
                    $subcmd = strtolower(array_shift($args));
                    switch($subcmd){
                         case "add":
                              if(count($args) < 2){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $name = strtolower(array_shift($args));
                              $cmd = strtolower(implode(" ", $args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(!isset($cmds[$name])){
                                   $cmds[$name]["cmds"] = [$cmd];
                                   $cmds[$name]["buycmd"] = "true";
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
                              break;
                         case "remove":
                              if(count($args) < 1){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
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
                              break;
                         case "setprice":
                              if(count($args) < 3){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $cmd = strtolower(array_shift($args));
                              $type = strtolower(array_shift($args));
                              if($type === "money"){
                                   if($this->economy != null){
                                        $amount = array_shift($args);
                                        if(!is_numeric($amount)){
                                             $this->sendUsage($subcmd, $sender);
                                             return true;
                                        }
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
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              break;
                         case "sign":
                              if(!$sender instanceof Player){
                                   $sender->sendMessage(self::ERROR . "Please use this command in-game!");
                                   break;
                              }
                              if(count($args) < 1){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $cmd = strtolower(array_shift($args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(isset($cmds[$cmd])){
                                   $this->signsetters[$sender->getName()] = $cmd;
                                   $sender->sendMessage(self::PREFIX . "Please tap a sign now!");
                              }else{
                                   $replacers = ["{cmd}" => $cmd];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                              break;
                         case "buycmd":
                              if(count($args) < 2 || ($args[1] !== "true" && $args[1] !== "false")){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $cmds = $this->getConfig()->get("commands", []);
                              $cmd = strtolower(array_shift($args));
                              $bool = strtolower(array_shift($args));
                              if(isset($cmds[$cmd])){
                                   $cmds[$cmd]["buycmd"] = $bool;
                                   $this->getConfig()->set("commands", $cmds);
                                   $this->getConfig()->save();
                                   $sender->sendMessage(self::PREFIX . "/buycmd has been successfully " . ($bool === "true" ? "enabled" : "disabled") . " for $cmd");
                              }else{
                                   $replacers = ["{cmd}" => $cmd];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                              break;
                         case "addcmd":
                              $cmds = $this->getConfig()->get("commands", []);
                              if(count($args) < 2){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $cmd = strtolower(array_shift($args));
                              $command = strtolower(implode(" ", $args));
                              if(isset($cmds[$cmd])){
                                   $cmds[$cmd]["cmds"][] = $command;
                                   $this->getConfig()->set("commands", $cmds);
                                   $this->getConfig()->save();
                                   $sender->sendMessage(self::PREFIX . TF::GREEN . "Successfully added the command to $cmd!");
                                   $sender->sendMessage(self::PREFIX . TF::AQUA . "Command that was added: " . TF::WHITE . "/" . $command);
                              }else{
                                   $replacers = ["{cmd}" => $cmd];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                              break;
                         case "list":
                              $cmds = $this->getConfig()->get("commands", []);
                              if($cmds != []){
                                   $msg = implode(", ", array_keys($cmds));
                                   $sender->sendMessage(self::PREFIX . "List of all buyable commands:");
                                   $sender->sendMessage($msg);
                              }else{
                                   $sender->sendMessage(self::ERROR . "You haven't created any buyable commands yet, please create one using " . TF::AQUA . "/cshop add" . TF::WHITE . ".");
                              }
                              break;
                         case "info":
                              if(count($args) < 1){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $cmdn = strtolower(array_shift($args));
                              $cmds = $this->getConfig()->get("commands", []);
                              if(isset($cmds[$cmdn])){
                                   $cmd = $cmds[$cmdn];
                                   $sender->sendMessage(self::PREFIX . "Information for the command $cmdn:");
                                   $commands = "Commands: \n- " . implode("\n- ", $cmd["cmds"]);
                                   $sender->sendMessage($commands);
                                   $sender->sendMessage("/buycmd: " . $cmd["buycmd"]);
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
                                             $sender->sendMessage("Item: " . $item->getName() . " Damage: " . $item->getDamage() . " Amount: " . $item->getCount());
                                        }else{
                                             $sender->sendMessage(self::ERROR . "Invalid paytype, please use " . TF::AQUA . "/cshop setprice" . TF::WHITE . " to set the price for this command!");
                                        }
                                   }else{
                                        $sender->sendMessage(self::ERROR . "No price has been set for this command, please use " . TF::AQUA . "/cshop setprice" . TF::WHITE . " to set the price for this command!");
                                   }
                              }else{
                                   $replacers = ["{cmd}" => $cmdn];
                                   $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                              }
                              break;
                         case "help":
                              $sender->sendMessage(self::PREFIX . "CommandShop Commands:");
                              foreach ($this->usages as $cmd => $usage) {
                                   $sender->sendMessage("/cshop $cmd $usage");
                              }
                              $sender->sendMessage("/buycmd <command>\nPlease visit the wiki for this plugin here: https://github.com/BoxOfDevs/CommandShop/wiki for further information.");
                              break;
                         default:
                              return false;
                    }
                    break;
               case "buycmd":
                    if(!$sender instanceof Player){
                         $sender->sendMessage(self::ERROR . "Please use this command in-game!");
                         break;
                    }
                    if(count($args) < 1) return false;
                    $cmd = strtolower(array_shift($args));
                    $cmds = $this->getConfig()->get("commands", []);
                    if(isset($cmds[$cmd])){
                         if($cmds[$cmd]["buycmd"] === "true"){
                              $this->buyCmd($cmd, $sender);
                         }else{
                              $replacers = ["{cmd}" => $cmd];
                              $sender->sendMessage($this->getMessage("buycmd.disabled", $replacers));
                         }
                    }else{
                         $replacers = ["{cmd}" => $cmd];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                    }
                    break;
          }
          return true;
     }
}
