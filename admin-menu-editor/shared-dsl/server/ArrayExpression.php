<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

class ArrayExpression extends Expression {
	protected array $items;

	public function __construct(array $items = []) {
		$this->items = self::boxValues($items);
	}

	public function evaluate(?EvaluationContext $context = null): array {
		return array_map(
			function (Expression $item) use ($context) {
				return $item->evaluate($context);
			},
			$this->items
		);
	}

	public function jsonSerialize(): array {
		//No need to explicitly call jsonSerialize() on each item.
		//json_encode() will do that automatically.
		return [
			't'     => 'array',
			'items' => $this->items,
		];
	}
}