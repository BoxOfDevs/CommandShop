<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\Commands;

use BoxOfDevs\CommandShop\CommandShop;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\network\mcpe\protocol\types\CommandData;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class BuyCmdCommand extends PluginCommand {
     /** @var CommandShop */
     private $plugin;

     public function __construct(Plugin $owner) {
          parent::__construct("buycmd", $owner);
          $this->setDescription('"Buy" a command');
          $this->setUsage("/buycmd <command>");
          $this->setPermission("cshop.buy.command");
          $this->plugin = $owner;
     }

     public function execute(CommandSender $sender, string $commandLabel, array $args) {
          if(!$sender instanceof Player){
               $sender->sendMessage(CommandShop::ERROR . "Please use this command in-game!");
               return true;
          }
          if(count($args) < 1) {
               $sender->sendMessage($this->getUsage());
               return false;
          }
          $cmdName = strtolower(array_shift($args));
          $cmd = $this->plugin->getCSCommand($cmdName);
          // TODO: Add translations
          if ($cmd === null) {
               $sender->sendMessage(CommandShop::ERROR . "This command wasn't found in the list of buyable commands!");
          } elseif (!$cmd->getBuyCmdEnabled()) {
               $sender->sendMessage(CommandShop::ERROR . "/buycmd is disabled for this buyable command!");
          } elseif (!$cmd->buy($sender)) {
               $sender->sendMessage(CommandShop::ERROR . "You can't buy this command.");
          } else {
               $sender->sendMessage(CommandShop::PREFIX . "You successfully bought the command $cmdName!");
          };
          return true;
     }
}