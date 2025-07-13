<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\guicore\Guielement;

use NetherByte\Customgui\utils\NbtSerializable;
use pocketmine\item\Item;

interface GuiLayoutIngredient extends NbtSerializable {

    /**
     * @return string
     * @description Get the type of this Guielement
     */
    public function getType() : string;

    /**
     * @param Item $item
     * @return bool
     * @description Check if the item can be accepted by this Guielement
     */
    public function accept(Item $item) : bool;

    /**
     * @param Item $item
     * @return Item
     * @description Return the result item after taking needed items for guicore
     */
    public function consume(Item $item) : Item;

    /**
     * @return Item
     * @description Get the item of this Guielement
     */
    public function getItem() : Item;
}