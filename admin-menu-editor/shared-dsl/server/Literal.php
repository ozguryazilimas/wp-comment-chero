<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

class Literal extends Expression {
	protected $value;

	/**
	 * @param mixed $value
	 */
	public function __construct($value) {
		$this->value = $value;
	}

	public function evaluate(?EvaluationContext $context = null) {
		return $this->value;
	}

	public function jsonSerialize(): array {
		return [
			't'     => 'literal',
			'value' => $this->value,
		];
	}
}