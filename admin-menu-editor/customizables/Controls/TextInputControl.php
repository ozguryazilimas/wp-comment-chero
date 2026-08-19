<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;


use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\StringSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class TextInputControl extends ClassicControl {
	protected $type = 'text';
	protected $koComponentName = 'ame-text-input';

	/**
	 * @var StringSetting
	 */
	protected $mainBinding;

	/**
	 * @var bool Whether to style the value as code (e.g. using fixed width fonts).
	 */
	protected $isCode = false;

	/**
	 * @var bool Whether to use the Knockout textInput binding instead of the value binding.
	 *           Has no effect if the control is used without Knockout.
	 */
	protected bool $useTextInputBinding = false;

	protected $inputType = 'text';

	public function __construct($settings = array(), $params = array(), $children = []) {
		parent::__construct($settings, $params, $children);

		$this->hasPrimaryInput = true;
		$this->isCode = !empty($params['isCode']);
		if ( !empty($params['inputType']) ) {
			$this->inputType = $params['inputType'];
		}
		$this->useTextInputBinding = !empty($params['useTextInputBinding']);
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$classes = array('regular-text');
		if ( $this->isCode ) {
			$classes[] = 'code';
		}
		$classes[] = 'ame-text-input-control';
		$value = $this->getMainSettingValue(null, $context);

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- buildInputElement() is safe
		echo $this->buildInputElement(
			$context, array(
				'type'      => $this->inputType,
				'value'     => ($value === null) ? '' : $value,
				'class'     => $classes,
				'style'     => $this->styles,
				'data-bind' => $this->makeKoDataBind(array_merge([
					($this->useTextInputBinding ? 'textInput' : 'value') => $this->getKoObservableExpression($value),
				], $this->getKoEnableBinding())),
			)
		);
		//phpcs:enable
		$this->outputSiblingDescription($context);
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = parent::getKoComponentParams($context);
		$params['isCode'] = $this->isCode;
		$params['inputType'] = $this->inputType;
		if ( $this->useTextInputBinding ) {
			$params['useTextInputBinding'] = $this->useTextInputBinding;
		}
		return $params;
	}

}