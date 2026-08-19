<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class StringSchema extends CheckableSchema {
	protected $convertEmptyStringsToNull = true;
	protected $_strict = false;

	public function min($length) {
		$this->addCheck('minLength', $length);
		if ( $length === 0 ) {
			$this->convertEmptyStringsToNull = false;
		}
		return $this;
	}

	public function getMinLength() {
		return $this->getFirstCheckValue('minLength');
	}

	public function max($length, $autoTruncate = false) {
		$params = [];
		if ( $autoTruncate ) {
			$params['truncate'] = true;
		}
		return $this->addCheck('maxLength', $length, $params);
	}

	public function getMaxLength() {
		return $this->getFirstCheckValue('maxLength');
	}

	public function regex($regex, $errorMessage = null, $errorCode = null) {
		return $this->addCheck('regex', $regex, ['errorMessage' => $errorMessage, 'errorCode' => $errorCode]);
	}

	public function trim() {
		return $this->addCheck('trim');
	}

	public function stripTags() {
		return $this->addCheck('stripTags');
	}

	/**
	 * Make the schema strict, meaning that the value must be a string.
	 *
	 * By default, the schema will attempt to convert non-string values to strings before parsing.
	 * Call strict() to disable this behavior.
	 *
	 * @return $this
	 */
	public function strict() {
		$this->_strict = true;
		return $this;
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$value = $this->checkForNull($value, $errors);
		if ( ($value === null) || is_wp_error($value) ) {
			return $value;
		}

		if ( $this->_strict && !is_string($value) ) {
			return self::addError($errors, 'not_string', 'Value must be a string');
		}

		if ( is_array($value) ) {
			return self::addError($errors, 'not_string', 'Value must be a string, not an array');
		}

		$convertedValue = strval($value);
		$hasErrors = false;

		foreach ($this->checks as $check) {
			switch ($check['kind']) {
				case 'minLength':
					if ( strlen($convertedValue) < $check['value'] ) {
						$errors = self::addError(
							$errors,
							'min_length',
							'Value is too short, minimum length is ' . $check['value']
						);
						$hasErrors = true;
					}
					break;
				case 'maxLength':
					if ( strlen($convertedValue) > $check['value'] ) {
						if ( !empty($check['params']['truncate']) ) {
							$convertedValue = substr($convertedValue, 0, $check['value']);
						} else {
							$errors = self::addError(
								$errors,
								'max_length',
								'Value is too long, maximum length is ' . $check['value']
							);
							$hasErrors = true;
						}
					}
					break;
				case 'regex':
					if ( !preg_match($check['value'], $convertedValue) ) {
						$errors = self::addError(
							$errors,
							'regex_match_failed',
							'Value must match the following regex: ' . $check['value'],
							$check
						);
						$hasErrors = true;
					}
					break;
				case 'trim':
					$convertedValue = trim($convertedValue);
					break;
				case 'stripTags':
					$convertedValue = wp_strip_all_tags($convertedValue);
					break;
			}

			if ( $stopOnFirstError && $errors && $hasErrors ) {
				return $errors;
			}
		}

		if ( $errors && $hasErrors ) {
			return $errors;
		}

		return $convertedValue;
	}

	public function isStringConversionSafe() {
		return true;
	}

	public function getSimplifiedDataType() {
		return 'string';
	}

	public function serialize(SchemaSerializer $serializer): array {
		$result = parent::serialize($serializer);
		if ( $this->_strict ) {
			$result['strict'] = true;
		}
		return $result;
	}

	protected function getJsonSerializeType(): string {
		return 'string';
	}
}