<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\utils;

use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;

class NbtHelper {
    public static function writeCompoundTag(CompoundTag $tag) : string{
        $serialier = new BigEndianNbtSerializer();
        return $serialier->write(new TreeRoot($tag));
    }

    public static function readCompoundTag(string $data) : CompoundTag{
        $serialier = new BigEndianNbtSerializer();
        return $serialier->read($data)->mustGetCompoundTag();
    }

    public static function compressCompoundTag(CompoundTag $tag) : string{
        return libdeflate_gzip_compress(self::writeCompoundTag($tag));
    }

    public static function decompressCompoundTag(string $data) : CompoundTag{
        $data = zlib_decode($data);
        return self::readCompoundTag($data);
    }
}