<?php
declare(strict_types=1);

namespace NetherByte\Customgui\command;

use NetherByte\Customgui\Customgui;
use NetherByte\Customgui\ui\forms\MainForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class CustomguiCommand extends Command implements PluginOwned {

    public function __construct(){
        parent::__construct("customgui");
        $this->setPermission("cg.use");
        $this->setDescription("Custom GUI Menu");
        $this->setUsage("/customgui");
        $this->setAliases([]);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
        if (!$sender instanceof Player){
            $sender->sendMessage("This command can only be used in-game");
            return;
        }
        

        
        if (isset($args[0])) {
            $guiName = trim(implode(" ", $args));
            $plugin = Customgui::getInstance();
            
            // Check if GUI exists in cache
            if ($plugin->getCachedGui($guiName) !== null) {
                $sender->sendMessage("§aOpening GUI: §e$guiName");
                (new \NetherByte\Customgui\ui\gui\ViewCustomGUI($sender, $guiName))->sendToPlayer();
                return;
            } else {
                $sender->sendMessage("§cGUI '$guiName' not found.");
                $sender->sendMessage("§eAvailable GUIs: " . implode(", ", $plugin->getAvailableGuis()));
                return;
            }
        }
        // Only allow OPs to open the main menu
        if (!$sender->hasPermission("cg.admin")) {
            $sender->sendMessage("§cYou must be OP to use the GUI menu.");
            return;
        }
        if (Customgui::getInstance()->getGuiManager()->isReady()){
            (new MainForm($sender))->sendForm();
        } else {
            $sender->sendMessage("GuiLayouts are being updated, please wait a moment...");
        }
    }



    public function getOwningPlugin() : Plugin{
        return Customgui::getInstance();
    }
}