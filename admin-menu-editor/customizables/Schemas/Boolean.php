<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Boolean extends Schema {
	protected $_strict = false;

	/**
	 * Make the schema strict, meaning that the value must be a boolean.
	 *
	 * By default, the schema will try to parse "on", "off", "true", "false", etc. as booleans,
	 * and will convert truthy and falsy values to true and false. Call strict() to disable this
	 * behavior.
	 *
	 * @return $this
	 */
	public function strict() {
		$this->_strict = true;
		return $this;
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$convertedValue = $this->checkForNull($value, $errors);
		if ( ($convertedValue === null) || is_wp_error($convertedValue) ) {
			return $convertedValue;
		}

		if ( $this->_strict ) {
			if ( is_bool($convertedValue) ) {
				return $convertedValue;
			}
			return self::addError($errors, 'not_boolean', 'Value must be a boolean (only true or false)');
		}

		$convertedValue = $this->tryConvertToBool($convertedValue);
		if ( $convertedValue === null ) {
			$errors->add('not_boolean', 'Value must be a boolean');
			return $errors;
		}
		return $convertedValue;
	}

	/**
	 * @param mixed $value
	 * @return bool|null
	 */
	protected function tryConvertToBool($value) {
		if ( is_string($value) ) {
			//Handle values like "on", "off", "false", etc.
			return filter_var(strtolower($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		} else if ( $value !== null ) {
			return boolval($value);
		}
		return null;
	}

	public function isStringConversionSafe() {
		return true;
	}

	public function getSimplifiedDataType() {
		return 'boolean';
	}

	public function serialize(SchemaSerializer $serializer): array {
		$result = parent::serialize($serializer);
		if ( $this->_strict ) {
			$result['strict'] = true;
		}
		return $result;
	}


	protected function getJsonSerializeType(): string {
		return 'boolean';
	}
}