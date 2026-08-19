<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL\Resolvers;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SettingWithSchema;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvable;

/**
 * @implements ResolutionStrategy<AbstractSetting>
 */
class SettingResolver implements ResolutionStrategy {
	/**
	 * @param AbstractSetting $r
	 * @param EvaluationContext $context
	 * @param mixed $customDefault
	 * @return Resolution
	 */
	public function resolve(Resolvable $r, EvaluationContext $context, $customDefault = null): Resolution {
		return new Resolution(
			$r,
			$r instanceof SettingWithSchema ? $r->getSchema() : null,
			$r,
			[],
		);
	}
}