<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls\ControlFlow;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class WithBlock extends ControlFlowBlock {
	private Binding $item;

	public function __construct(Binding $item, array $children = []) {
		parent::__construct([], $children);
		$this->item = $item;
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		echo '<p>"With" block not implemented yet.</p>';
	}

	protected function getSerializationContextForChildren(EvaluationContext $context): EvaluationContext {
		//Set the current item for the children so that they can (partially) resolve
		//their own bindings for control initialization.
		$innerContext = $context->createChildContext();
		$optionResult = $context->resolve($this->item);
		if ( $optionResult->isEmpty() ) {
			return $innerContext;
		}

		$resolved = $optionResult->get();
		$innerContext->setCurrentItem($resolved);
		return $innerContext;
	}


	protected function getJsUiElementType(): string {
		return $this->getRevisedJsUiElementType();
	}

	protected function getRevisedJsUiElementType(): string {
		return 'with';
	}

	public function serializeForRevisedJs(EvaluationContext $context): array {
		$result = parent::serializeForRevisedJs($context);
		$result['item'] = self::serializeMinimalBindingForJs($this->item, $context);
		return $result;
	}

	public function getAllReferencedSettings(EvaluationContext $context) {
		yield from self::getSettingsReferencedByBinding($this->item, $context);
	}
}