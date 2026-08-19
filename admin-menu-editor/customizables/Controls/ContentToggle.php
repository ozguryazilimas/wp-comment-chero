<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class ContentToggle extends ClassicControl {
	protected $itemSelector = '';
	protected $toggleParentSelector = 'body';
	protected $hiddenByDefault = false;

	protected $visibleStateText = 'Hide details';
	protected $hiddenStateText = 'Show details';

	public function __construct($settings = array(), $params = array(), $children = []) {
		parent::__construct($settings, $params, $children);

		if ( isset($params['itemSelector']) ) {
			$this->itemSelector = $params['itemSelector'];
		} else {
			throw new \InvalidArgumentException('The "itemSelector" setting is required for ContentToggle controls.');
		}
		if ( isset($params['toggleParentSelector']) ) {
			$this->toggleParentSelector = $params['toggleParentSelector'];
		}
		if ( isset($params['hiddenByDefault']) ) {
			$this->hiddenByDefault = $params['hiddenByDefault'];
		}
		if ( isset($params['visibleStateText']) ) {
			$this->visibleStateText = $params['visibleStateText'];
		} else if ( isset($params['label']) ) {
			$this->visibleStateText = $params['label'];
		}
		if ( isset($params['hiddenStateText']) ) {
			$this->hiddenStateText = $params['hiddenStateText'];
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		echo HtmlHelper::tag(
			'a',
			array(
				'href'                    => '#toggle',
				'class'                   => 'ame-content-toggle-control',
				'data-item-selector'      => $this->itemSelector,
				'data-parent-selector'    => $this->toggleParentSelector,
				'data-default-state'      => $this->hiddenByDefault ? 'hidden' : null,
				'data-visible-state-text' => $this->visibleStateText,
				'data-hidden-state-text'  => $this->hiddenStateText,
			),
			esc_html($this->hiddenByDefault ? $this->hiddenStateText : $this->visibleStateText)
		);

		self::enqueueDependencies();
	}
}