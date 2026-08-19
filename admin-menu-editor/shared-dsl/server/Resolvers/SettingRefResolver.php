<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL\Resolvers;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SettingWithSchema;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvable;
use YahnisElsts\AdminMenuEditor\WireDSL\SettingRef;

/**
 * @implements ResolutionStrategy<SettingRef>
 */
class SettingRefResolver implements ResolutionStrategy {
	/**
	 * @param SettingRef $r
	 * @param EvaluationContext $context
	 * @param mixed $customDefault
	 * @return Resolution
	 */
	public function resolve(Resolvable $r, EvaluationContext $context, $customDefault = null): Resolution {
		$s = $r->getSetting();
		return new Resolution(
			$s,
			$s instanceof SettingWithSchema ? $s->getSchema() : null,
			$s,
			[]
		);
	}
}