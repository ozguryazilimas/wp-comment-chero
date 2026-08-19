<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class PlaceholderStruct extends Struct {
	public function __construct($label = null) {
		parent::__construct([], $label);
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		/** @noinspection PhpUnhandledExceptionInspection -- Intentional exception for placeholder schema */
		throw new \Exception('Placeholder schema should not be used for parsing');
	}

	protected function getJsonSerializeType(): string {
		return 'placeholderStruct';
	}
}