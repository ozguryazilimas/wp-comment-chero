<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Placeholder extends Schema {
	public function parse($value, $errors = null, $stopOnFirstError = false) {
		/** @noinspection PhpUnhandledExceptionInspection -- Intentional exception for placeholder schema */
		throw new \Exception('Placeholder schema should not be used for parsing');
	}

	protected function getJsonSerializeType(): string {
		return 'placeholder';
	}
}