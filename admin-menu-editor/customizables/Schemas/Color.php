<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Color extends StringSchema {
	protected $transparentAllowed = false;

	public function __construct(...$args) {
		parent::__construct(...$args);

		$this->strict()->trim()->regex(
		/** @lang RegExp */
			'/^#(?:[\da-f]{6}|[\da-f]{3})$/i',
			'Value must be a valid CSS hex color',
			'invalid_hex_color'
		);
	}

	public function orTransparent() {
		$this->transparentAllowed = true;
		return $this;
	}

	public function noTransparent() {
		$this->transparentAllowed = false;
		return $this;
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		//An empty string is explicitly allowed.
		if ( $value === '' ) {
			return $value;
		}
		//For CSS colors, the "transparent" keyword can also be used.
		if ( $this->transparentAllowed && ($value === 'transparent') ) {
			return $value;
		}

		return parent::parse($value, $errors, $stopOnFirstError);
	}

	public function getSimplifiedDataType() {
		return 'color';
	}

	public function serialize(SchemaSerializer $serializer): array {
		$result = parent::serialize($serializer);
		if ( $this->transparentAllowed ) {
			$result['transparentAllowed'] = $this->transparentAllowed;
		}
		return $result;
	}

	protected function getJsonSerializeType(): string {
		return 'color';
	}
}