<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\Commands;

use BoxOfDevs\CommandShop\CommandShop;
use pocketmine\command\PluginCommand;
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
}