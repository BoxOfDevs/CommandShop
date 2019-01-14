<?php

declare(strict_types=1);

namespace BoxOfDevs\CommandShop;

interface JsonSavable extends \JsonSerializable {
     public static function jsonDeserialize(array $data);
}