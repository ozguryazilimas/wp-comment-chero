<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas;
use YahnisElsts\AdminMenuEditor\Options\Option;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

abstract class AbstractNumericControl extends ClassicControl {
	const NUMBER_VALIDATION_PATTERN = '\\s*-?[0-9]+(?:[.,]\\d*)?\s*';

	protected $customMin;
	protected $customMax;
	protected $customStep;

	protected $rangeByUnit = [];

	protected $spinButtonsAllowed = false;

	public function __construct($settings = [], $params = [], $children = []) {
		parent::__construct($settings, $params, $children);

		//Range.
		if ( array_key_exists('min', $params) ) {
			$this->customMin = Option::some($params['min']);
		} else {
			$this->customMin = Option::none();
		}
		if ( array_key_exists('max', $params) ) {
			$this->customMax = Option::some($params['max']);
		} else {
			$this->customMax = Option::none();
		}

		//Step.
		if ( array_key_exists('step', $params) ) {
			//Step must be a positive number, null, or the special value "any".
			if ( is_numeric($params['step']) ) {
				$this->customStep = Option::some(abs($params['step']));
			} else if ( ($params['step'] === null) || ($params['step'] === 'any') ) {
				$this->customStep = Option::some($params['step']);
			} else {
				throw new \InvalidArgumentException("Invalid step value: {$params['step']}");
			}
		} else {
			$this->customStep = Option::none();
		}

		//Each unit can have a different range.
		if ( array_key_exists('rangeByUnit', $params) ) {
			$this->rangeByUnit = $params['rangeByUnit'];
		}
	}

	private $cachedNumberConfig = null;
	private $configCacheKey = null;

	protected function getNumberConfig(?EvaluationContext $context = null): NumberConfig {
		$cacheKey = ($this->mainBinding ? $this->mainBinding->getInternalStringId() : '-') . '|';
		if ( $context ) {
			$cacheKey .= $context->getId() . '|' . $context->getVersion();
		} else {
			$cacheKey .= 'no-ctx';
		}

		if ( ($this->cachedNumberConfig === null) || ($this->configCacheKey !== $cacheKey) ) {
			$defaultConfig = static::createDefaultNumberConfig($this->mainBinding, $context);

			$min = $this->customMin->getOrElse($defaultConfig->getMin());
			$max = $this->customMax->getOrElse($defaultConfig->getMax());
			$step = $this->customStep->getOrElse($defaultConfig->getStep());

			//Estimate the max number of digits for input size adjustment.
			$digits = null;
			if ( is_numeric($min) && is_numeric($max) ) {
				$digits = 1;
				//Digits before the decimal point = greatest log10 of abs(min) and abs(max).
				//Add 1 because log10 is one less than the number of digits (e.g. log10(1) = 0).
				//Note the use of loose comparison to avoid "0 !== 0.0" issues.
				if ( ($min != 0) ) {
					$digits = max($digits, floor(log10(abs($min))) + 1);
				}
				if ( ($max != 0) ) {
					$digits = max($digits, floor(log10(abs($max))) + 1);
				}

				//Add the digits after the decimal point if the step is a decimal number.
				if ( is_numeric($step) ) {
					$fraction = abs($step - floor($step));
					if ( ($fraction != 0) ) {
						$digits += floor(abs(log10(abs($fraction))));
					}
				}
			}

			$this->cachedNumberConfig = new NumberConfig(
				$min,
				$max,
				$step,
				$defaultConfig->mayContainFloat(),
				$digits
			);
			$this->configCacheKey = $cacheKey;
		}

		return $this->cachedNumberConfig;
	}

	protected static function createDefaultNumberConfig(?Binding $binding, ?EvaluationContext $context = null): NumberConfig {
		$setting = $binding;
		$valueSchema = null;

		if ( $context && $binding ) {
			$setting = null;
			$option = $context->resolve($binding);
			if ( $option->isDefined() ) {
				$resolution = $option->get();
				$path = $resolution->getPathInSetting();
				if ( empty($path) ) {
					//It's just the setting itself.
					$setting = $resolution->getNearestSetting();
				} else {
					$valueSchema = $resolution->getValueSchema();
				}
			}
		}

		if ( empty($setting) && empty($valueSchema) ) {
			return new NumberConfig();
		}

		$min = null;
		$max = null;
		$mayContainFloat = false;

		if ( !$valueSchema && ($setting instanceof Settings\WithSchema\SingularSetting) ) {
			$valueSchema = $setting->getSchema();
		}

		if ( $setting instanceof Settings\NumericSetting ) {
			$min = $setting->getMinValue();
			$max = $setting->getMaxValue();
			$mayContainFloat = $setting instanceof Settings\FloatSetting;
		} else if ( $valueSchema instanceof Schemas\Number ) {
			$min = $valueSchema->getMin();
			$max = $valueSchema->getMax();
			$mayContainFloat = !$valueSchema->isInt();
		}

		if ( is_numeric($min) && is_numeric($max) && $mayContainFloat ) {
			$step = ($max - $min) / 100;
		} else {
			$step = 1;
		}

		return new NumberConfig($min, $max, $step, $mayContainFloat);
	}

	protected function getSliderRanges(NumberConfig $config): array {
		$sliderRanges = [];
		if ( ($config->getMin() !== null) && ($config->getMax() !== null) ) {
			$sliderRanges['_default'] = [
				'min'  => $config->getMin(),
				'max'  => $config->getMax(),
				'step' => $config->getStep(),
			];
		}
		return array_merge($sliderRanges, $this->rangeByUnit);
	}

	protected function getBasicInputAttributes(NumberConfig $config) {
		$attributes = [
			'min'  => $config->getMin(),
			'max'  => $config->getMax(),
			'step' => $config->getStep(),
		];

		if ( $this->spinButtonsAllowed ) {
			$attributes['type'] = 'number';
		} else {
			$attributes['type'] = 'text';
			$attributes['inputmode'] = 'numeric';
			$attributes['pattern'] = self::NUMBER_VALIDATION_PATTERN;
			$attributes['maxlength'] = 20;
		}
		return $attributes;
	}

	protected function renderUnitDropdown(
		Settings\AbstractSetting $unitSetting,
		EvaluationContext        $context,
		                         $elementAttributes = [],
		                         $includeKoBindings = true
	) {
		//Display a dropdown list of units.
		$units = ChoiceControlOption::tryGenerateFromSetting($unitSetting);
		if ( empty($units) ) {
			return false; //This setting isn't an enum or doesn't have any values.
		}

		$selectedUnit = $unitSetting->getValue();

		list($optionHtml, $optionBindings) = ChoiceControlOption::generateSelectOptions(
			$units,
			$selectedUnit,
			$unitSetting,
			$context
		);

		if ( $includeKoBindings ) {
			$elementAttributes['data-bind'] = $this->makeKoDataBind(array_merge(
				$optionBindings,
				['value' => $this->getKoObservableExpression($selectedUnit, $unitSetting)],
				$this->getKoEnableBinding()
			));
		}

		echo HtmlHelper::tag('select', $elementAttributes);
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $optionHtml;
		echo '</select>';

		return true;
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = parent::getKoComponentParams($context);
		$config = $this->getNumberConfig();
		$params['min'] = $config->getMin();
		$params['max'] = $config->getMax();
		$params['step'] = $config->getStep();

		$sliderRanges = $this->getSliderRanges($config);
		if ( !empty($sliderRanges) ) {
			$params['sliderRanges'] = $sliderRanges;
		}

		return $params;
	}
}