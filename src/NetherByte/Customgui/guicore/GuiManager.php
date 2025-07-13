<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\guicore;

use Closure;
use Generator;
use NetherByte\Customgui\Customgui;
use NetherByte\Customgui\libs\_6c9046632b65a4c3\SOFe\AwaitGenerator\Await;

class GuiManager {

    /** @var GuiLayout[] */
    private array $layouts = [];
    /**
     * @var bool
     * @description This method use for blocking player from guicore item before all recipes are loaded or updated
     * due to async nature of database
     */
    protected bool $ready = false;

    public function __construct(
    ){}

    public function addGuiLayout(GuiLayout $layout, $sync_with_db = true, Closure $callback = null) : void{
        $this->recipes[$layout->getName()] = $layout;
        if($sync_with_db){
            $this->setReady(false);
            Await::f2c(function() use ($layout, $callback) : Generator{
                yield from Customgui::getInstance()->getDatabase()->asyncAdd($layout);
                $this->setReady();
                if ($callback !== null){
                    $callback();
                }
            });
        }
    }

    public function removeGuiLayout(string $name, $sync_with_db = true, Closure $callback = null) : void{
        if(!isset($this->recipes[$name])){
            return;
        }
        unset($this->recipes[$name]);
        if($sync_with_db){
            $this->setReady(false);
            Await::f2c(function() use ($name, $callback) : Generator{
                yield from Customgui::getInstance()->getDatabase()->asyncDelete($name);
                $this->setReady();
                if ($callback !== null){
                    $callback();
                }
            });
        }
    }

    public function updateGuiLayout(GuiLayout $layout, $sync_with_db = true, Closure $callback = null) : void{
        $this->recipes[$layout->getName()] = $layout;
        $this->setReady(false);
        if($sync_with_db){
            Await::f2c(function() use ($layout, $callback) : Generator{
                yield from Customgui::getInstance()->getDatabase()->asyncUpdate($layout);
                $this->setReady();
                if ($callback !== null){
                    $callback();
                }
            });
        }
    }

    public function matchGuiGrid(GuiGrid $grid) : ?GuiLayout{
        foreach($this->recipes as $layout){
            if($layout->matchGuiGrid($grid)){
                return $layout;
            }
        }
        return null;
    }

    public function getGuiLayout(string $name) : ?GuiLayout{
        return $this->recipes[$name] ?? null;
    }

    public function getGuiLayouts() : array{
        return $this->recipes;
    }

    public function isReady() : bool{
        return $this->ready;
    }

    public function setReady(bool $ready = true) : void{
        $this->ready = $ready;
    }
}