<?php

declare(strict_types=1);

namespace NetherByte\Customgui\libs\muqsit\invmenu\session;

use NetherByte\Customgui\libs\muqsit\invmenu\InvMenu;
use NetherByte\Customgui\libs\muqsit\invmenu\type\graphic\InvMenuGraphic;

final class InvMenuInfo{

	public function __construct(
		readonly public InvMenu $menu,
		readonly public InvMenuGraphic $graphic,
		readonly public ?string $graphic_name
	){}
}