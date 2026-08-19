<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Anything extends Schema {
	public function parse($value, $errors = null, $stopOnFirstError = false) {
		return $value;
	}

	protected function getJsonSerializeType(): string {
		return 'any';
	}
}