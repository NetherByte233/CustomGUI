<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\guicore;

use InvalidArgumentException;
use NetherByte\Customgui\guicore\Guielement\EmptyGuiLayoutIngredient;
use NetherByte\Customgui\guicore\Guielement\NormalGuiLayoutIngredient;
use NetherByte\Customgui\guicore\Guielement\GuiLayoutIngredient;
use NetherByte\Customgui\guicore\result\GuiLayoutResult;
use NetherByte\Customgui\utils\NbtSerializable;
use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;

class GuiLayout implements NbtSerializable {

    public function __construct(
        protected string $name,
        protected int $width,
        protected int $height,
        /** @var $Guielements GuiLayoutIngredient[] */
        protected array $Guielements,
        protected GuiLayoutResult $result
    ){}

    public function getName() : string{
        return $this->name;
    }

    public function getWidth() : int{
        return $this->width;
    }

    public function getHeight() : int{
        return $this->height;
    }

    public function getIngredient(int $x, int $y) : ?GuiLayoutIngredient{
        if($x > $this->width || $x < 0 || $y > $this->height || $y < 0){
            throw new InvalidArgumentException("Invalid coordinate");
        }
        if(!isset($this->Guielements[$y][$x])){
            return null;
        }
        return $this->Guielements[$y][$x];
    }

    public function setIngredient(int $x, int $y, GuiLayoutIngredient $Guielement) : void{
        if($x > $this->width || $x < 0 || $y > $this->height || $y < 0){
            throw new InvalidArgumentException("Invalid coordinate");
        }
        $this->Guielements[$y][$x] = $Guielement;
    }

    /**
     * @return array<int, array<int, Item>>
     */
    public function getIngredients() : array{
        return $this->Guielements;
    }

    public function getResult() : GuiLayoutResult{
        return $this->result;
    }

    public function setResult(GuiLayoutResult $result) : void{
        $this->result = $result;
    }

    /** @note a bit modified copy-pasta from PMMP code because it's good */
    public function matchInputMap(GuiGrid $grid, $reverse = false) : bool{
        for ($y = 0; $y < $this->height; $y++){
            for ($x = 0; $x < $this->width; $x++){
                $given = $grid->getIngredient($reverse ? $this->width - $x - 1 : $x, $y);
                $required = $this->getIngredient($x, $y);

                if ($required === null){
                    if (!$given->isNull()){
                        return false;
                    }
                }elseif(!$required->accept($given)){
                    return false;
                }
            }
        }
        return true;
    }

    public function matchGuiGrid(GuiGrid $grid) : bool{
        if ($grid->getGuiLayoutWidth() !== $this->width || $grid->getGuiLayoutHeight() !== $this->height){
            return false;
        }
        return $this->matchInputMap($grid) || $this->matchInputMap($grid, true);
    }

    public function nbtSerialize() : CompoundTag{
        $ctag = new CompoundTag();
        $ctag->setString("name", $this->name);
        $ctag->setInt("width", $this->width);
        $ctag->setInt("height", $this->height);
        $ctag->setTag("result", $this->result->nbtSerialize());
        $Guielements = [];
        foreach($this->Guielements as $row){
            foreach($row as $item){
                $Guielements[] = $item->nbtSerialize();
            }
        }
        $itag = new ListTag($Guielements, NBT::TAG_Compound);
        $ctag->setTag("Guielements", $itag);
        return $ctag;
    }

    public static function nbtDeserialize(CompoundTag $tag) : self{
        $name = $tag->getString("name");
        $width = $tag->getInt("width");
        $height = $tag->getInt("height");
        $result = GuiLayoutResult::nbtDeserialize($tag->getCompoundTag("result"));
        $Guielements = [];
        $itag = $tag->getListTag("Guielements");
        $data = $itag->getValue();
        $index = 0;
        for($y = 0; $y < $height; ++$y){
            for($x = 0; $x < $width; ++$x){
                if($data[$index] instanceof CompoundTag){
                    $Guielement_type = $data[$index]->getString("type");
                    $Guielements[$y][$x] = match ($Guielement_type) {
                        "normal" => NormalGuiLayoutIngredient::nbtDeserialize($data[$index]),
                        "empty" => new EmptyGuiLayoutIngredient(),
                        default => null
                    };
                }
                $index++;
            }
        }
        return new self($name, $width, $height, $Guielements, $result);
    }

    public static function fromGuiGrid(string $name, GuiGrid $grid, GuiLayoutResult $result) : self{
        $Guielements = [];
        for($y = 0; $y < $grid->getGuiLayoutHeight(); ++$y){
            for($x = 0; $x < $grid->getGuiLayoutWidth(); ++$x){
                $item = $grid->getIngredient($x, $y);
                if ($item->isNull()){
                    $Guielements[$y][$x] = new EmptyGuiLayoutIngredient();
                    continue;
                }
                $Guielements[$y][$x] = new NormalGuiLayoutIngredient($grid->getIngredient($x, $y));
            }
        }
        return new self($name, $grid->getGuiLayoutWidth(), $grid->getGuiLayoutHeight(), $Guielements, $result);
    }
}