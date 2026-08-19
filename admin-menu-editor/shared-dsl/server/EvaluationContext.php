<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\SettingContainer;
use YahnisElsts\AdminMenuEditor\Options\Option;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvers\Resolution;

interface EvaluationContext {
	/**
	 * @param Resolvable $resolvable
	 * @param mixed $customDefault
	 * @return Option<Resolution>
	 */
	function resolve(Resolvable $resolvable, $customDefault = null): Option;

	function resolveValue(Resolvable $resolvable, $customDefault = null);

	/**
	 * @param Resolvable $resolvable
	 * @param $customDefault
	 * @return Option<Resolution>
	 */
	function partialResolve(Resolvable $resolvable, $customDefault = null): Option;

	function getResolvers(): array;

	function getRootSettingContainer(): SettingContainer;

	/**
	 * Get the current item in the evaluation context.
	 *
	 * Usually used to refer to the current item in a collection or struct when iterating over it.
	 *
	 * @return Resolution|null
	 */
	function getCurrentItem(): ?Resolution;

	function setCurrentItem(Resolution $item): void;

	function createChildContext(): EvaluationContext;

	function withAttributes(array $attributes): EvaluationContext;

	function getAttribute(string $key, $default = null);

	function getId(): string;
	function getVersion(): int;

	/**
	 * Utility function that calls the corresponding method on the resolved setting or schema.
	 *
	 * @param Resolvable $resolvable
	 * @param mixed $value
	 * @return string
	 */
	function encodeValueForForm(Resolvable $resolvable, $value): string;
}