<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;


use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class EventButton extends ClassicControl {
	const DEFAULT_KIND = 'button';
	const VALID_KINDS = [
		self::DEFAULT_KIND => true,
		'link'             => true,
	];

	protected $koComponentName = 'ame-event-button';
	protected $eventName = '';
	protected $eventData = [];
	protected $wrap = false;
	protected string $iconClass = '';
	protected string $kind = self::DEFAULT_KIND;

	public function __construct($settings = [], $params = [], $children = []) {
		parent::__construct($settings, $params, $children);

		if ( array_key_exists('eventName', $params) ) {
			$this->eventName = (string)$params['eventName'];
		} else {
			$this->eventName = 'adminMenuEditor:defaultCustomEvent';
		}

		if ( array_key_exists('eventData', $params) ) {
			$this->eventData = $params['eventData'];
		}

		if ( array_key_exists('wrap', $params) ) {
			$this->wrap = (bool)$params['wrap'];
		}

		if ( array_key_exists('iconClass', $params) ) {
			$this->iconClass = (string)$params['iconClass'];
		}

		if ( array_key_exists('kind', $params) ) {
			$this->kind = $params['kind'] === 'link' ? 'link' : self::DEFAULT_KIND;
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		//Currently only implemented as a placeholder in HTML output.
		//The real action happens in the Knockout component.
		echo '[EventButton: ' . esc_html($this->getLabel($context)) . ']';
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = parent::getKoComponentParams($context);

		if ( !empty($this->eventName) ) {
			$params['eventName'] = $this->eventName;
		}
		if ( !empty($this->eventData) ) {
			$params['eventData'] = $this->eventData;
		}
		$params['wrap'] = $this->wrap;
		if ( !empty($this->iconClass) ) {
			$params['iconClass'] = $this->iconClass;
		}
		if ( $this->kind !== self::DEFAULT_KIND ) {
			$params['kind'] = $this->kind;
		}
		return $params;
	}

}