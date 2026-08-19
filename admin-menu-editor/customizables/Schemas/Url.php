<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Url extends StringSchema {
	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$parsedValue = parent::parse($value, $errors, $stopOnFirstError);
		if ( ($parsedValue === null) || is_wp_error($parsedValue) ) {
			return $parsedValue;
		}

		//Optionally, allow an empty string.
		if ( ($parsedValue === '') && ($this->getMinLength() === 0) ) {
			return $parsedValue;
		}

		$filteredValue = filter_var($parsedValue, FILTER_VALIDATE_URL);
		if ( $filteredValue === false ) {
			return self::addError($errors, 'invalid_url', 'Value must be a valid URL');

		}

		$convertedValue = esc_url_raw($filteredValue);
		if ( empty($convertedValue) ) {
			//esc_url() documentation says it returns an empty string if the protocol
			//is not one of the allowed protocols, but I'm not 100% sure if that is
			//the *only* situation where it might return an empty string.
			return self::addError($errors, 'invalid_protocol', 'Invalid protocol or a malformed URL');
		}

		return $convertedValue;
	}

	public function getSimplifiedDataType() {
		return 'url';
	}

	protected function getJsonSerializeType(): string {
		return 'url';
	}
}