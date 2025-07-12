<?php
declare(strict_types = 1);

namespace NetherByte\Customgui\database;

use Generator;
use NetherByte\Customgui\guicore\GuiLayout;
use NetherByte\Customgui\database\DatabaseStmts as Stmts;
use NetherByte\Customgui\Customgui;
use NetherByte\Customgui\utils\NbtHelper;
use NetherByte\Customgui\libs\poggit\libasynql\DataConnector;
use NetherByte\Customgui\libs\poggit\libasynql\libasynql;
use NetherByte\Customgui\libs\SOFe\AwaitGenerator\Await;

final class GuiStorage {

    private DataConnector $database;
    private BinaryStringParser $parser;

    public function __construct(){
        Await::f2c(function() : Generator{
            yield from $this->asyncInit();
        });
        //TODO: Handle errors
    }

    public function asyncInit() : Generator{
        $configurations = Customgui::getInstance()->getConfig()->get("database");
        $type = $configurations["type"];
        $this->parser = BinaryStringParser::fromDatabase($type);
        $this->database = libasynql::create(Customgui::getInstance(), $configurations, ["sqlite" => "sql/sqlite.sql", "mysql" => "sql/mysql.sql"]);
        yield from $this->database->asyncGeneric(Stmts::INIT);
    }

    public function asyncLoad() : Generator{
        $result = yield from $this->database->asyncSelect(Stmts::LOAD);
        foreach($result as $row){
            $data = $this->parser->decode($row["data"]);
            $nbt = NbtHelper::decompressCompoundTag($data);
            $layout = GuiLayout::nbtDeserialize($nbt);
            Customgui::getInstance()->getGuiManager()->addGuiLayout($layout, false);
        }
    }

    public function asyncAdd(GuiLayout $layout) : Generator{
        $nbt = $layout->nbtSerialize();
        $data = NbtHelper::compressCompoundTag($nbt);
        yield from $this->database->asyncInsert(Stmts::ADD, ["name" => $layout->getName(), "data" => $this->parser->encode($data)]);
    }

    public function asyncUpdate(GuiLayout $layout) : Generator{
        $nbt = $layout->nbtSerialize();
        $data = NbtHelper::compressCompoundTag($nbt);
        yield from $this->database->asyncChange(Stmts::UPDATE, ["name" => $layout->getName(), "data" => $this->parser->encode($data)]);
    }

    public function asyncDelete(string $name) : Generator{
        yield from $this->database->asyncChange(Stmts::DELETE, ["name" => $name]);
    }

    public function close() : void{
        if(isset($this->database)){
            $this->database->waitAll();
            $this->database->close();
        }
    }
}