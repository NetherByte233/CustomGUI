<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\guicore;

use InvalidArgumentException;
use LogicException;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use RuntimeException;

/** @note a bit modified based on GuiGrid class in PMMP */
class GuiGrid {

    /**
     * @var $items array<int, array<int, Item>>
     */
    protected array $items = [];
    protected ?int $startX = null;
    protected ?int $startY = null;
    protected ?int $xLen = null;
    protected ?int $yLen = null;

    public function __construct(
        protected int $gridSize
    ){}

    public function getGuiLayoutWidth() : int{
        return $this->xLen ?? 0;
    }

    public function getGuiLayoutHeight() : int{
        return $this->yLen ?? 0;
    }

    public function getGridSize() : int{
        return $this->gridSize;
    }

    public function getItem(int $x, int $y) : ?Item{
        if ($x < 0 || $x >= $this->gridSize || $y < 0 || $y >= $this->gridSize){
            throw new InvalidArgumentException("Invalid coordinate");
        }
        return $this->items[$y][$x] ?? null;
    }

    public function getIngredient(int $x, int $y) : Item{
        if ($this->startX !== null && $this->startY !== null){
            return $this->getItem($this->startX + $x, $this->startY + $y) ?? VanillaItems::AIR();
        }
        throw new LogicException("No Guielements in grid");
    }

    public function takeIngredients(GuiLayout $layout) : void{
        if (!$layout->matchGuiGrid($this)){
            throw new RuntimeException("Grid does not match recipe");
        }

        for($y = 0; $y < $layout->getHeight(); ++$y){
            for($x = 0; $x < $layout->getWidth(); ++$x){
                $Guielement = $layout->getIngredient($x, $y);
                if ($Guielement === null){
                    continue;
                }
                $Guielement->consume($this->getIngredient($x, $y));
            }
        }
        $this->seekGuiLayoutBounds();
    }

    public function setItem(int $x, int $y, Item $item, bool $seekGuiLayoutBounds = true) : void{
        if ($x < 0 || $x >= $this->gridSize || $y < 0 || $y >= $this->gridSize){
            throw new InvalidArgumentException("Invalid coordinate");
        }
        $this->items[$y][$x] = $item;
        if ($seekGuiLayoutBounds){
            $this->seekGuiLayoutBounds();
        }
    }

    public function isSlotEmpty(int $x, int $y) : bool{
        if ($x < 0 || $x >= $this->gridSize || $y < 0 || $y >= $this->gridSize){
            throw new InvalidArgumentException("Invalid coordinate");
        }
        if (!isset($this->items[$y][$x])){
            return true;
        }
        if ($this->items[$y][$x]->isNull()){
            return true;
        }
        return false;
    }

    public function seekGuiLayoutBounds() : void{
        $minX = PHP_INT_MAX;
        $maxX = 0;
        $minY = PHP_INT_MAX;
        $maxY = 0;
        $empty = true;
        for ($y = 0; $y < $this->gridSize; ++$y){
            for ($x = 0; $x < $this->gridSize; ++$x){
                if (!$this->isSlotEmpty($x, $y)){
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                    $empty = false;
                }
            }
        }
        if (!$empty){
            $this->startX = $minX;
            $this->xLen = $maxX - $minX + 1;
            $this->startY = $minY;
            $this->yLen = $maxY - $minY + 1;
        }else{
            $this->startX = $this->startY = $this->xLen = $this->yLen = 0;
        }
    }

    /**
     * @return array<int, array<int, Item>>
     */
    public function getItems() : array{
        return $this->items;
    }
}