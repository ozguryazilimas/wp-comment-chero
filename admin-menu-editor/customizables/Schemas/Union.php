<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Union extends Schema {
	/**
	 * @var Schema[]
	 */
	private array $options;

	public function __construct(array $options, $label = null) {
		$this->options = array_values($options);
		parent::__construct($label);
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$value = $this->checkForNull($value, $errors);
		if ( ($value === null) || is_wp_error($value) ) {
			return $value;
		}

		$collectedErrors = [];
		foreach ($this->options as $index => $schema) {
			$parsedValue = $schema->parse($value, null, $stopOnFirstError);
			if ( is_wp_error($parsedValue) ) {
				$collectedErrors[$index] = $parsedValue;
			} else {
				return $parsedValue;
			}
		}

		$message = 'Value does not match any of the schemas.';
		foreach ($collectedErrors as $index => $error) {
			$message .= "\n" . sprintf('Option %d: %s', $index + 1, $error->get_error_message());
		}

		return self::addError($errors, 'union_value_invalid', $message);
	}

	public function serialize(SchemaSerializer $serializer): array {
		$result = parent::serialize($serializer);
		$result['options'] = array_map(fn($schema) => $serializer->serialize($schema), $this->options);
		return $result;
	}

	protected function getJsonSerializeType(): string {
		return 'union';
	}
}