<?php

declare(strict_types=1);

namespace NetherByte\Customgui\libs\muqsit\invmenu\type\util\builder;

use NetherByte\Customgui\libs\muqsit\invmenu\type\InvMenuType;

interface InvMenuTypeBuilder{

	public function build() : InvMenuType;
}