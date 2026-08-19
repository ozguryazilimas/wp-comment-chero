<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;

abstract class Expression implements \JsonSerializable {
	abstract public function evaluate(?EvaluationContext $context = null);

	public static function boxValues(array $values): array {
		return array_map(
			function ($value) {
				if ( $value instanceof Expression ) {
					return $value; //Already boxed.
				} else if ( $value instanceof AbstractSetting ) {
					return new SettingRef($value);
				} else if ( is_array($value) && !empty($value) ) {
					return new ArrayExpression($value);
				} else {
					return new Literal($value);
				}
			},
			$values
		);
	}
}