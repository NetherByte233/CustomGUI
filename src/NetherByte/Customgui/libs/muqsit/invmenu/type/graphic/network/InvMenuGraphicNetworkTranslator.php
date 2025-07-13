<?php

declare(strict_types=1);

namespace NetherByte\Customgui\libs\muqsit\invmenu\type\graphic\network;

use NetherByte\Customgui\libs\muqsit\invmenu\session\InvMenuInfo;
use NetherByte\Customgui\libs\muqsit\invmenu\session\PlayerSession;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;

interface InvMenuGraphicNetworkTranslator{

	public function translate(PlayerSession $session, InvMenuInfo $current, ContainerOpenPacket $packet) : void;
}