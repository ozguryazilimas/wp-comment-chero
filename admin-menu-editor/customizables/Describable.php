<?php

namespace YahnisElsts\AdminMenuEditor\Customizable;

use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

interface Describable {
	public function getLabel(?EvaluationContext $context = null): string;

	public function getDescription(?EvaluationContext $context = null): string;
}