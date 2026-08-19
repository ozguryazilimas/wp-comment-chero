<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Number extends CheckableSchema {
	protected $convertEmptyStringsToNull = true;

	public function __construct($label = null) {
		parent::__construct($label);
		$this->defaultValue(0);
	}

	public function min($value) {
		return $this->addCheck('min', $value);
	}

	public function max($value) {
		return $this->addCheck('max', $value);
	}

	public function getMin() {
		return $this->getFirstCheckValue('min');
	}

	public function getMax() {
		return $this->getFirstCheckValue('max');
	}

	public function int() {
		return $this->addCheck('int');
	}

	public function isInt() {
		$check = $this->findFirstCheck('int');
		return ($check !== null);
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$value = $this->checkForNull($value, $errors);
		if ( ($value === null) || is_wp_error($value) ) {
			return $value;
		}

		if ( !is_numeric($value) ) {
			return self::addError($errors, 'not_numeric', 'Value must be a number');
		}

		$numValue = floatval($value);
		$hasErrors = false;

		foreach ($this->checks as $check) {
			switch ($check['kind']) {
				case 'min':
					if ( $numValue < $check['value'] ) {
						$errors = self::addError($errors, 'min_value', 'Value must be ' . $check['value'] . ' or greater');
						$hasErrors = true;
					}
					break;
				case 'max':
					if ( $numValue > $check['value'] ) {
						$errors = self::addError($errors, 'max_value', 'Value must be ' . $check['value'] . ' or less');
						$hasErrors = true;
					}
					break;
				case 'int':
					if ( $numValue !== floor($numValue) ) {
						$errors = self::addError($errors, 'not_integer', 'Value must be an integer');
						$hasErrors = true;
					} else {
						$numValue = intval($value);
					}
					break;
			}

			if ( $stopOnFirstError && $hasErrors ) {
				return $errors;
			}
		}

		if ( $errors && $hasErrors ) {
			return $errors;
		}
		return $numValue;
	}

	public function isStringConversionSafe() {
		return true;
	}

	public function serializeValidationRules() {
		$result = parent::serializeValidationRules();

		if ( $this->isNullable() ) {
			$result['isNullable'] = true;
			$result['convertEsToNull'] = $this->convertEmptyStringsToNull;
		}

		if ( !isset($result['parsers']) ) {
			$result['parsers'] = [];
		}

		$numericParserConfig = [];
		//Find the lowest and highest values from the checks.
		$min = null;
		$max = null;
		foreach ($this->checks as $check) {
			switch ($check['kind']) {
				case 'min':
					if ( ($min === null) || ($check['value'] < $min) ) {
						$min = $check['value'];
					}
					break;
				case 'max':
					if ( ($max === null) || ($check['value'] > $max) ) {
						$max = $check['value'];
					}
					break;
			}
		}

		if ( $min !== null ) {
			$numericParserConfig['min'] = $min;
		}
		if ( $max !== null ) {
			$numericParserConfig['max'] = $max;
		}
		$result['parsers'][] = ['numeric', $numericParserConfig];

		if ( $this->isInt() ) {
			$result['parsers'][] = ['int'];
		}

		return $result;
	}

	public function getSimplifiedDataType() {
		if ($this->isInt()) {
			return 'integer';
		} else {
			return 'float';
		}
	}

	protected function getJsonSerializeType(): string {
		return 'number';
	}
}