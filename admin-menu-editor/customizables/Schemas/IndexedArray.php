<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class IndexedArray extends Collection {
	public function __construct(Schema $itemSchema, $label = null) {
		parent::__construct($itemSchema, new Number(), $label);
	}

	protected function getJsonSerializeType(): string {
		return 'array';
	}
}