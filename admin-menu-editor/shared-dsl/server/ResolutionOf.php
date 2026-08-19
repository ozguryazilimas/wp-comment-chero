<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;

/**
 * Represents an expression that resolves a reference to a setting or variable.
 *
 * Unlike most expressions, this evaluates to the underlying object, not the object's value.
 * This is useful when you need to access the setting object from TypeScript.
 */
class ResolutionOf extends Expression {
	protected Resolvable $reference;

	public function __construct(Resolvable $reference) {
		$this->reference = $reference;
	}

	public function evaluate(?EvaluationContext $context = null): ?AbstractSetting {
		if ( !$context ) {
			throw new \InvalidArgumentException('Evaluation context is required to resolve a reference.');
		}

		$opt = $context->resolve($this->reference);
		if ( $opt->isEmpty() ) {
			return null;
		}

		$resolution = $opt->get();
		if ( $resolution->isLeafSetting() ) {
			return $resolution->getNearestSetting();
		}

		return null;
	}

	public function jsonSerialize(): array {
		if ( $this->reference instanceof \JsonSerializable ) {
			$serialized = $this->reference->jsonSerialize();
		} else if ( $this->reference instanceof AbstractSetting ) {
			$serialized = [
				't'    => 'binding',
				'bind' => $this->reference->getId(),
			];
		} else {
			throw new \RuntimeException('Resolvable must be something JsonSerializable or an AbstractSetting.');
		}

		return [
			't'   => 'resolve',
			'ref' => $serialized,
		];
	}
}