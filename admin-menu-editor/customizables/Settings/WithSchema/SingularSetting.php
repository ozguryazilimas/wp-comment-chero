<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema;

use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Schema;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

class SingularSetting extends Settings\AbstractSetting implements SettingWithSchema {
	/**
	 * @var Schema
	 */
	protected $schema;

	public function __construct(Schema $schema, $id = '', ?StorageInterface $store = null, $params = []) {
		$this->schema = $schema;
		parent::__construct($id, $store, $params);
	}

	public function validate($errors, $value, $stopOnFirstError = false) {
		return $this->schema->parse($value, $errors, $stopOnFirstError);
	}

	public function getValue($customDefault = null) {
		$currentDefault = ($customDefault !== null) ? $customDefault : $this->getDefaultValue();
		if ( $this->store ) {
			return $this->store->getValue($currentDefault);
		}
		return $currentDefault;
	}

	public function update($validValue) {
		if ( $this->store ) {
			$isSuccess = $this->store->setValue($validValue);
			$this->notifyUpdated();
			return $isSuccess;
		}
		return false;
	}

	public function getDefaultValue() {
		return $this->schema->getDefaultValue(null);
	}

	public function getSchema() {
		return $this->schema;
	}

	/**
	 * Convert a setting value to a string usable in HTML forms.
	 *
	 * This does NOT encode special HTML characters. It is only intended to convert
	 * non-string values - like booleans and NULLs - to a format suitable for form field.
	 *
	 * @param $value
	 * @return string
	 */
	public function encodeForForm($value) {
		return $this->schema->encodeValueForForm($value);
	}

	/**
	 * Convert submitted form data to a type suitable for validation.
	 * This is not necessarily the same as the schema's output type.
	 *
	 * @param string $value
	 */
	public function decodeSubmittedValue($value) {
		return $this->schema->decodeSubmittedFormValue($value);
	}

	public function validateFormValue($errors, $value, $stopOnFirstError = false) {
		$decodedValue = $this->decodeSubmittedValue($value);
		return $this->validate($errors, $decodedValue, $stopOnFirstError);
	}

	public function serializeValidationRules() {
		return $this->schema->serializeValidationRules();
	}

	public function getDataType() {
		return $this->schema->getSimplifiedDataType();
	}
}