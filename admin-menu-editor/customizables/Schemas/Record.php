<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class Record extends Collection {
	public function __construct(Schema $keySchema, Schema $itemSchema, $label = null) {
		//Keys must be strings. An enum with only string values is also acceptable.
		$keysAreStrings = ($keySchema instanceof StringSchema)
			|| (($keySchema instanceof Enum) && $keySchema->areAllValuesStrings());
		if ( !$keysAreStrings ) {
			throw new \InvalidArgumentException('Key schema for Record must be a string schema or an enum with string values.');
		}

		parent::__construct($itemSchema, $keySchema, $label);
		$this->keySchema = $keySchema;
	}

	public function getSimplifiedDataType() {
		return 'map'; //Helps ensure that empty records are serialized as {} instead of [].
	}

	protected function getJsonSerializeType(): string {
		return 'record';
	}
}