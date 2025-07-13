<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\ui\gui;

use NetherByte\Customgui\libs\muqsit\invmenu\InvMenu;
use NetherByte\Customgui\libs\muqsit\invmenu\transaction\InvMenuTransaction;
use NetherByte\Customgui\libs\muqsit\invmenu\transaction\InvMenuTransactionResult;
use NetherByte\Customgui\libs\muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\inventory\Inventory;
use pocketmine\player\Player;

abstract class BaseGUI {

    protected InvMenu $menu;

    public function __construct(protected Player $player){
        $this->menu = InvMenu::create($this->getType());
        $this->prepare();
        $this->menu->setListener($this->onTransaction(...));
        $this->menu->setInventoryCloseListener($this->onClose(...));
    }

    protected function getMenu() : InvMenu{
        return $this->menu;
    }

    public function sendToPlayer() : void{
        $this->menu->send($this->player);
    }

    protected function getType() : string{
        return InvMenuTypeIds::TYPE_CHEST;
    }

    // Define menu content
    protected function prepare() : void{}

    // Handle menu transaction
    protected abstract function onTransaction(InvMenuTransaction $transaction) : InvMenuTransactionResult;

    // Optional: Do sth when player close menu
    protected function onClose(Player $player, Inventory $inventory) : void{}
}