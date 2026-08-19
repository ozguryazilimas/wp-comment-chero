<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class TextArea extends ClassicControl {
	protected $type = 'textarea';
	protected $koComponentName = 'ame-rev-text-area';

	/**
	 * @var Binding
	 */
	protected $mainBinding;

	protected $rows = 5;
	protected $cols = 100;
	protected string $placeholder = '';

	/**
	 * @var bool Whether to use the Knockout textInput binding instead of the value binding.
	 *           Has no effect if the control is used without Knockout.
	 */
	protected bool $useTextInputBinding = false;

	public function __construct($settings = array(), $params = array(), $children = []) {
		$this->hasPrimaryInput = true;
		parent::__construct($settings, $params, $children);

		if ( isset($params['rows']) ) {
			$this->rows = max(intval($params['rows']), 1);
		}
		if ( isset($params['cols']) ) {
			$this->cols = max(intval($params['cols']), 1);
		}
		if ( isset($params['placeholder']) ) {
			$this->placeholder = (string)$params['placeholder'];
		}
		if ( isset($params['useTextInputBinding']) ) {
			$this->useTextInputBinding = (bool)$params['useTextInputBinding'];
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$value = (string)$context->resolveValue($this->mainBinding);

		//phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- builtInputElement() is safe
		echo $this->buildInputElement(
			$context, [
			'rows'        => (int)$this->rows,
			'cols'        => (int)$this->cols,
			'class'       => 'large-text',
			'placeholder' => $this->placeholder,
			'data-bind'   => $this->makeKoDataBind([
				($this->useTextInputBinding ? 'textInput' : 'value') => $this->getKoObservableExpression($value),
			]),
		],
			'textarea',
			esc_textarea($value)
		);
		//phpcs:enable
		$this->outputSiblingDescription($context);
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = parent::getKoComponentParams($context);
		$params['rows'] = $this->rows;
		$params['cols'] = $this->cols;
		if ( $this->placeholder !== '' ) {
			$params['placeholder'] = $this->placeholder;
		}
		if ( $this->useTextInputBinding ) {
			$params['useTextInputBinding'] = $this->useTextInputBinding;
		}
		return $params;
	}
}