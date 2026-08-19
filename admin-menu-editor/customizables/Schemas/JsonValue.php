<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class JsonValue extends Schema {
	/**
	 * @var Schema
	 */
	protected $parsedValueSchema;

	public function __construct(Schema $parsedValueSchema, $label = null) {
		parent::__construct($label);
		$this->parsedValueSchema = $parsedValueSchema;
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$value = $this->checkForNull($value, $errors);
		if ( ($value === null) || is_wp_error($value) ) {
			return $value;
		}

		if ( !is_string($value) ) {
			return self::addError($errors, 'not_string', 'Value must be a string');
		}

		$parsedValue = json_decode($value, true);
		if ( ($parsedValue === null) && (json_last_error() !== JSON_ERROR_NONE) ) {
			return self::addError($errors, 'invalid_json', 'Invalid JSON: ' . json_last_error_msg());
		}

		return $this->parsedValueSchema->parse($parsedValue, $errors, $stopOnFirstError);
	}

	protected function getJsonSerializeType(): string {
		return 'jsonValue';
	}
}