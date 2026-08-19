<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL\Resolvers;

use YahnisElsts\AdminMenuEditor\Options\Option;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvable;

/**
 * @template T of Resolvable
 */
interface ResolutionStrategy {
	/**
	 * @param T $r
	 * @param EvaluationContext $context
	 * @param mixed $customDefault
	 * @return Resolution
	 */
	public function resolve(Resolvable $r, EvaluationContext $context, $customDefault = null): Resolution;
}