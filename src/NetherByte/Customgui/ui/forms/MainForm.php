<?php
declare(strict_types=1);

namespace NetherByte\Customgui\ui\forms;

use NetherByte\Customgui\libs\dktapps\pmforms\CustomForm;
use NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse;
use NetherByte\Customgui\libs\dktapps\pmforms\element\Input;
use NetherByte\Customgui\libs\dktapps\pmforms\MenuForm;
use NetherByte\Customgui\libs\dktapps\pmforms\MenuOption;
use NetherByte\Customgui\libs\dktapps\pmforms\ModalForm;
use NetherByte\Customgui\guicore\GuiLayout;
use NetherByte\Customgui\Customgui;
use NetherByte\Customgui\ui\gui\AddGuiLayoutGUI;
use NetherByte\Customgui\ui\gui\CraftingGUI;
use NetherByte\Customgui\ui\gui\EditGuiLayoutGUI;
use NetherByte\Customgui\ui\gui\ViewGuiLayoutGUI;
use NetherByte\Customgui\ui\gui\BlankGridGUI;
use NetherByte\Customgui\ui\gui\ViewCustomGUI;
use pocketmine\player\Player;

class MainForm{

    public function __construct(protected Player $player){}

    function sendForm() : void{
        $options = [
            new MenuOption("Create GUI"),
            new MenuOption("GUIs")
        ];
        $title = "Custom GUI Menu";

        $form = new MenuForm(
            $title, "", $options,
            function(Player $submitter, int $selected) : void{
                switch($selected){
                    case 0:
                        $this->createForm($submitter);
                        break;
                    case 1:
                        $this->listGUIs($submitter);
                        break;
                }
            }
        );
        $this->player->sendForm($form);
    }

    private function createForm(Player $player) : void{
        $form = new CustomForm(
            "Create Custom GUI",
            [
                new Input("gui_name", "Enter GUI Name", "my_gui")
            ],
            function (Player $player, CustomFormResponse $response) : void{
                $gui_name = $response->getString("gui_name");
                
                if (empty($gui_name)) {
                    $player->sendMessage("§cGUI name cannot be empty!");
                    return;
                }
                // Open a blank grid GUI
                (new BlankGridGUI($player, $gui_name))->sendToPlayer();
            }
        );
        $player->sendForm($form);
    }

    private function listGUIs(Player $player) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $guis = [];
        if (is_dir($dataFolder)) {
            foreach (glob($dataFolder . "*.json") as $file) {
                $guis[] = basename($file, ".json");
            }
        }
        if (empty($guis)) {
            $player->sendMessage("§cNo GUIs found!");
            return;
        }
        $options = array_map(fn($name) => new MenuOption($name), $guis);
        $form = new MenuForm(
            "Saved GUIs", "Select a GUI to manage:", $options,
            function(Player $submitter, int $selected) use ($guis) : void {
                $guiName = $guis[$selected];
                $this->guiSubMenu($submitter, $guiName);
            }
        );
        $player->sendForm($form);
    }

    private function guiSubMenu(Player $player, string $guiName) : void {
        $form = new MenuForm(
            "GUI: $guiName",
            "What do you want to do with this GUI?",
            [
                new MenuOption("Open"),
                new MenuOption("Edit"),
                new MenuOption("Delete"),
                new MenuOption("Action"),
                new MenuOption("Lore")
            ],
            function(Player $submitter, int $selected) use ($guiName) : void {
                switch ($selected) {
                    case 0: // Open
                        (new ViewCustomGUI($submitter, $guiName))->sendToPlayer();
                        break;
                    case 1: // Edit
                        (new \NetherByte\Customgui\ui\gui\BlankGridGUI($submitter, $guiName))->sendToPlayer();
                        break;
                    case 2: // Delete
                        $this->confirmDeleteGUI($submitter, $guiName);
                        break;
                    case 3: // Action
                        $this->actionManagementMenu($submitter, $guiName);
                        break;
                    case 4: // Lore
                        $this->loreManagementMenu($submitter, $guiName);
                        break;
                }
            }
        );
        $player->sendForm($form);
    }

    private function actionManagementMenu(Player $player, string $guiName) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $guiName . ".json";
        $actions = [];
        if (file_exists($file)) {
            $items = json_decode(file_get_contents($file), true);
            foreach ($items as $key => $data) {
                $action = is_array($data) && isset($data['action']) ? $data['action'] : null;
                if ($action !== null) {
                    $actions[$key] = $action;
                }
            }
        }
        $options = [];
        foreach ($actions as $slot => $action) {
            if (is_array($action) && isset($action[0]) && !isset($action['type'])) {
                // Multiple actions: show comma-separated types
                $types = array_map(fn($a) => $a['type'] ?? 'unknown', $action);
                $desc = implode(', ', $types);
            } else if (is_array($action) && isset($action['type'])) {
                $desc = $action['type'];
            } else {
                $desc = is_array($action) ? json_encode($action) : strval($action);
            }
            $options[] = new MenuOption("Slot $slot: $desc");
        }
        $options[] = new MenuOption("§aAdd New Action");
        $form = new MenuForm(
            "Actions for $guiName",
            "Select an action to edit/remove, or add a new one:",
            $options,
            function(Player $submitter, int $selected) use ($guiName, $actions) : void {
                if ($selected === count($actions)) {
                    $this->addNewActionTypeForm($submitter, $guiName);
                } else {
                    $slot = array_keys($actions)[$selected];
                    $this->actionEditRemoveMenu($submitter, $guiName, $slot, $actions[$slot]);
                }
            }
        );
        $player->sendForm($form);
    }

    private function actionEditRemoveMenu(Player $player, string $guiName, string $slot, $action) : void {
        // If multiple actions, show a menu to select which one to edit/remove
        if (is_array($action) && isset($action[0]) && !isset($action['type'])) {
            $options = [];
            foreach ($action as $i => $act) {
                $desc = is_array($act) && isset($act['type']) ? $act['type'] : (is_array($act) ? json_encode($act) : strval($act));
                $options[] = new MenuOption("Action $i: $desc");
            }
            $form = new MenuForm(
                "Edit/Remove Action",
                "Select an action to edit or remove:",
                $options,
                function(Player $submitter, int $selected) use ($guiName, $slot, $action) : void {
                    $this->actionEditRemoveMenu($submitter, $guiName, $slot, $action[$selected]);
                }
            );
            $player->sendForm($form);
            return;
        }
        $desc = is_array($action) && isset($action['type']) ? $action['type'] : (is_array($action) ? json_encode($action) : strval($action));
        $form = new MenuForm(
            "Action for $guiName [$slot]",
            "Action: $desc",
            [
                new MenuOption("Edit Action"),
                new MenuOption("Remove Action")
            ],
            function(Player $submitter, int $selected) use ($guiName, $slot, $action) : void {
                if ($selected === 0) {
                    // Edit Action flow
                    if (is_array($action) && isset($action['type'])) {
                        switch ($action['type']) {
                            case 'command':
                                $this->editActionCommandForm($submitter, $guiName, $slot, $action);
                                break;
                            case 'teleport':
                                $this->editActionTeleportForm($submitter, $guiName, $slot, $action);
                                break;
                            case 'open_gui':
                                $this->editActionOpenGuiForm($submitter, $guiName, $slot, $action);
                                break;
                            default:
                                $submitter->sendMessage("§cUnknown action type, cannot edit.");
                        }
                    } else {
                        $submitter->sendMessage("§cInvalid action data, cannot edit.");
                    }
                } else {
                    $this->removeActionFromSlot($submitter, $guiName, $slot);
                }
            }
        );
        $player->sendForm($form);
    }

    private function editActionCommandForm(Player $player, string $guiName, string $slot, array $action) : void {
        $form = new \NetherByte\Customgui\libs\dktapps\pmforms\CustomForm(
            "Edit Command Action",
            [
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("command", "Enter the command to run (without /)", "", $action['command'] ?? "")
            ],
            function(Player $submitter, \NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse $response) use ($guiName, $slot, $action) : void {
                $command = $response->getString("command");
                if (empty($command)) {
                    $submitter->sendMessage("§cCommand cannot be empty!");
                    return;
                }
                $this->updateActionInSlot($submitter, $guiName, $slot, ["type" => "command", "command" => $command]);
            }
        );
        $player->sendForm($form);
    }

    private function editActionTeleportForm(Player $player, string $guiName, string $slot, array $action) : void {
        $form = new \NetherByte\Customgui\libs\dktapps\pmforms\CustomForm(
            "Edit Teleport Action",
            [
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("x", "X coordinate", "", strval($action['x'] ?? "")),
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("y", "Y coordinate", "", strval($action['y'] ?? "")),
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("z", "Z coordinate", "", strval($action['z'] ?? ""))
            ],
            function(Player $submitter, \NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse $response) use ($guiName, $slot, $action) : void {
                $x = $response->getString("x");
                $y = $response->getString("y");
                $z = $response->getString("z");
                if (!is_numeric($x) || !is_numeric($y) || !is_numeric($z)) {
                    $submitter->sendMessage("§cCoordinates must be numbers!");
                    return;
                }
                $this->updateActionInSlot($submitter, $guiName, $slot, ["type" => "teleport", "x" => (float)$x, "y" => (float)$y, "z" => (float)$z]);
            }
        );
        $player->sendForm($form);
    }

    private function editActionOpenGuiForm(Player $player, string $guiName, string $slot, array $action) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $availableGuis = $plugin->getAvailableGuis();
        
        // Remove the current GUI from the list to prevent self-reference
        $availableGuis = array_filter($availableGuis, fn($gui) => $gui !== $guiName);
        
        if (empty($availableGuis)) {
            $player->sendMessage("§cNo other GUIs available to open!");
            return;
        }
        
        // Find the current selection index
        $currentGuiName = strval($action['gui_name'] ?? '');
        $availableGuisArray = array_values($availableGuis);
        $currentIndex = array_search($currentGuiName, $availableGuisArray);
        if ($currentIndex === false) {
            $currentIndex = 0; // Default to first option if current GUI not found
        }
        
        try {
            $form = new \NetherByte\Customgui\libs\dktapps\pmforms\CustomForm(
                "Edit Open GUI Action",
                [
                    new \NetherByte\Customgui\libs\dktapps\pmforms\element\Dropdown("gui_name", "Select the GUI to open", $availableGuis, $currentIndex)
                ],
                function(Player $submitter, \NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse $response) use ($guiName, $slot, $availableGuis) : void {
                    $selectedIndex = $response->getInt("gui_name");
                    $availableGuisArray = array_values($availableGuis);
                    if ($selectedIndex >= 0 && $selectedIndex < count($availableGuisArray)) {
                        $targetGuiName = strval($availableGuisArray[$selectedIndex]);
                        $this->updateActionInSlot($submitter, $guiName, $slot, ["type" => "open_gui", "gui_name" => $targetGuiName]);
                    } else {
                        $submitter->sendMessage("§cInvalid GUI selection!");
                    }
                }
            );
            $player->sendForm($form);
        } catch (\Throwable $e) {
            $player->sendMessage("§cError creating form: " . $e->getMessage());
        }
    }

    private function updateActionInSlot(Player $player, string $guiName, string $slot, array $newAction) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $guiName . ".json";
        if (!file_exists($file)) {
            $player->sendMessage("§cGUI file not found.");
            return;
        }
        $items = json_decode(file_get_contents($file), true);
        if (isset($items[$slot]) && is_array($items[$slot])) {
            $items[$slot]['action'] = $newAction;
            file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $player->sendMessage("§aAction updated for slot $slot.");
            // Reload the GUI cache immediately
            $plugin = \NetherByte\Customgui\Customgui::getInstance();
            if ($plugin !== null) {
                $plugin->reloadGui($guiName);
            }
        } else {
            $player->sendMessage("§cNo action found for slot $slot.");
        }
    }

    private function removeActionFromSlot(Player $player, string $guiName, string $slot) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $guiName . ".json";
        if (!file_exists($file)) {
            $player->sendMessage("§cGUI file not found.");
            return;
        }
        $items = json_decode(file_get_contents($file), true);
        if (isset($items[$slot]) && is_array($items[$slot]) && isset($items[$slot]['action'])) {
            unset($items[$slot]['action']);
            file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $player->sendMessage("§aAction removed from slot $slot.");
            // Reload the GUI cache immediately
            $plugin = \NetherByte\Customgui\Customgui::getInstance();
            if ($plugin !== null) {
                $plugin->reloadGui($guiName);
            }
        } else {
            $player->sendMessage("§cNo action found for slot $slot.");
        }
    }

    private function addNewActionTypeForm(Player $player, string $guiName) : void {
        $form = new MenuForm(
            "Add New Action",
            "Select the type of action to add:",
            [
                new MenuOption("Command"),
                new MenuOption("Teleport"),
                new MenuOption("Open GUI")
            ],
            function(Player $submitter, int $selected) use ($guiName) : void {
                switch ($selected) {
                    case 0: // Command
                        $this->addNewActionCommandForm($submitter, $guiName);
                        break;
                    case 1: // Teleport
                        $this->addNewActionTeleportForm($submitter, $guiName);
                        break;
                    case 2: // Open GUI
                        $this->addNewActionOpenGuiForm($submitter, $guiName);
                        break;
                }
            }
        );
        $player->sendForm($form);
    }

    private function addNewActionCommandForm(Player $player, string $guiName) : void {
        $form = new \NetherByte\Customgui\libs\dktapps\pmforms\CustomForm(
            "Add Command Action",
            [
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("command", "Enter the command to run (without /)")
            ],
            function(Player $submitter, \NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse $response) use ($guiName) : void {
                $command = $response->getString("command");
                if (empty($command)) {
                    $submitter->sendMessage("§cCommand cannot be empty!");
                    return;
                }
                $action = ["type" => "command", "command" => $command];
                $gui = new \NetherByte\Customgui\ui\gui\BlankGridGUI($submitter, $guiName);
                $gui->setPendingAction($submitter, $action);
                $gui->sendToPlayer();
            }
        );
        $player->sendForm($form);
    }

    private function addNewActionTeleportForm(Player $player, string $guiName) : void {
        $form = new \NetherByte\Customgui\libs\dktapps\pmforms\CustomForm(
            "Add Teleport Action",
            [
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("x", "X coordinate"),
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("y", "Y coordinate"),
                new \NetherByte\Customgui\libs\dktapps\pmforms\element\Input("z", "Z coordinate")
            ],
            function(Player $submitter, \NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse $response) use ($guiName) : void {
                $x = $response->getString("x");
                $y = $response->getString("y");
                $z = $response->getString("z");
                if (!is_numeric($x) || !is_numeric($y) || !is_numeric($z)) {
                    $submitter->sendMessage("§cCoordinates must be numbers!");
                    return;
                }
                $action = ["type" => "teleport", "x" => (float)$x, "y" => (float)$y, "z" => (float)$z];
                $gui = new \NetherByte\Customgui\ui\gui\BlankGridGUI($submitter, $guiName);
                $gui->setPendingAction($submitter, $action);
                $gui->sendToPlayer();
            }
        );
        $player->sendForm($form);
    }

    private function addNewActionOpenGuiForm(Player $player, string $guiName) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $availableGuis = $plugin->getAvailableGuis();
        
        // Remove the current GUI from the list to prevent self-reference
        $availableGuis = array_filter($availableGuis, fn($gui) => $gui !== $guiName);
        
        if (empty($availableGuis)) {
            $player->sendMessage("§cNo other GUIs available to open!");
            return;
        }
        
        try {
            $form = new \NetherByte\Customgui\libs\dktapps\pmforms\CustomForm(
                "Add Open GUI Action",
                [
                    new \NetherByte\Customgui\libs\dktapps\pmforms\element\Dropdown("gui_name", "Select the GUI to open", $availableGuis)
                ],
                function(Player $submitter, \NetherByte\Customgui\libs\dktapps\pmforms\CustomFormResponse $response) use ($guiName, $availableGuis) : void {
                    $selectedIndex = $response->getInt("gui_name");
                    $availableGuisArray = array_values($availableGuis);
                    if ($selectedIndex >= 0 && $selectedIndex < count($availableGuisArray)) {
                        $targetGuiName = strval($availableGuisArray[$selectedIndex]);
                        $action = ["type" => "open_gui", "gui_name" => $targetGuiName];
                        $gui = new \NetherByte\Customgui\ui\gui\BlankGridGUI($submitter, $guiName);
                        $gui->setPendingAction($submitter, $action);
                        $gui->sendToPlayer();
                    }
                }
            );
            $player->sendForm($form);
        } catch (\Throwable $e) {
            $player->sendMessage("§cError creating form: " . $e->getMessage());
        }
    }

    private function confirmDeleteGUI(Player $player, string $guiName) : void {
        $form = new ModalForm(
            "Delete GUI",
            "Are you sure you want to delete the GUI '$guiName'? This cannot be undone!",
            function(Player $submitter, bool $choice) use ($guiName) : void {
                if ($choice) {
                    $plugin = \NetherByte\Customgui\Customgui::getInstance();
                    $dataFolder = $plugin->getGuiDataFolder();
                    $file = $dataFolder . $guiName . ".json";
                    if (file_exists($file)) {
                        unlink($file);
                        $submitter->sendMessage("§aGUI '$guiName' deleted.");
                    } else {
                        $submitter->sendMessage("§cGUI file not found.");
                    }
                } else {
                    $submitter->sendMessage("§eDeletion cancelled.");
                }
            }
        );
        $player->sendForm($form);
    }

    private function loreManagementMenu(Player $player, string $guiName) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $guiName . ".json";
        $slotLores = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            foreach ($data as $key => $itemData) {
                if ($key === 'lores') continue; // Skip old global lore format
                if (is_array($itemData) && isset($itemData['lore'])) {
                    $slotLores[$key] = $itemData['lore'];
                }
            }
        }
        
        $options = [];
        foreach ($slotLores as $slot => $lore) {
            $title = $lore['title'] ?? 'Untitled';
            $description = $lore['description'] ?? 'No description';
            $options[] = new MenuOption("Slot $slot: $title - $description");
        }
        $player->sendMessage("§aDebug: Found " . count($slotLores) . " slots with lore");
        foreach ($slotLores as $slot => $lore) {
            $player->sendMessage("§aDebug: Slot $slot has lore: " . ($lore['title'] ?? 'no title'));
        }
        $options[] = new MenuOption("§aAdd New Lore to Slot");
        
        $form = new MenuForm(
            "Lore for $guiName",
            "Select a lore to edit/remove, or add new lore to a slot:",
            $options,
            function(Player $submitter, int $selected) use ($guiName, $slotLores) : void {
                if ($selected === count($slotLores)) {
                    $this->addNewLoreForm($submitter, $guiName);
                } else {
                    $slot = array_keys($slotLores)[$selected];
                    $lore = $slotLores[$slot];
                    $this->loreEditRemoveMenu($submitter, $guiName, $slot, $lore);
                }
            }
        );
        $player->sendForm($form);
    }

    private function loreEditRemoveMenu(Player $player, string $guiName, string $slot, array $lore) : void {
        $title = $lore['title'] ?? 'Untitled';
        $description = $lore['description'] ?? 'No description';
        $form = new MenuForm(
            "Lore for $guiName [$slot]",
            "Title: $title\nDescription: $description",
            [
                new MenuOption("Edit Lore"),
                new MenuOption("Remove Lore")
            ],
            function(Player $submitter, int $selected) use ($guiName, $slot, $lore) : void {
                if ($selected === 0) {
                    $this->editLoreForm($submitter, $guiName, $slot, $lore);
                } else {
                    $this->removeLore($submitter, $guiName, $slot);
                }
            }
        );
        $player->sendForm($form);
    }

    private function addNewLoreForm(Player $player, string $guiName) : void {
        $form = new CustomForm(
            "Add New Lore",
            [
                new Input("title", "Lore Title", "Enter title..."),
                new Input("description", "Lore Description", "Enter description...")
            ],
            function(Player $submitter, CustomFormResponse $response) use ($guiName) : void {
                $title = $response->getString("title");
                $description = $response->getString("description");
                
                if (empty($title)) {
                    $submitter->sendMessage("§cLore title cannot be empty!");
                    return;
                }
                
                // Now open the GUI to select which slot to assign the lore to
                $lore = ["type" => "lore", "title" => $title, "description" => $description];
                $gui = new \NetherByte\Customgui\ui\gui\BlankGridGUI($submitter, $guiName);
                $gui->setPendingLore($submitter, $lore);
                $gui->sendToPlayer();
            }
        );
        $player->sendForm($form);
    }

    private function editLoreForm(Player $player, string $guiName, string $slot, array $lore) : void {
        $title = $lore['title'] ?? '';
        $description = $lore['description'] ?? '';
        
        $form = new CustomForm(
            "Edit Lore",
            [
                new Input("title", "Lore Title", "Enter title...", $title),
                new Input("description", "Lore Description", "Enter description...", $description)
            ],
            function(Player $submitter, CustomFormResponse $response) use ($guiName, $slot) : void {
                $title = $response->getString("title");
                $description = $response->getString("description");
                
                if (empty($title)) {
                    $submitter->sendMessage("§cLore title cannot be empty!");
                    return;
                }
                
                $this->updateLore($submitter, $guiName, $slot, $title, $description);
            }
        );
        $player->sendForm($form);
    }

    private function updateLore(Player $player, string $guiName, string $slot, string $title, string $description) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $guiName . ".json";
        
        if (!file_exists($file)) {
            $player->sendMessage("§cGUI file not found.");
            return;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!isset($data[$slot])) {
            $player->sendMessage("§cSlot not found.");
            return;
        }
        
        if (is_array($data[$slot])) {
            $data[$slot]['lore'] = [
                'title' => $title,
                'description' => $description
            ];
        } else {
            $data[$slot] = [
                'nbt' => $data[$slot],
                'lore' => [
                    'title' => $title,
                    'description' => $description
                ]
            ];
        }
        
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $player->sendMessage("§aLore updated for slot $slot!");
        // Reload the GUI cache immediately
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        if ($plugin !== null) {
            $plugin->reloadGui($guiName);
        }
    }

    private function removeLore(Player $player, string $guiName, string $slot) : void {
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        $dataFolder = $plugin->getGuiDataFolder();
        $file = $dataFolder . $guiName . ".json";
        
        if (!file_exists($file)) {
            $player->sendMessage("§cGUI file not found.");
            return;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!isset($data[$slot]) || !is_array($data[$slot]) || !isset($data[$slot]['lore'])) {
            $player->sendMessage("§cLore not found for slot $slot.");
            return;
        }
        
        unset($data[$slot]['lore']);
        
        // If the slot only has lore and no other data, remove it entirely
        if (count($data[$slot]) === 0) {
            unset($data[$slot]);
        }
        
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $player->sendMessage("§aLore removed from slot $slot!");
        // Reload the GUI cache immediately
        $plugin = \NetherByte\Customgui\Customgui::getInstance();
        if ($plugin !== null) {
            $plugin->reloadGui($guiName);
        }
    }
}