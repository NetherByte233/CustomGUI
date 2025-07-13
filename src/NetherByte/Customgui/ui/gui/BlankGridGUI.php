<?php

declare(strict_types=1);

namespace NetherByte\Customgui\ui\gui;

use NetherByte\Customgui\guicore\GuiGrid;
use NetherByte\Customgui\utils\NbtHelper;
use NetherByte\Customgui\libs\muqsit\invmenu\transaction\InvMenuTransactionResult;
use NetherByte\Customgui\Customgui;
use NetherByte\Customgui\libs\dktapps\pmforms\ModalForm;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use NetherByte\Customgui\ui\gui\BaseGridGUI;

class BlankGridGUI extends BaseGridGUI {
    private string $guiName;
    private ?array $pendingAction = null;
    private bool $isActionAssignmentMode = false;
    private string $actionAssigningPlayer = "";
    private ?array $pendingLore = null;
    private bool $isLoreAssignmentMode = false;
    private string $loreAssigningPlayer = "";
    private array $mergeReplacePrompt = [];
    private static array $pendingMergeReplace = [];

    public function __construct(Player $player, string $guiName) {
        $this->guiName = trim($guiName);
        parent::__construct($player);
    }

    protected function prepare() : void {
        $this->grid = new \NetherByte\Customgui\guicore\GuiGrid(9); // 9 columns
        $inv = $this->getMenu()->getInventory();
        // Fill all slots (6x9 = 54) with AIR
        for ($i = 0; $i < 54; $i++) {
            $inv->setItem($i, \pocketmine\item\VanillaItems::AIR());
        }
        // Load existing GUI if it exists
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $this->guiName . ".json";
        if (file_exists($file)) {
            $items = json_decode(file_get_contents($file), true);
            foreach ($items as $key => $data) {
                [$y, $x] = explode(',', $key);
                $slot = $y * 9 + $x;
                $nbtString = is_array($data) && isset($data['nbt']) ? $data['nbt'] : (is_string($data) ? $data : null);
                try {
                    $tag = \NetherByte\Customgui\utils\NbtHelper::readCompoundTag($nbtString);
                    $item = \pocketmine\item\Item::nbtDeserialize($tag);
                } catch (\Throwable $e) {
                    $item = \pocketmine\item\VanillaItems::AIR();
                }
                $inv->setItem($slot, $item);
            }
        }
        $this->getMenu()->setName("Edit GUI: " . $this->guiName);
    }

    protected function onTransaction($transaction): \NetherByte\Customgui\libs\muqsit\invmenu\transaction\InvMenuTransactionResult {
        $player = $transaction->getPlayer();
        $slot = $transaction->getAction()->getSlot();
        
        // Handle lore assignment
        if ($this->isLoreAssignmentMode && $player->getName() === $this->loreAssigningPlayer) {
            $this->isLoreAssignmentMode = false;
            $this->loreAssigningPlayer = "";
            $lore = $this->pendingLore;
            $this->pendingLore = null;
            $plugin = \NetherByte\Customgui\Customgui::getInstance();
            $dataFolder = $plugin->getGuiDataFolder();
            $file = $dataFolder . $this->guiName . ".json";
            $items = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            $key = $this->getSlotKey($slot);
            $this->saveLoreToSlot($file, $items, $key, $lore);
            $player->sendMessage("§aLore assigned to slot $key.");
            return $transaction->discard();
        }
        
        // Handle action assignment
        if ($this->isActionAssignmentMode && $player->getName() === $this->actionAssigningPlayer) {
            $this->isActionAssignmentMode = false;
            $this->actionAssigningPlayer = "";
            $action = $this->pendingAction;
            $this->pendingAction = null;
            $plugin = \NetherByte\Customgui\Customgui::getInstance();
            $dataFolder = $plugin->getGuiDataFolder();
            $file = $dataFolder . $this->guiName . ".json";
            $items = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            $key = $this->getSlotKey($slot);
            $existing = isset($items[$key]['action']) ? $items[$key]['action'] : (isset($items[$key]) && is_array($items[$key]) && isset($items[$key]['action']) ? $items[$key]['action'] : null);
            // Prevent adding more than one teleport action or tp command
            $isTeleport = is_array($action) && (
                (isset($action['type']) && $action['type'] === 'teleport') ||
                (isset($action['type']) && $action['type'] === 'command' && isset($action['command']) && preg_match('/^tp( |$)/i', trim($action['command'])))
            );
            $existingTeleports = 0;
            if ($existing !== null) {
                if (is_array($existing) && isset($existing[0]) && !isset($existing['type'])) {
                    foreach ($existing as $act) {
                        if (is_array($act) && (
                            (isset($act['type']) && $act['type'] === 'teleport') ||
                            (isset($act['type']) && $act['type'] === 'command' && isset($act['command']) && preg_match('/^tp( |$)/i', trim($act['command'])))
                        )) {
                            $existingTeleports++;
                        }
                    }
                } elseif (is_array($existing) && (
                    (isset($existing['type']) && $existing['type'] === 'teleport') ||
                    (isset($existing['type']) && $existing['type'] === 'command' && isset($existing['command']) && preg_match('/^tp( |$)/i', trim($existing['command'])))
                )) {
                    $existingTeleports++;
                }
            }
            if ($isTeleport && $existingTeleports > 0) {
                $player->sendMessage("§cYou cannot add more than one teleport or tp command action to the same slot!");
                return $transaction->discard();
            }
            if ($existing !== null) {
                // Store pending merge/replace context and close GUI
                self::$pendingMergeReplace[$player->getName()] = [
                    'guiName' => $this->guiName,
                    'slot' => $key,
                    'existing' => $existing,
                    'new' => $action
                ];
                $player->removeCurrentWindow();
                // Delay sending the form to ensure the window is closed
                $this->scheduleMergeReplacePrompt($player);
                return $transaction->discard();
            }
            // Save the action (no existing)
            $this->saveActionToSlot($file, $items, $key, $action);
            $player->sendMessage("§aAction assigned to slot $key.");
            return $transaction->discard();
        }
        return $transaction->continue();
    }

    private function scheduleMergeReplacePrompt(Player $player) : void {
        // Use a short delay to ensure the inventory window is closed before sending the form
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $plugin->getScheduler()->scheduleDelayedTask(new class($player) extends \pocketmine\scheduler\Task {
            private $player;
            public function __construct($player) { $this->player = $player; }
            public function onRun() : void {
                $gui = BlankGridGUI::class;
                $gui::showStaticMergeReplacePrompt($this->player);
            }
        }, 10); // 10 ticks = 0.5s
    }

    public static function showStaticMergeReplacePrompt(Player $player) : void {
        $data = self::$pendingMergeReplace[$player->getName()] ?? null;
        if ($data === null) return;
        $form = new \NetherByte\Customgui\libs\dktapps\pmforms\ModalForm(
            "Slot already has an action",
            "This slot already has an action. What do you want to do?",
            function(Player $submitter, bool $choice) use ($data) : void {
                $plugin = \NetherByte\Customgui\Customgui::getInstance();
                $dataFolder = $plugin->getGuiDataFolder();
                $file = $dataFolder . $data['guiName'] . ".json";
                $items = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
                $key = $data['slot'];
                $existing = $data['existing'];
                $new = $data['new'];
                // Prevent merging if both actions are teleport or tp command
                $newIsTeleport = is_array($new) && (
                    (isset($new['type']) && $new['type'] === 'teleport') ||
                    (isset($new['type']) && $new['type'] === 'command' && isset($new['command']) && preg_match('/^tp( |$)/i', trim($new['command'])))
                );
                $existingTeleports = 0;
                if ($existing !== null) {
                    if (is_array($existing) && isset($existing[0]) && !isset($existing['type'])) {
                        foreach ($existing as $act) {
                            if (is_array($act) && (
                                (isset($act['type']) && $act['type'] === 'teleport') ||
                                (isset($act['type']) && $act['type'] === 'command' && isset($act['command']) && preg_match('/^tp( |$)/i', trim($act['command'])))
                            )) {
                                $existingTeleports++;
                            }
                        }
                    } elseif (is_array($existing) && (
                        (isset($existing['type']) && $existing['type'] === 'teleport') ||
                        (isset($existing['type']) && $existing['type'] === 'command' && isset($existing['command']) && preg_match('/^tp( |$)/i', trim($existing['command'])))
                    )) {
                        $existingTeleports++;
                    }
                }
                if ($choice) { // true = merge, false = replace
                    if ($newIsTeleport && $existingTeleports > 0) {
                        $submitter->sendMessage("§cYou cannot have more than one teleport or tp command action in the same slot!");
                        unset(self::$pendingMergeReplace[$submitter->getName()]);
                        return;
                    }
                    // Merge
                    if (is_array($existing) && isset($existing[0])) {
                        $actions = $existing;
                    } else {
                        $actions = [$existing];
                    }
                    $actions[] = $new;
                    if (isset($items[$key]) && is_array($items[$key])) {
                        $items[$key]['action'] = $actions;
                    } else if (isset($items[$key])) {
                        $items[$key] = [
                            'nbt' => $items[$key],
                            'action' => $actions
                        ];
                    } else {
                        $items[$key] = [
                            'nbt' => '',
                            'action' => $actions
                        ];
                    }
                    file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $submitter->sendMessage("§aActions merged for slot $key.");
                } else {
                    // Replace
                    if (isset($items[$key]) && is_array($items[$key])) {
                        $items[$key]['action'] = $new;
                    } else if (isset($items[$key])) {
                        $items[$key] = [
                            'nbt' => $items[$key],
                            'action' => $new
                        ];
                    } else {
                        $items[$key] = [
                            'nbt' => '',
                            'action' => $new
                        ];
                    }
                    file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $submitter->sendMessage("§aAction replaced for slot $key.");
                }
                unset(self::$pendingMergeReplace[$submitter->getName()]);
                // Do NOT reopen the GUI here
            },
            "Merge",
            "Replace"
        );
        $player->sendForm($form);
    }

    private function saveActionToSlot(string $file, array $items, string $key, $action) : void {
        if (isset($items[$key]) && is_array($items[$key])) {
            $items[$key]['action'] = $action;
        } else if (isset($items[$key])) {
            $items[$key] = [
                'nbt' => $items[$key],
                'action' => $action
            ];
        } else {
            $items[$key] = [
                'nbt' => '',
                'action' => $action
            ];
        }
        file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function saveLoreToSlot(string $file, array $items, string $key, $lore) : void {
        if (isset($items[$key]) && is_array($items[$key])) {
            // Preserve existing data and add lore
            $items[$key]['lore'] = [
                'title' => $lore['title'],
                'description' => $lore['description']
            ];
        } else if (isset($items[$key])) {
            // Convert string NBT to array and add lore
            $items[$key] = [
                'nbt' => $items[$key],
                'lore' => [
                    'title' => $lore['title'],
                    'description' => $lore['description']
                ]
            ];
        } else {
            // Create new slot with lore only
            $items[$key] = [
                'nbt' => '',
                'lore' => [
                    'title' => $lore['title'],
                    'description' => $lore['description']
                ]
            ];
        }
        file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // Debug: Log what was saved
        $this->player->sendMessage("§aDebug: Saved lore to slot $key with title: " . $lore['title']);
        // Reload the GUI cache immediately
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        if ($plugin !== null) {
            $plugin->reloadGui($this->guiName);
        }
    }

    protected function onClose(\pocketmine\player\Player $player, \pocketmine\inventory\Inventory $inventory) : void {
        // Save the GUI contents to plugin_data/customgui/guis/{name}.json
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $this->guiName . ".json";
        $existing = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        $items = [];
        for ($y = 0; $y < 6; $y++) {
            for ($x = 0; $x < 9; $x++) {
                $slot = $y * 9 + $x;
                $key = "$y,$x";
                $item = $inventory->getItem($slot);
                if (!$item->isNull()) {
                    // Serialize item to NBT string
                    $nbt = \NetherByte\Customgui\utils\NbtHelper::writeCompoundTag($item->nbtSerialize());
                    if (isset($existing[$key]) && is_array($existing[$key])) {
                        $items[$key] = $existing[$key];
                        $items[$key]['nbt'] = $nbt;
                    } else {
                        $items[$key] = $nbt;
                    }
                } else if (isset($existing[$key]) && is_array($existing[$key])) {
                    // Keep existing data (action, lore, etc.) even if item is now AIR
                    $items[$key] = $existing[$key];
                    $items[$key]['nbt'] = '';
                }
            }
        }
        // Preserve existing global lore data (for backward compatibility)
        if (isset($existing['lores'])) {
            $items['lores'] = $existing['lores'];
        }
        
        file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $player->sendMessage("§aGUI saved as: " . $this->guiName);
        // Immediately reload the GUI into the plugin cache so it can be opened without restart
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        if ($plugin !== null) {
            $plugin->reloadGui($this->guiName);
        }
    }

    public function setPendingAction(Player $player, array $action) : void {
        $this->pendingAction = $action;
        $this->isActionAssignmentMode = true;
        $this->actionAssigningPlayer = $player->getName();
    }

    public function setPendingLore(Player $player, array $lore) : void {
        $this->pendingLore = $lore;
        $this->isLoreAssignmentMode = true;
        $this->loreAssigningPlayer = $player->getName();
        $player->sendMessage("§aClick on a slot to assign the lore.");
    }

    private function getSlotKey(int $slot) : string {
        $y = intdiv($slot, 9);
        $x = $slot % 9;
        return "$y,$x";
    }
} 