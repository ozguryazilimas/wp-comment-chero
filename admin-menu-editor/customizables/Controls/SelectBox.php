<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class SelectBox extends ChoiceControl {
	protected $type = 'select';
	protected $koComponentName = 'ame-select-box';

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$currentValue = $context->resolveValue($this->mainBinding);
		$classes = array_merge(['ame-select-box-control'], $this->classes);

		list($optionHtml, $optionBindings) = ChoiceControlOption::generateSelectOptions(
			$this->getOptions($context),
			$currentValue,
			$this->mainBinding,
			$context
		);

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->buildInputElement(
			$context, [
			'class'     => $classes,
			'style'     => $this->styles,
			'data-bind' => $this->makeKoDataBind(array_merge(
				$optionBindings,
				['value' => $this->getKoObservableExpression($currentValue)]
			)),
		],
			'select'
		);
		echo $optionHtml;
		//phpcs:enable
		echo '</select>';

		$tooltip = $this->getTooltip();
		if ( $tooltip ) {
			echo ' ';
			$renderer->renderTooltipTrigger($tooltip);
		}
	}
}