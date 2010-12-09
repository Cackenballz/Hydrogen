<?php
/*
 * Copyright (c) 2009 - 2010, Frosted Design
 * All rights reserved.
 */

namespace hydrogen\view\engines\hydrogen;

use hydrogen\view\TemplateEngine;
use hydrogen\view\engines\hydrogen\Parser;

class HydrogenEngine extends TemplateEngine {

	public static function getPHP($templateName, $loader) {
		$parser = new Parser($templateName, $loader);
		$nodes = $parser->parse();
		return $nodes->render();
	}

}

?>