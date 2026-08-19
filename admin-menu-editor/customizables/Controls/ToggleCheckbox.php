<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

/**
 * A special "checkbox" that sends one value when it's checked and another value
 * when it's unchecked.
 *
 * Usually, the browser only sends the checkbox value when the checkbox is
 * checked. This class uses a hidden input and some JS to send a different
 * value when the checkbox is unchecked.
 *
 * It can also work without JS, but only if the server handles multiple fields
 * with the same name by taking the value of the last field and discarding
 * the rest (which PHP does).
 */
class ToggleCheckbox extends ClassicControl {
	protected $type = 'binCheckbox';
	protected $koComponentName = 'ame-toggle-checkbox';

	protected $onValue = '1';
	protected $offValue = '0';

	public function __construct($settings = [], $params = [], $children = []) {
		$this->hasPrimaryInput = true;
		parent::__construct($settings, $params, $children);

		if ( array_key_exists('onValue', $params) ) {
			$this->onValue = $params['onValue'];
		}
		if ( array_key_exists('offValue', $params) ) {
			$this->offValue = $params['offValue'];
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$isChecked = ($this->getMainSettingValue(null, $context) === $this->onValue);

		//Encode non-scalar values as JSON. To simplify things for the script that
		//receives this data, we'll either encode both values or none of them.
		$useJson = !is_scalar($this->onValue) || !is_scalar($this->offValue);
		if ( $useJson ) {
			$onString = wp_json_encode($this->onValue);
			$offString = wp_json_encode($this->offValue);
		} else {
			$onString = self::boxValueToString($this->onValue);
			$offString = self::boxValueToString($this->offValue);
		}

		echo HtmlHelper::tag(
			'input',
			[
				'type'  => 'hidden',
				'name'  => $this->getFieldName($context),
				'value' => $offString,
				'class' => 'ame-toggle-checkbox-alternative',
			]
		);

		echo HtmlHelper::tag(
			'label',
			['class' => array_merge(['ame-toggle-checkbox-control'], $this->classes)]
		);

		$jsonOnValue = wp_json_encode($this->onValue);
		$jsonOffValue = wp_json_encode($this->offValue);

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Method is safe, and label is allowed to have HTML.
		echo $this->buildInputElement(
			$context, [
				'type'               => 'checkbox',
				'name'               => $this->getFieldName($context),
				'value'              => $onString,
				'checked'            => $isChecked,
				'data-ame-on-value'  => $jsonOnValue,
				'data-ame-off-value' => $jsonOffValue,
				'data-bind'          => 'ameToggleCheckbox: {
					checked: ' . $this->getKoObservableExpression($isChecked ? $this->onValue : $this->offValue)
					. ', onValue: ' . $jsonOnValue
					. ', offValue: ' . $jsonOffValue . ' }',
			]
		);
		echo ' ', $this->getLabel($context);
		//phpcs:enable

		$this->outputNestedDescription($context);
		echo '</label>';

		self::enqueueDependencies();
	}

	/**
	 * Convert one of the checkbox values to a string that can be used in a "value" attribute.
	 *
	 * @param scalar $stateValue
	 * @return string
	 */
	protected static function boxValueToString($stateValue) {
		if ( is_bool($stateValue) ) {
			return $stateValue ? '1' : '0';
		} else {
			return (string)$stateValue;
		}
	}

	public function includesOwnLabel(): bool {
		return true;
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		return array_merge(
			parent::getKoComponentParams($context),
			[
				'onValue'  => $this->onValue,
				'offValue' => $this->offValue,
			]
		);
	}
}