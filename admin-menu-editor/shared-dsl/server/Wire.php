<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;

/**
 * A collection of static factory methods for creating DSL expressions.
 */
abstract class Wire {
	public static function ifTruthy(Expression $condition, $then = true, $else = null): Expression {
		return new FunctionCall(
			'ifTruthy',
			[
				'value'      => $condition,
				'thenResult' => $then,
				'elseResult' => $else,
			]
		);
	}

	/**
	 * @param string|AbstractSetting $binding
	 * @return Binding
	 */
	public static function bind($binding): Binding {
		if ( is_string($binding) ) {
			return new BindingRef($binding);
		} else if ( $binding instanceof AbstractSetting ) {
			return new SettingRef($binding);
		} else {
			throw new \InvalidArgumentException("bind() expects a string or an AbstractSetting.");
		}
	}

	public static function resolutionOf(Resolvable $reference): ResolutionOf {
		return new ResolutionOf($reference);
	}
}