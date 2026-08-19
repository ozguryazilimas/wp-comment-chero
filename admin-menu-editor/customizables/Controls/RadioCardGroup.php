<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class RadioCardGroup extends ChoiceControl {
	protected $type = 'radio';
	protected $koComponentName = 'ame-radio-card-group';

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$fieldName = $this->getFieldName($context);
		$currentValue = $this->getMainSettingValue(null, $context);

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->buildFieldsetContainer($context, ['ame-radio-card-group-control']);
		foreach ($this->getOptions($context) as $option) {
			$isChecked = ($currentValue === $option->value);

			echo $this->buildTag('label', array(
				'class' => 'ame-radio-card',
				'title' => $option->description,
			));

			echo '<div class="ame-radio-card-input-wrapper">';
			echo $this->generateRadioInputFor($option, $fieldName, $isChecked, $context);
			echo '</div>';

			echo '<div class="ame-radio-card-body">';

			$childControl = $this->getOptionChild($option->value);
			if ( $childControl ) {
				echo '<div class="ame-radio-card-children">';
				//renderElement() would often put the child control inside an auto-generated control
				//group, which can mess up the layout. Let's use renderControl() if possible.
				if ( $childControl instanceof Control ) {
					$renderer->renderControl($childControl, $context);
				} else {
					$renderer->renderElement($childControl, $context, $this);
				}
				echo '</div>';
			}

			if ( !empty($option->label) ) {
				HtmlHelper::outputTag('span', ['class' => 'ame-radio-card-label'], $option->label);
			}

			echo '</div>';

			echo '</label>';
		}
		echo '</fieldset>';
		//phpcs:enable
	}

}