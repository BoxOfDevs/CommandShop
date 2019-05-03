<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\CShopCommand;

use BoxOfDevs\CommandShop\CommandShop;
use BoxOfDevs\CommandShop\CShopCommand\PaymentMethod\IPaymentMethod;
use BoxOfDevs\CommandShop\CShopCommand\PaymentMethod\ItemMethod;
use BoxOfDevs\CommandShop\JsonSavable;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\item\Item;
use pocketmine\Player;

class CShopCommand implements JsonSavable {
     /** @var string */
     private $name;

     /** @var string[] */
     private $cmds = [];

     /** @var bool */
     private $buyCmdEnabled;

     /** @var ?IPaymentMethod */
     private $paymentMethod;

     public function __construct(string $name, array $cmds, bool $buyCmdEnabled, IPaymentMethod $paymentMethod = null) {
          $this->name = $name;
          $this->cmds = $cmds;
          $this->buyCmdEnabled = $buyCmdEnabled;
          $this->paymentMethod = $paymentMethod;
     }

     public function getName(): string {
          return $this->name;
     }

     public function setName(string $name): void {
          $this->name = $name;
     }

     public function getCmds(): array {
          return $this->cmds;
     }

     public function addCmd(string $cmd): void {
          $this->cmds[] = $cmd;
     }

     public function getBuyCmdEnabled(): bool {
          return $this->buyCmdEnabled;
     }

     public function setBuyCmdEnabled(bool $value): void {
          $this->buyCmdEnabled = $value;
     }

     public function getPaymentMethod(): ?IPaymentMethod {
          return $this->paymentMethod;
     }

     public function setPaymentMethod(IPaymentMethod $method): void {
          $this->paymentMethod = $method;
     }

     public function buy(Player $player): bool {
          if ($this->paymentMethod !== null && $this->paymentMethod->pay($player)) {
               $this->executeCommands($player);
               return true;
          }
          return false;
     }

     /**
      * Execute commands as console
      *
      * @param Player $player
      */
     private function executeCommands(Player $player) {
          $this->cmds = str_replace("{player}", '"' . $player->getName() . '"', $this->cmds);
          $this->cmds = str_replace("{level}", $player->getLevel()->getName(), $this->cmds);
          $this->cmds = str_replace("{x}", round($player->x, 0), $this->cmds);
          $this->cmds = str_replace("{y}", round($player->y, 0), $this->cmds);
          $this->cmds = str_replace("{z}", round($player->z, 0), $this->cmds);
          foreach ($this->cmds as $cmd) {
               $player->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd);
          }
          return;
     }

     public function jsonSerialize(): array {
          return [
               "name" => $this->name,
               "cmds" => $this->cmds,
               "buycmd" => $this->buyCmdEnabled,
               "price" => $this->paymentMethod
          ];
     }

     public static function jsonDeserialize(array $data, CommandShop $plugin = null): CShopCommand {
          // TODO: Ewwwww what even is this
          if ($plugin === null) throw new \Error("Can't create a CShopCommand object without an instance of CommandShop");
          $paymentMethod = $plugin->getPaymentMethod($data["price"]["paytype"]);
          return new CShopCommand($data["name"], $data["cmds"], (bool) $data["buycmd"], $paymentMethod::jsonDeserialize($data["price"]));
     }
}