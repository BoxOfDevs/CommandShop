<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\CShopCommand\PaymentMethod;

use BoxOfDevs\CommandShop\JsonSavable;
use pocketmine\Player;

interface IPaymentMethod extends JsonSavable {
     /**
      * Get the name used for price->paytype in the serialized json
      *
      * @return string
      */
     public function getName(): string;

     /**
      * Get a string representation of the command's price, useful for outputting it to a player
      *
      * @return string
      */
     public function getPriceString(): string;

     /**
      * Checks whether or not the player can afford to buy a command
      *
      * @param Player $player
      * @return bool
      */
     public function canAfford(Player $player): bool;

     /**
      * Pays for the command, returns true if successful
      *
      * @param Player $player
      * @return bool
      */
     public function pay(Player $player): bool;
}