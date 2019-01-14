<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\CShopCommand\PaymentMethod;

use EssentialsPE\Commands\Economy\Eco;
use pocketmine\Player;
use onebone\economyapi\EconomyAPI;

class EconomyApiMethod implements IPaymentMethod {
     /** @var integer */
     private $price;

     public function __construct(int $price) {
          $this->price = $price;
     }

     public function getName(): string {
          return "money";
     }

     public function getPriceString(): string {
          return (string) $this->price;
     }

     public function canAfford(Player $player): bool {
          $economy = $this->getEconomyAPI($player);
          if ($economy !== false && $economy->myMoney($player) >= $this->price) {
               return true;
          }
          return false;
     }

     public function pay(Player $player): bool {
          $economy = $this->getEconomyAPI($player);
          if($economy !== false && $economy->reduceMoney($player, $this->price) === EconomyAPI::RET_SUCCESS){
               return true;
          }
          return false;
     }

     /**
      * @param Player $player
      * @return bool|EconomyAPI
      */
     private function getEconomyAPI(Player $player) {
          if ($player->getServer()->getPluginManager()->getPlugin("EconomyAPI") === null) {
               $player->getServer()->getLogger()->error("CommandShop detected an attempt to use EconomyAPI, but the plugin isn't installed!");
               return false;
          }
          return EconomyAPI::getInstance();
     }

     public function jsonSerialize(): array {
          return [
               "paytype" => "money",
               "amount" => $this->price
          ];
     }

     public static function jsonDeserialize(array $data): EconomyApiMethod {
          return new EconomyApiMethod($data["amount"]);
     }
}