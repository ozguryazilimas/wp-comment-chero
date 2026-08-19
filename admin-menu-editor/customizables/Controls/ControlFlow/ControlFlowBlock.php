<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls\ControlFlow;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

abstract class ControlFlowBlock extends UiElement {
	abstract public function renderContent(Renderer $renderer, EvaluationContext $context);
}