<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop\CShopCommand\PaymentMethod;

use pocketmine\inventory\BaseInventory;
use pocketmine\Player;
use pocketmine\item\Item;

class ItemMethod implements IPaymentMethod {
     /** @var Item[] */
     private $items = [];

     /**
      * ItemMethod constructor.
      * @param Item[] $items
      */
     public function __construct(array $items) {
          $this->items = $items;
     }

     public function getName(): string {
          return "item";
     }

     public function getPriceString(): string {
          $text = "";
          foreach ($this->items as $index => $item) {
               $text .= $item->getCount() . " x " . $item->getName();
               if ($index < count($this->items) - 1) $text .= ", ";
          }
          return $text;
     }

     public function canAfford(Player $player): bool {
          foreach ($this->items as $item) {
               if(!$player->getInventory()->contains($item)){
                    return false;
               }
          }
          return true;
     }

     public function pay(Player $player): bool {
          if (!$this->canAfford($player)) return false;
          foreach ($this->items as $item) {
               $this->removeItems($item, $player->getInventory());
          }
          return true;
     }

     /**
      * Removes an item from a player's inventory (solving count issues with PMMP)
      *
      * @param Item $item
      * @param BaseInventory $inventory
      */
     private function removeItems(Item $item, BaseInventory $inventory){
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

     public function jsonSerialize(): array {
          return [
               "paytype" => "item",
               "items" => $this->items
          ];
     }



     public static function jsonDeserialize(array $data): ItemMethod {
          $tempItems = [];
          if (isset($data["items"])) {
               foreach ($data["items"] as $item) {
                    $tempItems[] = Item::jsonDeserialize($item);
               }
          }
          if (isset($data["item"])) { // Legacy config
               $tempItems[] = Item::fromString($data["item"]);
          }
          return new ItemMethod($tempItems);
     }
}