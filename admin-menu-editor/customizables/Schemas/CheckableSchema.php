<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

abstract class CheckableSchema extends Schema {
	protected $checks = [];

	protected function addCheck($kind, $value = null, $params = []) {
		$check = ['kind' => $kind, 'value' => $value];
		if ( !empty($params) ) {
			$check = array_merge(array_filter($params), $check);
		}
		$this->checks[] = $check;
		return $this;
	}

	protected function findFirstCheck($kind) {
		foreach ($this->checks as $check) {
			if ( $check['kind'] === $kind ) {
				return $check;
			}
		}
		return null;
	}

	protected function getFirstCheckValue($kind, $defaultResult = null) {
		$check = $this->findFirstCheck($kind);
		if ( $check === null ) {
			return $defaultResult;
		}
		return array_key_exists('value', $check) ? $check['value'] : $defaultResult;
	}

	public function serialize(SchemaSerializer $serializer): array {
		$result = parent::serialize($serializer);
		if ( !empty($this->checks) ) {
			$result['checks'] = $this->checks;
		}
		return $result;
	}
}