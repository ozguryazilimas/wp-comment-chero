<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;


use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class RadioButtonBar extends ChoiceControl {
	protected $type = 'radio-bar';
	protected $koComponentName = 'ame-radio-button-bar';

	protected $declinesExternalLineBreaks = true;

	protected string $controlClass = 'ame-radio-button-bar-control';

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$fieldName = $this->getFieldName($context);
		$currentValue = $this->mainBinding->getValue();

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->buildFieldsetContainer($context, [$this->controlClass]);
		foreach ($this->getOptions($context) as $option) {
			$isChecked = ($currentValue === $option->value);

			echo $this->buildTag('label', array(
				'class' => 'ame-radio-bar-item',
				'title' => $option->description,
			));

			echo $this->generateRadioInputFor($option, $fieldName, $isChecked, $context);

			$buttonContent = esc_html($option->label);
			if ( is_string($option->icon) && (strpos($option->icon, 'dashicons-') !== false) ) {
				$buttonContent = sprintf(
					'<span class="dashicons %s"></span>  %s',
					esc_attr($option->icon),
					$buttonContent
				);
			}

			$buttonClasses = ['button', 'ame-radio-bar-button'];
			if ( !empty($option->label) ) {
				$buttonClasses[] = 'ame-rb-has-label';
			}

			//Note that we can't use a "button" element because then the label
			//won't correctly select the radio input when clicked. It's probably
			//because a label can't be associated with two elements.
			echo $this->buildTag(
				'span',
				['class' => $buttonClasses],
				$buttonContent
			);

			echo '</label>';
		}
		echo '</fieldset>';
		//phpcs:enable
	}
}