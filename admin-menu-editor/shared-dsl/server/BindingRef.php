<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;

class BindingRef extends Reference implements Binding {
	protected string $bindingString;

	public function __construct(string $bindingString) {
		$this->bindingString = $bindingString;
	}

	public function evaluate(?EvaluationContext $context = null) {
		if ( $context ) {
			return $context->resolveValue($this);
		} else {
			throw new \RuntimeException("Cannot evaluate BindingRef without an EvaluationContext.");
		}
	}

	public function jsonSerialize(): array {
		return [
			't'    => 'binding',
			'bind' => $this->bindingString,
		];
	}

	public function getBindingString(): string {
		return $this->bindingString;
	}

	public function getInternalStringId(): string {
		return $this->bindingString;
	}

	const RESOLUTION_KEY = 'binding';

	public function getResolutionStrategyKey(): string {
		return self::RESOLUTION_KEY;
	}
}