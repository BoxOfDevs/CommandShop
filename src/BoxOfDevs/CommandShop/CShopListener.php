<?php

namespace BoxOfDevs\CommandShop;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat as TF;

class CShopListener implements Listener{

     protected $cs;

     public function __construct(CommandShop $cs){
          $this->cs = $cs;
     }

     /**
      * When a player touches a sign
      *
      * @param PlayerInteractEvent $event
      */
     public function onSignTouch(PlayerInteractEvent $event){
          if($event->getBlock()->getId() != Block::SIGN_POST && $event->getBlock()->getId() != Block::WALL_SIGN) return;
          $p = $event->getPlayer();
          $sign = $p->getLevel()->getTile($event->getBlock());
          $level = $p->getLevel()->getName();
          if(!($sign instanceof Sign)) return;
          $x = $sign->getBlock()->getX();
          $y = $sign->getBlock()->getY();
          $z = $sign->getBlock()->getZ();
          $signs = $this->cs->getConfig()->get("signs", []);
          if(isset($this->cs->signsetters[$p->getName()])){
               foreach($signs as $i => $s){
                    if($s["posx"] === $x && $s["posy"] === $y && $s["posz"] === $z && $s["level"] === $level){
                         unset($signs[$i]);
                         $this->cs->getConfig()->set("signs", $signs);
                         $this->cs->getConfig()->save();
                    }
               }
               $signs = $this->cs->getConfig()->get("signs", []);
               $index = 0;
               while(isset($signs[$index])){
                    $index++;
               }
               $signs[$index]["posx"] = $x;
               $signs[$index]["posy"] = $y;
               $signs[$index]["posz"] = $z;
               $signs[$index]["level"] = $level;
               $signs[$index]["cmd"] = $this->cs->signsetters[$p->getName()];
               $this->cs->getConfig()->set("signs", $signs);
               $this->cs->getConfig()->save();
               unset($this->cs->signsetters[$p->getName()]);
               $p->sendMessage(CommandShop::PREFIX . TF::GREEN . "Sign has been successfully created!");
               return;
          }else{
               foreach($signs as $index => $s){
                    if($s["posx"] === $x && $s["posy"] === $y && $s["posz"] === $z && $s["level"] === $level){
                         if($p->hasPermission("cshop.buy.sign")){
                              if(isset($this->cs->confirms[$p->getName()])){
                                   if($this->cs->confirms[$p->getName()] === $index){
                                        $this->cs->buyCmd($s["cmd"], $p);
                                        unset($this->cs->confirms[$p->getName()]);
                                        return;
                                   }else{
                                        unset($this->cs->confirms[$p->getName()]);
                                   }
                              }
                              $this->cs->confirms[$p->getName()] = $index;
                              $replacers = ["{cmd}" => $s["cmd"]];
                              $p->sendMessage($this->cs->getMessage("sign.confirm", $replacers));
                         }else{
                              $p->sendMessage($this->cs->getMessage("sign.noperm"));
                         }
                         return;
                    }
               }
          }
          return;
     }

     /**
      * When a player breaks a sign
      *
      * @param BlockBreakEvent $event
      */
     public function onBlockBreak(BlockBreakEvent $event){
          if($event->getBlock()->getId() != Block::SIGN_POST && $event->getBlock()->getId() != Block::WALL_SIGN) return;
          $p = $event->getPlayer();
          $sign = $p->getLevel()->getTile($event->getBlock());
          $level = $p->getLevel()->getName();
          if(!($sign instanceof Sign)) return;
          $x = $sign->getBlock()->getX();
          $y = $sign->getBlock()->getY();
          $z = $sign->getBlock()->getZ();
          $signs = $this->cs->getConfig()->get("signs", []);
          foreach($signs as $i => $s){
               if($s["posx"] === $x && $s["posy"] === $y && $s["posz"] === $z && $s["level"] === $level){
                    if($p->hasPermission("cshop.breaksign")){
                         unset($signs[$i]);
                         $p->sendMessage(CommandShop::PREFIX . TF::RED . "Sign has been successfully removed!");
                         $this->cs->getConfig()->set("signs", $signs);
                         $this->cs->getConfig()->save();
                         return;
                    }else{
                         $p->sendMessage($this->cs->getMessage("sign.nobreak"));
                         $event->setCancelled(true);
                         return;
                    }
               }
          }
          return;
     }

}