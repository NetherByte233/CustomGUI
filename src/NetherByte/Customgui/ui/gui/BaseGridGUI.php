<?php
declare(strict_types=1);

namespace NetherByte\Customgui\ui\gui;

use NetherByte\Customgui\libs\muqsit\invmenu\type\InvMenuTypeIds;
use NetherByte\Customgui\guicore\GuiGrid;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

abstract class BaseGridGUI extends BaseGUI {
    protected GuiGrid $grid;

    protected function getType() : string{
        return InvMenuTypeIds::TYPE_DOUBLE_CHEST;
    }

    protected function prepare() : void{
        $this->grid = new GuiGrid(9); // 9 columns for full double chest
        $inv = $this->getMenu()->getInventory();
        // Fill all slots with AIR by default
        for ($i = 0; $i < 54; $i++) {
            $inv->setItem($i, VanillaItems::AIR());
        }
    }

    protected function onClose(Player $player, Inventory $inventory) : void{
        // Optionally, return all items in the grid to the player on close
        for ($y = 0; $y < 6; $y++){
            for ($x = 0; $x < 9; $x++){
                $item = $inventory->getItem($y * 9 + $x);
                if ($item->isNull()){
                    continue;
                }
                if ($this->player->getInventory()->canAddItem($item)){
                    $this->player->getInventory()->addItem($item);
                }else{
                    $this->player->dropItem($item);
                }
            }
        }
    }

    protected function isInGrid(int $slot) : bool{
        // All 54 slots are valid in the custom GUI
        return $slot >= 0 && $slot < 54;
    }

    protected function getGrid() : GuiGrid{
        return $this->grid;
    }

    protected function portToGrid() : void{
        $inv = $this->getMenu()->getInventory();
        for ($y = 0; $y < 6; $y++){
            for ($x = 0; $x < 9; $x++){
                $this->grid->setItem($x, $y, $inv->getItem($y * 9 + $x), false);
            }
        }
        $this->grid->seekGuiLayoutBounds();
    }
}  