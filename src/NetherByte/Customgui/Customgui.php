<?php
declare(strict_types = 1);

namespace NetherByte\Customgui;

use Generator;
use NetherByte\Customgui\libs\muqsit\invmenu\InvMenuHandler;
use NetherByte\Customgui\command\CustomguiCommand;
use NetherByte\Customgui\guicore\GuiManager;
use NetherByte\Customgui\database\Database;
use NetherByte\Customgui\database\GuiStorage;
use NetherByte\Customgui\ui\forms\MainForm;
use NetherByte\Customgui\ui\gui\ViewCustomGUI;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\player\Player;
use NetherByte\Customgui\libs\SOFe\AwaitGenerator\Await;

class Customgui extends PluginBase implements Listener {
    private static ?Customgui $instance = null;
    private GuiManager $guiManager;
    private GuiStorage $guiStorage;
    /** @var array<string, array> */
    private array $guiCache = [];
    /** @var array<string, array<string, array>> */
    private array $playerGuiCache = [];

    public static function getInstance() : Customgui {
        return self::$instance;
    }

    protected function onLoad() : void{
        self::$instance = $this;
    }

    protected function onEnable() : void{
        $this->saveResource("config.yml");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register("customgui", new \NetherByte\Customgui\command\CustomguiCommand());
        InvMenuHandler::register($this);
        $this->guiManager = new GuiManager();
        $this->guiStorage = new GuiStorage($this);
        
        // Preload all GUI data for server-wide access
        $this->preloadAllGuis();
        
        // Load recipes from database
        Await::f2c(function() : Generator{
            yield from $this->guiStorage->asyncLoad();
            $this->guiManager->setReady();
        });
    }

    protected function onDisable() : void{
        $this->guiStorage->close();
    }

    /**
     * Preload all GUI data from JSON files into memory
     */
    private function preloadAllGuis() : void {
        $dataFolder = $this->findGuiDirectory();
        if ($dataFolder === null) {
            return;
        }
        
        $files = glob($dataFolder . "*.json");
        
        $loadedCount = 0;
        foreach ($files as $file) {
            $guiName = strval(basename($file, ".json"));
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->guiCache[$guiName] = $data;
                    $loadedCount++;
                }
            }
        }
    }

    /**
     * Get cached GUI data (server-wide cache)
     */
    public function getCachedGui(string $guiName) : ?array {
        return $this->guiCache[$guiName] ?? null;
    }

    /**
     * Get cached GUI data for a specific player
     */
    public function getPlayerCachedGui(string $playerName, string $guiName) : ?array {
        return $this->playerGuiCache[$playerName][$guiName] ?? null;
    }

    /**
     * Get all available GUI names (server-wide cache)
     */
    public function getAvailableGuis() : array {
        return array_map('strval', array_keys($this->guiCache));
    }

    /**
     * Get all available GUI names for a specific player
     */
    public function getPlayerAvailableGuis(string $playerName) : array {
        return isset($this->playerGuiCache[$playerName]) ? array_map('strval', array_keys($this->playerGuiCache[$playerName])) : [];
    }

    /**
     * Reload a specific GUI into server cache
     */
    public function reloadGui(string $guiName) : void {
        $dataFolder = $this->findGuiDirectory();
        if ($dataFolder === null) {
            return;
        }
        
        $file = $dataFolder . $guiName . ".json";
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->guiCache[strval($guiName)] = $data;
                }
            }
        }
    }

    /**
     * Reload a specific GUI for a specific player
     */
    public function reloadPlayerGui(string $playerName, string $guiName) : void {
        $dataFolder = $this->findGuiDirectory();
        if ($dataFolder === null) {
            return;
        }
        
        $file = $dataFolder . $guiName . ".json";
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->playerGuiCache[$playerName][strval($guiName)] = $data;
                }
            }
        }
    }

    /**
     * Reload all GUIs for a specific player
     */
    public function reloadPlayerAllGuis(string $playerName) : void {
        $this->loadPlayerGuiCache($playerName);
    }

    public function getGuiManager() : GuiManager{
        return $this->guiManager;
    }

    public function getGuiStorage() : GuiStorage{
        return $this->guiStorage;
    }

    /**
     * Handle player join to load per-player GUI cache
     */
    public function onPlayerJoin(\pocketmine\event\player\PlayerJoinEvent $event) : void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Load GUI cache for this specific player
        $this->loadPlayerGuiCache($playerName);
    }

    /**
     * Handle player quit to clean up their cache
     */
    public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event) : void {
        $playerName = $event->getPlayer()->getName();
        
        // Clean up player's GUI cache
        if (isset($this->playerGuiCache[$playerName])) {
            unset($this->playerGuiCache[$playerName]);
        }
    }

    /**
     * Load GUI cache for a specific player
     */
    private function loadPlayerGuiCache(string $playerName) : void {
        $dataFolder = $this->findGuiDirectory();
        if ($dataFolder === null) {
            return;
        }
        
        $files = glob($dataFolder . "*.json");
        $playerCache = [];
        
        foreach ($files as $file) {
            $guiName = strval(basename($file, ".json"));
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $playerCache[$guiName] = $data;
                }
            }
        }
        
        $this->playerGuiCache[$playerName] = $playerCache;
    }

    /**
     * Find the GUI directory using multiple possible paths
     */
    private function findGuiDirectory() : ?string {
        $pluginFile = $this->getFile();
        $pluginDir = dirname($pluginFile);
        
        $possiblePaths = [
            $pluginDir . "/resources/guis/",
            dirname($pluginDir) . "/resources/guis/",
            $this->getDataFolder() . "/../resources/guis/",
            dirname(__DIR__, 3) . "/resources/guis/"
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }
        
        return null;
    }
}