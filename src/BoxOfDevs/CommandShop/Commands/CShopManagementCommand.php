<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\Commands;

use BoxOfDevs\CommandShop\CommandShop;
use BoxOfDevs\CommandShop\CShopCommand\CShopCommand;
use BoxOfDevs\CommandShop\CShopCommand\PaymentMethod\EconomyApiMethod;
use BoxOfDevs\CommandShop\CShopCommand\PaymentMethod\ItemMethod;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

class CShopManagementCommand extends PluginCommand {
     /** @var CommandShop */
     private $plugin;

     public function __construct(CommandShop $owner) {
          parent::__construct("cshop", $owner);
          $this->setDescription("Create, manage and remove 'buyable' commands");
          $this->setUsage("/cshop <add|remove|setprice|sign|buycmd|addcmd|list|info|help> <args>");
          $this->setPermission("cshop.command.manage");
          $this->plugin = $owner;
     }

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

     /**
      * Translates a message from config
      *
      * @param string $msg
      * @param array  $replacers
      * @return string
      */
     public function getMessage(string $msg, array $replacers = []): string{
          $msg = $this->plugin->getConfig()->get($msg);
          $msg = str_ireplace(array_keys($replacers), array_values($replacers), $msg);
          $values = [
               "{prefix}" => CommandShop::PREFIX,
               "{error}" => CommandShop::ERROR,
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
      */
     private function sendUsage(string $cmd, CommandSender $p): void{
          $p->sendMessage(CommandShop::ERROR . "Usage: /cshop $cmd " . $this->usages[$cmd]);
     }

     public function execute(CommandSender $sender, string $commandLabel, array $args) {
          parent::execute($sender, $commandLabel, $args);
          // TODO: Made subcommand classes
          $subcmd = strtolower(array_shift($args));
          switch($subcmd){
               case "add":
                    if(count($args) < 2){
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    $name = strtolower(array_shift($args));
                    $cmd = strtolower(implode(" ", $args));
                    if($this->plugin->addCSCommand(new CShopCommand($name, [$cmd], true), true)){
                         $sender->sendMessage(CommandShop::PREFIX . TF::GREEN . "Command $name has been successfully added to the list of buyable commands!");
                         $sender->sendMessage(CommandShop::PREFIX . TF::AQUA . "Infos:");
                         $sender->sendMessage(CommandShop::PREFIX . "Name: " . $name);
                         $sender->sendMessage(CommandShop::PREFIX . "Command: " . $cmd);
                         $sender->sendMessage(CommandShop::PREFIX . "Please set a price now using " . TF::AQUA . "/cshop setprice" . TF::WHITE . ". Or add more commands to be executed using " . TF::AQUA . "/cshop addcmd" . TF::WHITE . ".");
                    }else{
                         $sender->sendMessage(CommandShop::ERROR . "That command does already exist!");
                    }
                    break;
               case "remove":
                    if(count($args) < 1){
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    $name = strtolower(array_shift($args));
                    if($this->plugin->removeCSCommand($name, true)){
                         $sender->sendMessage(CommandShop::PREFIX . TF::GREEN . "Command $name has been successfully removed from the list of buyable commands!");
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
                    $name = strtolower(array_shift($args));
                    $type = strtolower(array_shift($args));
                    $cmd = $this->plugin->getCSCommand($name);
                    if($cmd === null){
                         $replacers = ["{cmd}" => $name];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                         return true;
                    }
                    if($type === "money"){
                         if($this->plugin->getServer()->getPluginManager()->getPlugin("EconomyAPI") !== null){
                              $amount = array_shift($args);
                              if(!is_numeric($amount)){
                                   $this->sendUsage($subcmd, $sender);
                                   return true;
                              }
                              $method = new EconomyApiMethod($amount);
                              $cmd->setPaymentMethod($method);
                              $this->plugin->saveCSCommands();
                              $sender->sendMessage(CommandShop::PREFIX . TF::GREEN . "The price of $amount has successfully been set to the command $name!");
                         }else{
                              $sender->sendMessage(CommandShop::ERROR . "Please install EconomyAPI by onebone in order to be able to pay for commands with money!");
                         }
                    }elseif($type === "item"){
                         $item = array_shift($args);
                         $method = new ItemMethod([Item::fromString($item)]);
                         $cmd->setPaymentMethod($method);
                         $this->plugin->saveCSCommands();
                         $sender->sendMessage(CommandShop::PREFIX . TF::GREEN . "The item-price of $item has successfully been set to the command $name!");
                    }else{
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    break;
               case "sign":
                    if(!$sender instanceof Player){
                         $sender->sendMessage(CommandShop::ERROR . "Please use this command in-game!");
                         return true;
                    }
                    if(count($args) < 1){
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    $name = strtolower(array_shift($args));
                    $cmd = $this->plugin->getCSCommand($name);
                    if($cmd === null){
                         $replacers = ["{cmd}" => $name];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                         return true;
                    }
                    $this->plugin->signsetters[$sender->getName()] = $cmd;
                    $sender->sendMessage(CommandShop::PREFIX . "Please tap a sign now!");
                    break;
               case "buycmd":
                    if(count($args) < 2 || ($args[1] !== "true" && $args[1] !== "false")){
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    $name = strtolower(array_shift($args));
                    $bool = strtolower(array_shift($args));
                    $cmd = $this->plugin->getCSCommand($name);
                    if($cmd === null){
                         $replacers = ["{cmd}" => $name];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                         return true;
                    }
                    $cmd->setBuyCmdEnabled($bool === "true" ? true : false);
                    $this->plugin->saveCSCommands();
                    $sender->sendMessage(CommandShop::PREFIX . "/buycmd has been successfully " . ($bool === "true" ? "enabled" : "disabled") . " for $name");
                    break;
               case "addcmd":
                    if(count($args) < 2){
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    $name = strtolower(array_shift($args));
                    $command = strtolower(implode(" ", $args));
                    $cmd = $this->plugin->getCSCommand($name);
                    if($cmd === null){
                         $replacers = ["{cmd}" => $name];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                         return true;
                    }
                    $cmd->addCmd($command);
                    $this->plugin->saveCSCommands();
                    $sender->sendMessage(CommandShop::PREFIX . TF::GREEN . "Successfully added the command to $name!");
                    $sender->sendMessage(CommandShop::PREFIX . TF::AQUA . "Command that was added: " . TF::WHITE . "/" . $command);
                    break;
               case "list":
                    $cmds = $this->plugin->getCSCommands();
                    if($cmds !== []){
                         $msg = implode(", ", array_keys($cmds));
                         $sender->sendMessage(CommandShop::PREFIX . "List of all buyable commands:");
                         $sender->sendMessage($msg);
                    }else{
                         $sender->sendMessage(CommandShop::ERROR . "You haven't created any buyable commands yet, please create one using " . TF::AQUA . "/cshop add" . TF::WHITE . ".");
                    }
                    break;
               case "info":
                    if(count($args) < 1){
                         $this->sendUsage($subcmd, $sender);
                         return true;
                    }
                    $name = strtolower(array_shift($args));
                    $cmd = $this->plugin->getCSCommand($name);
                    if($cmd === null){
                         $replacers = ["{cmd}" => $name];
                         $sender->sendMessage($this->getMessage("command.notfound", $replacers));
                         return true;
                    }
                    $sender->sendMessage(CommandShop::PREFIX . "Information for the command $name:");
                    $commands = "Commands: \n- " . implode("\n- ", $cmd->getCmds());
                    $sender->sendMessage($commands);
                    $sender->sendMessage("/buycmd: " . $cmd->getBuyCmdEnabled());
                    $method = $cmd->getPaymentMethod();
                    if($method !== null){
                         $paytype = $method->getName();
                         if($paytype === "money"){
                              $amount =
                              $sender->sendMessage("Paytype: Money (EconomyAPI)");
                              $sender->sendMessage("Amount: $amount");
                         }elseif($paytype === "item"){
                              $item = $cmd["price"]["item"];
                              $item = $this->getItem($item);
                              $sender->sendMessage("Paytype: Items");
                              $sender->sendMessage("Item: " . $item->getName() . " Damage: " . $item->getDamage() . " Amount: " . $item->getCount());
                         }else{
                              $sender->sendMessage(self::ERROR, "Invalid paytype, please use " . TF::AQUA . "/cshop setprice" . TF::WHITE . " to set the price for this command!");
                         }
                    }else{
                         $sender->sendMessage(self::ERROR, "No price has been set for this command, please use " . TF::AQUA . "/cshop setprice" . TF::WHITE . " to set the price for this command!");
                    }
                    break;
               case "help":
                    $sender->sendMessage(CommandShop::PREFIX . "CommandShop Commands:");
                    foreach ($this->usages as $cmd => $usage) {
                         $sender->sendMessage("/cshop $cmd $usage");
                    }
                    $sender->sendMessage("/buycmd <command>\nPlease visit the wiki for this plugin here: https://github.com/BoxOfDevs/CommandShop/wiki for further information.");
                    break;
               default:
                    return false;
          }
          break;
     }
}