<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

/**
 * Enum schema
 *
 * Note that if you want to allow NULL, it must be explicitly included as one
 * of the possible values. Only setting the default value to NULL is not enough.
 */
class Enum extends Schema {
	protected $convertEmptyStringsToNull = true;
	/**
	 * @var array|null
	 */
	protected $enumValues = null;
	/**
	 * @var callable|null
	 */
	protected $enumValueCallback = null;
	protected $valueDetails = [];
	protected $cachedStringConversionSafe = null;
	protected $cachedAllValuesAreStrings = null;

	public function values(array $values): self {
		$this->enumValues = array_values($values);
		$this->enumValueCallback = null;
		$this->onValuesChanged();

		return $this;
	}

	public function valueCallback(callable $callback): self {
		$this->enumValueCallback = $callback;
		$this->enumValues = null;
		$this->onValuesChanged();

		return $this;
	}

	protected function onValuesChanged() {
		$this->cachedStringConversionSafe = null;
		$this->cachedAllValuesAreStrings = null;

		if (
			is_array($this->enumValues)
			&& !empty($this->enumValues)
			&& !in_array($this->getDefaultValue(), $this->enumValues)
		) {
			$this->defaultValue(reset($this->enumValues));
		}
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$convertedValue = $this->checkForNull($value, $errors);
		if ( ($convertedValue === null) || is_wp_error($convertedValue) ) {
			return $convertedValue;
		}

		$enumValues = $this->getEnumValues();
		if ( !in_array($value, $enumValues) ) {
			return self::addError(
				$errors,
				'invalid_value',
				'Value must be one of: ' . implode(', ', $enumValues)
				. '. Received: ' . wp_json_encode($value)
			);
		}

		if ( !$this->isValueEnabled($value) ) {
			return self::addError($errors, 'disabled_value', 'That option is currently not allowed');
		}

		return $value;
	}

	public function isStringConversionSafe() {
		if ( $this->cachedStringConversionSafe === null ) {
			$this->cachedStringConversionSafe = true;

			$lastType = null;

			foreach ($this->getEnumValues() as $value) {
				if ( !is_string($value) && !is_numeric($value) ) {
					$this->cachedStringConversionSafe = false;
					break;
				}

				//All values must be of the same type. Otherwise, we can't distinguish between
				//numbers and strings that contain numbers.
				$thisType = gettype($value);
				if ( ($lastType !== null) && ($thisType !== $lastType) ) {
					$this->cachedStringConversionSafe = false;
					break;
				}
				$lastType = $thisType;
			}

		}

		return $this->cachedStringConversionSafe;
	}

	public function areAllValuesStrings(): bool {
		if ( $this->cachedAllValuesAreStrings !== null ) {
			return $this->cachedAllValuesAreStrings;
		}

		$this->cachedAllValuesAreStrings = true;
		foreach ($this->getEnumValues() as $value) {
			if ( !is_string($value) ) {
				$this->cachedAllValuesAreStrings = false;
				break;
			}
		}
		return $this->cachedAllValuesAreStrings;
	}

	public function getEnumValues(): array {
		if ( is_array($this->enumValues) ) {
			return $this->enumValues;
		}

		if ( is_callable($this->enumValueCallback) ) {
			$valuesAndDetails = call_user_func($this->enumValueCallback);
			if ( is_array($valuesAndDetails) ) {
				$values = [];
				foreach ($valuesAndDetails as $pair) {
					if ( is_array($pair) ) {
						$values[] = $pair[0];
						if ( isset($pair[1]) && is_array($pair[1]) ) {
							$this->describeValue(
								$pair[0],
								$pair[1]['label'] ?? esc_html($pair[0]),
								$pair[1]['description'] ?? '',
								$pair[1]['enabled'] ?? null,
								$pair[1]['icon'] ?? null
							);
						}
					} else {
						$values[] = $pair;
					}
				}
				return $values;
			}
		}

		return [];
	}

	public function describeValue($value, $label, $description = '', $enabled = null, $icon = null): self {
		$safeValue = wp_json_encode($value);
		$this->valueDetails[$safeValue] = [
			'label'       => $label,
			'description' => $description,
			'enabled'     => $enabled,
			'icon'        => $icon,
		];
		return $this;
	}

	public function getValueDetails($value) {
		$safeValue = wp_json_encode($value);
		return $this->valueDetails[$safeValue] ?? null;
	}

	public function isValueEnabled($value) {
		if ( !in_array($value, $this->getEnumValues(), true) ) {
			return false;
		}

		$safeValue = wp_json_encode($value);
		if ( !isset($this->valueDetails[$safeValue]['enabled']) ) {
			return true; //All values are enabled by default.
		}

		$decider = $this->valueDetails[$safeValue]['enabled'];
		if ( is_scalar($decider) ) {
			return (bool)$decider;
		} elseif ( is_callable($decider) ) {
			return call_user_func($decider, $value);
		}

		return true;
	}

	public function serialize(SchemaSerializer $serializer): array {
		$result = parent::serialize($serializer);
		$result['values'] = $this->getEnumValues();
		if ( !empty($this->valueDetails) ) {
			$result['valueDetails'] = $this->valueDetails;
		}
		return $result;
	}

	protected function getJsonSerializeType(): string {
		return 'enum';
	}
}