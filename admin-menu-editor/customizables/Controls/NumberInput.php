<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;


use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Enum;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\CssLengthSetting;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\WithSchema\CssLengthSetting as CssLengthSettingWithSchema;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class NumberInput extends AbstractNumericControl {
	protected $type = 'fancyNumber';
	protected $koComponentName = 'ame-number-input';

	/**
	 * @var string|null
	 */
	protected $fixedUnit = null;
	/**
	 * @var Settings\AbstractSetting|null
	 */
	protected $unitSetting = null;

	public function __construct($settings = [], $params = [], $children = []) {
		$this->hasPrimaryInput = true;
		parent::__construct($settings, $params, $children);

		//Units can be specified in a number of ways.
		if ( isset($settings['unit']) ) {
			$this->unitSetting = $settings['unit'];
		}

		if ( !$this->unitSetting ) {
			if ( array_key_exists('unit', $params) ) {
				if ( is_string($params['unit']) ) {
					$this->fixedUnit = $params['unit'];
				} else if ( $params['unit'] instanceof Settings\AbstractSetting ) {
					$this->unitSetting = $params['unit'];
				}
			} else if (
				($this->mainBinding instanceof CssLengthSetting)
				|| ($this->mainBinding instanceof CssLengthSettingWithSchema)
			) {
				$unitSetting = $this->mainBinding->getUnitSetting();
				if ( $unitSetting instanceof Settings\AbstractSetting ) {
					$this->unitSetting = $unitSetting;
				} else {
					$this->fixedUnit = $this->mainBinding->getUnit();
				}
			}
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$hasUnitDropdown = (
			($this->unitSetting instanceof Settings\EnumSetting)
			|| (
				($this->unitSetting instanceof Settings\WithSchema\SettingWithSchema)
				&& ($this->unitSetting->getSchema() instanceof Enum)
			)
		);

		$currentUnitValue = $this->getCurrentUnit();
		if ( $hasUnitDropdown || !empty($currentUnitValue) ) {
			$unitElementId = $this->getUnitElementId($context);
		} else {
			$unitElementId = null;
		}

		$value = $this->getMainSettingValue(null, $context);

		$numberConfig = $this->getNumberConfig($context);
		$sliderRanges = $this->getSliderRanges($numberConfig);

		$wrapperClasses = ['ame-number-input-control'];
		if ( !empty($sliderRanges) ) {
			$wrapperClasses[] = 'ame-container-with-popup-slider';
		}
		$wrapperClasses = array_merge($wrapperClasses, $this->classes);

		echo HtmlHelper::tag(
			'fieldset',
			[
				'class'     => $wrapperClasses,
				'data-bind' => $this->makeKoDataBind($this->getKoEnableBinding()),
			]
		);
		if ( $hasUnitDropdown ) {
			echo '<div class="ame-input-group">';
		}

		$attributes = $this->getBasicInputAttributes($numberConfig);
		$attributes['value'] = $value;

		$inputClasses = [];
		if ( !empty($sliderRanges) ) {
			$inputClasses[] = 'ame-input-with-popup-slider';
		}
		$inputClasses[] = 'ame-number-input';
		//buildInputElement() will add $this->inputClasses, so no need to do it here.
		$attributes['class'] = implode(' ', $inputClasses);

		if ( !empty($unitElementId) ) {
			$attributes['data-unit-element-id'] = $unitElementId;
		}
		if ( !empty($sliderRanges) ) {
			$attributes['data-slider-ranges'] = wp_json_encode($sliderRanges);
		}

		$attributes['data-bind'] = $this->makeKoDataBind(array_merge([
			'value'                     => $this->getKoObservableExpression($value),
			'ameObservableChangeEvents' => 'true',
		], $this->getKoEnableBinding()));

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buildInputElement() is safe
		echo $this->buildInputElement($context, $attributes);

		if ( $hasUnitDropdown ) {
			$this->renderUnitDropdown($this->unitSetting, $context, [
				'name'               => $this->getFieldName($context, null, $this->unitSetting),
				'id'                 => $unitElementId,
				'class'              => 'ame-input-group-secondary ame-number-input-unit',
				'data-ac-setting-id' => $this->unitSetting->getId(),
			]);
		} else {
			$unit = $this->getCurrentUnit();
			if ( !empty($unit) ) {
				echo HtmlHelper::tag(
					'span',
					[
						'id'               => $unitElementId,
						'class'            => 'ame-number-input-unit',
						'data-number-unit' => $unit,
					],
					' ' . esc_html($unit)
				);
			}
		}

		if ( $hasUnitDropdown ) {
			echo '</div>';
		}

		//Slider
		if ( !empty($sliderRanges) ) {
			PopupSlider::basic()->render();
		}

		echo '</fieldset>';

		static::enqueueDependencies();
	}

	protected function getCurrentUnit() {
		if ( $this->unitSetting instanceof Settings\AbstractSetting ) {
			return $this->unitSetting->getValue();
		}
		return $this->fixedUnit;
	}

	protected function getUnitElementId(?EvaluationContext $context = null) {
		return $this->getPrimaryInputId($context) . '__unit';
	}

	public function getInputClasses(?EvaluationContext $context = null): array {
		$classes = parent::getInputClasses($context);

		//Add the "ame-small-number-input" class to controls where the expected
		//number of digits is 4 or less. This only applies if both the min and
		//max values are known.
		$maxDigits = $this->getNumberConfig($context)->getEstimatedMaxDigits();
		if (
			is_numeric($maxDigits)
			&& ($maxDigits <= 4)
			&& !in_array('ame-small-number-input', $classes)
		) {
			$classes[] = 'ame-small-number-input';
		}

		return $classes;
	}


	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = parent::getKoComponentParams($context);

		$unitText = $this->getCurrentUnit();
		$hasUnitDropdown = false;

		$units = ChoiceControlOption::tryGenerateFromSetting($this->unitSetting);
		if ( !empty($units) ) {
			$hasUnitDropdown = true;
			$params['hasUnitDropdown'] = true;
			$params['unitDropdownOptions'] = ChoiceControlOption::generateKoOptions($units);
		} else if ( !empty($unitText) ) {
			$params['unitText'] = $unitText;
		}

		if ( $hasUnitDropdown || !empty($unitText) ) {
			$params['unitElementId'] = $this->getUnitElementId();
		}

		return $params;
	}

	public function serializeForJs(EvaluationContext $context): array {
		$result = parent::serializeForJs($context);

		if ( $this->unitSetting instanceof Settings\AbstractSetting ) {
			if ( empty($result['settings']) ) {
				$result['settings'] = [];
			}
			$result['settings']['unit'] = $this->unitSetting->getId();
		}

		return $result;
	}

	public function enqueueKoComponentDependencies() {
		parent::enqueueKoComponentDependencies();

		//The slider automatically enqueues its dependencies when it's rendered
		//via PopupSlider::render(), but KO components don't use that method.
		//We need to enqueue the dependencies explicitly.
		PopupSlider::enqueueDependencies();
	}

	public function getSettings() {
		$settings = parent::getSettings();

		//The unit setting might not be in the "settings" array if it was passed via params only.
		//But we need it to correctly serialize the control's settings for JS.
		if ( $this->unitSetting && !isset($settings['unit']) ) {
			$settings['unit'] = $this->unitSetting;
		}

		return $settings;
	}


}