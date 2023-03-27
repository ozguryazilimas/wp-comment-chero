<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;

class SelectBox extends ChoiceControl {
	protected $type = 'select';
	protected $koComponentName = 'ame-select-box';

	public function renderContent(Renderer $renderer) {
		$currentValue = $this->mainSetting->getValue();
		$classes = array_merge(['ame-select-box-control'], $this->classes);

		list($optionHtml, $optionBindings) = ChoiceControlOption::generateSelectOptions(
			$this->options,
			$currentValue,
			$this->mainSetting
		);

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->buildInputElement(
			[
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
	}
}