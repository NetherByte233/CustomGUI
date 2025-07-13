<?php

declare(strict_types=1);

namespace NetherByte\Customgui\ui\gui;

use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\StringToItemParser;
use NetherByte\Customgui\utils\NbtHelper;
use NetherByte\Customgui\guicore\GuiGrid;
use NetherByte\Customgui\libs\muqsit\invmenu\transaction\InvMenuTransactionResult;
use NetherByte\Customgui\ui\gui\BaseGridGUI;
use NetherByte\Customgui\libs\muqsit\invmenu\InvMenu;

class ViewCustomGUI extends BaseGridGUI {
    private string $guiName;
    /** @var array<int, array> */
    private array $actions = [];
    /** @var array<string, array> */
    private array $guiData = [];

    public function __construct(Player $player, string $guiName) {
        $this->guiName = $guiName;
        parent::__construct($player);
    }

    /**
     * Switch to a different GUI instantly without closing the inventory
     */
    public function switchToGui(string $newGuiName) : void {
        $this->guiName = $newGuiName;
        $this->actions = []; // Clear old actions
        $this->loadGuiData();
        $this->applyGuiToInventory();
        
        // Update the inventory name (this might not update the display immediately)
        $this->getMenu()->setName($this->guiName);
        
        // Force sync the inventory contents
        $this->player->getNetworkSession()->getInvManager()->syncContents($this->getMenu()->getInventory());
        
        // Note: The inventory name might not update visually due to Minecraft client limitations
        // but the functionality (items and actions) will work correctly
    }

    /**
     * Load GUI data from cache or file
     */
    private function loadGuiData() : void {
        // Try to get from player cache first
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $playerName = $this->player->getName();
        $cachedData = $plugin->getPlayerCachedGui($playerName, $this->guiName);
        
        if ($cachedData !== null) {
            $this->guiData = $cachedData;
        } else {
            // Fallback to server cache
            $cachedData = $plugin->getCachedGui($this->guiName);
            if ($cachedData !== null) {
                $this->guiData = $cachedData;
            } else {
                // Fallback to file loading
                $plugin = \NetherByte\Customgui\Customgui::getInstance();
                $dataFolder = $plugin->getGuiDataFolder();
                $file = $dataFolder . $this->guiName . ".json";
                if (file_exists($file)) {
                    $this->guiData = json_decode(file_get_contents($file), true) ?? [];
                } else {
                    $this->guiData = [];
                }
            }
        }
    }

    /**
     * Apply loaded GUI data to the inventory
     */
    private function applyGuiToInventory() : void {
        $inv = $this->getMenu()->getInventory();
        
        // Clear all slots first
        for ($i = 0; $i < 54; $i++) {
            $inv->setItem($i, VanillaItems::AIR());
        }
        
        // Apply GUI data
        foreach ($this->guiData as $key => $data) {
            if ($key === 'lores') continue; // Skip global lore data
            
            [$y, $x] = explode(',', $key);
            $slot = $y * 9 + $x;
            $nbtString = is_array($data) && isset($data['nbt']) ? $data['nbt'] : (is_string($data) ? $data : null);
            $action = is_array($data) && isset($data['action']) ? $data['action'] : null;
            $lore = is_array($data) && isset($data['lore']) ? $data['lore'] : null;
            
            try {
                $tag = NbtHelper::readCompoundTag($nbtString);
                $item = Item::nbtDeserialize($tag);
                
                // Apply lore to the item - use existing lore or empty values
                if ($lore !== null) {
                    $title = $lore['title'] ?? '';
                    $description = $lore['description'] ?? '';
                    
                    // Set the item's display name
                    $item->setCustomName("§e" . $title);
                    
                    // Set the item's lore with interaction hint
                    $item->setLore([
                        "§f" . $description,
                        "§7Click to interact"
                    ]);
                } else {
                    // Empty lore for items without lore - no custom name or lore
                    $item->setCustomName(" ");
                    $item->setLore([]);
                }
            } catch (\Throwable $e) {
                $item = VanillaItems::AIR();
            }
            $inv->setItem($slot, $item);
            if ($action !== null) {
                $this->actions[$slot] = $action;
            }
        }
    }

    protected function prepare() : void {
        $this->grid = new \NetherByte\Customgui\guicore\GuiGrid(9);
        $this->loadGuiData();
        $this->applyGuiToInventory();
        $this->getMenu()->setName($this->guiName);
    }

    protected function onTransaction($transaction): \NetherByte\Customgui\libs\muqsit\invmenu\transaction\InvMenuTransactionResult {
        // Always reload the latest actions from file before handling a click
        $this->loadGuiData();
        $this->applyGuiToInventory();
        $slot = $transaction->getAction()->getSlot();
        $player = $transaction->getPlayer();
        $action = $this->actions[$slot] ?? null;
        if ($action !== null) {
            // If multiple actions, execute all
            if (is_array($action) && isset($action[0]) && !isset($action['type'])) {
                foreach ($action as $act) {
                    $this->executeAction($player, $act);
                }
                return $transaction->discard();
            } elseif (is_array($action) && isset($action['type'])) {
                $this->executeAction($player, $action);
                return $transaction->discard();
            }
        }
        
        // Check if slot has lore and show additional info
        $this->showLoreForSlot($player, $slot);
        
        // Default: just discard the click (no item movement)
        return $transaction->discard();
    }

    private function showLoreForSlot(Player $player, int $slot) : void {
        $key = $this->getSlotKey($slot);
        if (isset($this->guiData[$key]) && is_array($this->guiData[$key]) && isset($this->guiData[$key]['lore'])) {
            $lore = $this->guiData[$key]['lore'];
            $title = $lore['title'] ?? 'Untitled';
            $description = $lore['description'] ?? 'No description';
            $player->sendMessage("§aYou clicked on: §e$title");
            $player->sendMessage("§7$description");
        }
    }

    private function executeAction(Player $player, array $action) : void {
        switch ($action['type'] ?? null) {
            case 'teleport':
                if (isset($action['x'], $action['y'], $action['z'])) {
                    $player->teleport(new \pocketmine\math\Vector3($action['x'], $action['y'], $action['z']));
                }
                break;
            case 'command':
                if (!empty($action['command'])) {
                    $player->getServer()->dispatchCommand($player, $action['command']);
                }
                break;
            case 'open_gui':
                if (!empty($action['gui_name'])) {
                    $targetGuiName = strval($action['gui_name']);
                    
                    $plugin = \NetherByte\Customgui\Customgui::getInstance();
                    $playerName = $this->player->getName();
                    
                    // Check if the target GUI exists in player cache first
                    if ($plugin->getPlayerCachedGui($playerName, $targetGuiName) !== null) {
                        // Switch to the new GUI instantly
                        $this->switchToGui($targetGuiName);
                    } else if ($plugin->getCachedGui($targetGuiName) !== null) {
                        // Switch to the new GUI instantly
                        $this->switchToGui($targetGuiName);
                    }
                }
                break;
        }
    }

    protected function mustReturnItems() : bool {
        return false;
    }

    protected function onClose(\pocketmine\player\Player $player, \pocketmine\inventory\Inventory $inventory) : void {
        // For view-only GUI, we don't want to save changes or transfer items
        // Just clear the inventory to prevent items from going to player
        for ($i = 0; $i < $inventory->getSize(); $i++) {
            $inventory->setItem($i, VanillaItems::AIR());
        }
    }

    private function getSlotKey(int $slot) : string {
        $y = intdiv($slot, 9);
        $x = $slot % 9;
        return "$y,$x";
    }
} 