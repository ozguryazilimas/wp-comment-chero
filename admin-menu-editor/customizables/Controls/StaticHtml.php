<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

/**
 * This control just outputs a predefined HTML string.
 */
class StaticHtml extends ClassicControl {
	protected $type = 'static';
	protected $koComponentName = 'ame-static-html';

	/**
	 * @var bool The HTML code may or may not have line breaks, but adding them
	 *           externally is a bad idea and could produce invalid HTML.
	 */
	protected $declinesExternalLineBreaks = true;

	protected $html = '';

	protected $koComponentContainerType = 'span';

	public function __construct($html = '', $params = [], $children = []) {
		parent::__construct([], $params, $children);
		$this->html = $html;
		if ( isset($params['componentContainer']) ) {
			$this->koComponentContainerType = $params['componentContainer'];
		}
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is the point.
		echo $this->html;
	}

	protected function getKoComponentParams(EvaluationContext $context): array {
		return array_merge(
			parent::getKoComponentParams($context),
			[
				'html'      => $this->html,
				'container' => $this->koComponentContainerType,
			]
		);
	}
}