<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

//TODO: Could this conceivably be a subclass of ControlGroup? It can generate the controls dynamically.
class RadioGroup extends ChoiceControl implements ControlContainer {
	const WRAP_LINE_BREAK = 'LineBreak';
	const WRAP_PARAGRAPH = 'Paragraph';
	const WRAP_NONE = 'None';
	const INPUT_ID_PREFIX = 'ame-rg-input_';

	protected $type = 'radio';
	protected $koComponentName = 'ame-radio-group';
	protected $declinesExternalLineBreaks = true;

	protected $beforeOption = '';
	protected $afterOption = '';
	protected $wrapStyle;

	protected $descriptionsAsTooltips = false;

	public function __construct($settings = [], $params = [], $children = []) {
		parent::__construct($settings, $params, $children);

		$this->wrapStyle = $params['wrap'] ?? self::WRAP_PARAGRAPH;
		switch ($this->wrapStyle) {
			case self::WRAP_LINE_BREAK:
				//A few WordPress settings pages use this.
				$this->beforeOption = '';
				$this->afterOption = '<br>';
				break;
			case self::WRAP_PARAGRAPH:
				//"Settings -> Reading" uses this, and AME used it in the "Settings" tab.
				$this->beforeOption = '<p>';
				$this->afterOption = '</p>';
				break;
			default:
				throw new \InvalidArgumentException("Invalid option wrap style: " . $this->wrapStyle);
		}

		if ( isset($params['descriptionsAsTooltips']) ) {
			$this->descriptionsAsTooltips = (bool)$params['descriptionsAsTooltips'];
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$fieldName = $this->getFieldName($context);
		$currentValue = $this->getMainSettingValue(null, $context);

		//Note: Option initialization needs to happen before checking if any options have nested
		//controls. Otherwise, we'll always get the "no nested controls" case.
		$options = $this->getOptions($context);

		$classes = $this->classes;
		$hasNestedControls = !empty($this->optionChildIndex);
		if ( $hasNestedControls ) {
			$classes[] = 'ame-rg-has-nested-controls';
		}

		$beforeOption = $this->beforeOption;
		$afterOption = $this->afterOption;
		if ( $hasNestedControls ) {
			//Layout will be handled by CSS grid, so we don't need line breaks,
			//and wrapping the options in <p> tags would mess up the grid.
			$beforeOption = $afterOption = '';
		}

		//buildTag() is safe, and we intentionally allow HTML in the label and description.
		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->buildTag(
			'fieldset',
			[
				'class'     => $classes,
				'style'     => $this->styles,
				'disabled'  => !$this->isEnabled($context),
				'data-bind' => $this->makeKoDataBind($this->getKoEnableBinding()),
			]
		);
		foreach ($options as $option) {
			$isChecked = ($currentValue === $option->value);

			echo $beforeOption;
			$labelClasses = ['ame-rg-option-label'];
			if ( is_string($option->value) && $this->hasOptionChild($option->value) ) {
				$labelClasses[] = 'ame-rg-has-choice-child';
			}
			echo $this->buildTag('label', ['class' => $labelClasses]);

			echo $this->buildTag(
				'input',
				array_merge([
					'type'      => 'radio',
					'name'      => $fieldName,
					'value'     => $context->encodeValueForForm($this->mainBinding, $option->value),
					'class'     => $this->getInputClasses($context),
					'checked'   => $isChecked,
					'disabled'  => !$option->enabled,
					'id'        => $this->getRadioInputId($option),
					'data-bind' => $this->makeKoDataBind([
						'checked'      => $this->getKoObservableExpression($option->value),
						'checkedValue' => wp_json_encode($option->value),
					]),
				], $this->inputAttributes)
			);
			echo ' ', $option->label;

			if ( !empty($option->description) ) {
				if ( $this->descriptionsAsTooltips ) {
					echo ' ';
					$renderer->renderTooltipTrigger(new Tooltip(
						$option->description,
						Tooltip::INFO,
						['ame-understated-tooltip']
					));
				} else {
					echo self::formatNestedDescription($option->description);
				}
			}
			echo '</label>';
			echo $afterOption;

			if ( is_string($option->value) ) {
				$child = $this->getOptionChild($option->value);
				if ( $child instanceof Control ) {
					echo HtmlHelper::tag('span', ['class' => 'ame-rg-nested-control']);
					$renderer->renderControl($child, $context);
					echo '</span>';
				}
			}
		}
		echo '</fieldset>';
		//phpcs:enable

		static::enqueueDependencies();
	}

	/**
	 * @param ChoiceControlOption $option
	 * @return string
	 */
	protected function getRadioInputId(ChoiceControlOption $option): string {
		return $this->getRadioInputPrefix() . sanitize_key(strval($option->value));
	}

	protected function getRadioInputPrefix(): string {
		return self::INPUT_ID_PREFIX . $this->instanceNumber . '-';
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = parent::getKoComponentParams($context);

		$hasNestedControls = !empty($this->optionChildIndex);
		$params['wrapStyle'] = $hasNestedControls ? self::WRAP_NONE : $this->wrapStyle;
		$params['radioInputPrefix'] = $this->getRadioInputPrefix();

		return $params;
	}
}