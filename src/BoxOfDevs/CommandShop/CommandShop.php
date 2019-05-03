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
      * Called when one of the defined commands of the plugin has been called
      *
      * @param CommandSender $sender
      * @param Command       $command
      * @param string        $label
      * @param array         $args
      * @return bool
      */
     public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
          return true;
     }
}
