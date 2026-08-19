<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;


use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class ColorPicker extends ClassicControl {
	protected $type = 'colorPicker';
	protected $koComponentName = 'ame-color-picker';

	/**
	 * @var AbstractSetting
	 */
	protected $mainBinding;

	public function __construct($settings = [], $params = [], $children = []) {
		$this->hasPrimaryInput = true;
		parent::__construct($settings, $params, $children);
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$value = $this->getMainSettingValue(null, $context);
		if ( !is_string($value) ) {
			$value = '';
		}
		$settingId = ($this->mainBinding instanceof AbstractSetting) ? $this->mainBinding->getId() : null;

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- buildInputElement() is safe
		echo $this->buildInputElement($context, [
			'type'                => 'text',
			'class'               => array_merge(
				['ame-color-picker', 'ame-customizable-color-picker'],
				$this->classes
			),
			'value'               => $value,
			'style'               => 'visibility: hidden',
			'data-ame-setting-id' => $settingId,
			'data-bind'           => 'ameObservableChangeEvents: ' . $this->getKoObservableExpression($value),
		]);
		//phpcs:enable

		static::enqueueDependencies();
	}

	protected static function enqueueDependencies() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		parent::enqueueDependencies();

		wp_enqueue_script('wp-color-picker');
	}

	public function supportsLabelAssociation() {
		//This currently doesn't work with the WordPress color picker.
		return false;
	}
}