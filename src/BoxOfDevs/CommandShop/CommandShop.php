<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop;

use BoxOfDevs\CommandShop\Commands\BuyCmdCommand;
use BoxOfDevs\CommandShop\Commands\CShopManagementCommand;
use BoxOfDevs\CommandShop\CShopCommand\CShopCommand;
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

     /** @var CShopCommand[] */
     public $signsetters = [];

     public $confirms = [];

     /** @var string[] */
     private $paymentMethods = [];

     /** @var CShopCommand[] */
     private $commands = [];

     private function initPaymentMethods(): void {
          $prefix = __NAMESPACE__ . "\\CShopCommand\\PaymentMethod\\";
          $this->paymentMethods["item"] = $prefix . "ItemMethod";
          $this->paymentMethods["money"] = $prefix . "EconomyApiMethod";
     }

     public function getPaymentMethod(string $name): ?string {
          // TODO: Implement this in a way that isn't an eyesore
          if (!isset($this->paymentMethods[$name])) return null;
          return $this->paymentMethods[$name];
     }

     public function loadCSCommands(): void {
          $commands = $this->getConfig()->get("commands", []);
          foreach ($commands as $name => $cmd) {
               $cmd["name"] = $name;
               $this->commands[$name] = CShopCommand::jsonDeserialize($cmd, $this);
          }
     }

     public function saveCSCommands() {
          $this->getConfig()->set("commands", json_decode(json_encode($this->commands), true));
          $this->getConfig()->save();
     }

     public function getCSCommands(): array {
          return $this->commands;
     }

     public function getCSCommand(string $name): ?CShopCommand {
          if (!isset($this->commands[$name])) return null;
          return $this->commands[$name];
     }

     private function setCSCommand(string $name, CShopCommand $command, bool $save = false): void {
          $this->commands[$name] = $command;
          if ($save) $this->saveCSCommands();
     }

     public function removeCSCommand(string $name, bool $save = false): bool {
          if ($this->getCSCommand($name) === null) return false;
          unset($this->commands[$name]);
          if ($save) $this->saveCSCommands();
          return true;
     }

     public function addCSCommand(CShopCommand $command, bool $save = false): bool {
          if ($this->getCSCommand($command->getName()) !== null) return false;
          $this->setCSCommand($command->getName(), $command, $save);
          return true;
     }

     /**
      * When the plugin enables
      */
     public function onEnable(){
          $this->getLogger()->warning("This is a highly unstable development version not meant for general use, please switch to a stable version unless you know what you are doing!");
          $this->getServer()->getPluginManager()->registerEvents(new CShopListener($this),$this);
          $this->getServer()->getCommandMap()->register("commandshop", new CShopManagementCommand($this));
          $this->getServer()->getCommandMap()->register("commandshop", new BuyCmdCommand($this));
          $this->saveDefaultConfig();
          $this->initPaymentMethods();
          $this->loadCSCommands();
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
      * @param CommandSender $p
      */
     public function sendUsage(string $cmd, CommandSender $p) {
          $p->sendMessage(self::ERROR . "Usage: /cshop $cmd " . $this->usages[$cmd]);
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
