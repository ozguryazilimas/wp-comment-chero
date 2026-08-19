<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

class FunctionCall extends Expression {
	protected string $functionName;
	protected array $arguments;

	public function __construct(string $functionName, array $arguments) {
		$this->functionName = $functionName;
		$this->arguments = self::boxValues($arguments);
	}

	public function evaluate(?EvaluationContext $context = null) {
		$evaluatedArgs = array_map(
			function (Expression $arg) use ($context) {
				return $arg->evaluate($context);
			},
			$this->arguments
		);

		$callable = [BuiltinFunctions::class, $this->functionName];
		if ( is_callable($callable) ) {
			return call_user_func_array($callable, $evaluatedArgs);
		} else {
			throw new \RuntimeException("Function '$this->functionName' is not defined.");
		}
	}

	public function jsonSerialize(): array {
		return [
			't'    => 'funcCall',
			'name' => $this->functionName,
			'args' => $this->arguments,
		];
	}
}