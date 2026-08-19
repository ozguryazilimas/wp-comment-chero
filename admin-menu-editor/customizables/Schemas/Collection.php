<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

abstract class Collection extends CheckableSchema {
	/**
	 * @var Schema
	 */
	protected Schema $keySchema;

	/**
	 * @var Schema
	 */
	protected Schema $itemSchema;

	public function __construct(Schema $itemSchema, ?Schema $keySchema = null, $label = null) {
		parent::__construct($label);
		$this->itemSchema = $itemSchema;
		if ( $keySchema === null ) {
			$this->keySchema = (new Anything());
		} else {
			$this->keySchema = $keySchema;
		}
	}

	public function min($minItems): self {
		return $this->addCheck('minItems', $minItems);
	}

	public function max($maxItems): self {
		return $this->addCheck('maxItems', $maxItems);
	}

	public function parse($value, $errors = null, $stopOnFirstError = false) {
		$value = $this->checkForNull($value, $errors);
		if ( ($value === null) || is_wp_error($value) ) {
			return $value;
		}

		if ( !is_array($value) ) {
			return self::addError($errors, 'collection_value_invalid', 'Collection value must be an array');
		}

		$parsedValues = [];
		$foundErrors = false;
		foreach ($value as $key => $item) {
			$parsedItem = $this->itemSchema->parse($item, $errors, $stopOnFirstError);
			if ( is_wp_error($parsedItem) ) {
				$errors = $parsedItem;
				$foundErrors = true;
				if ( $stopOnFirstError ) {
					break;
				}
			} else {
				$parsedKey = $this->keySchema->parse($key, $errors, $stopOnFirstError);
				if ( is_wp_error($parsedKey) ) {
					$errors = $parsedKey;
					$foundErrors = true;
					if ( $stopOnFirstError ) {
						break;
					}
				} else {
					$parsedValues[$parsedKey] = $parsedItem;
				}
			}
		}

		$itemCount = count($parsedValues);
		foreach ($this->checks as $check) {
			switch ($check['kind']) {
				case 'minItems':
					if ( $itemCount < $check['value'] ) {
						$errors = self::addError(
							$errors,
							'min_items',
							'Collection must have at least ' . $check['value'] . ' items'
						);
						$foundErrors = true;
					}
					break;
				case 'maxItems':
					if ( $itemCount > $check['value'] ) {
						$errors = self::addError(
							$errors,
							'max_items',
							'Collection must have at most ' . $check['value'] . ' items'
						);
						$foundErrors = true;
					}
					break;
			}
		}

		if ( $foundErrors ) {
			return $errors;
		} else {
			return $parsedValues;
		}
	}

	/**
	 * @return Schema
	 */
	public function getKeySchema() {
		return $this->keySchema;
	}

	public function getItemSchema(): Schema {
		return $this->itemSchema;
	}

	public function getChildSchema($key): ?Schema {
		//All items in the collection have the same schema.
		return $this->itemSchema;
	}

	public function serialize(SchemaSerializer $serializer): array {
		return array_merge(
			parent::serialize($serializer),
			[
				'keySchema'  => $serializer->serialize($this->keySchema),
				'itemSchema' => $serializer->serialize($this->itemSchema),
			]
		);
	}
}