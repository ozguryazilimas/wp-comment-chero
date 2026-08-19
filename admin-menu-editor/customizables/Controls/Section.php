<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;


use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class Section extends Container {
	const NAVIGATION_ROLE = 'navigation';
	const CONTENT_ROLE = 'content';

	const VALID_ROLES = [
		self::NAVIGATION_ROLE => true,
		self::CONTENT_ROLE    => true,
	];

	/**
	 * Indicates how the section should be rendered in the UI.
	 *
	 * Note that this is a preference, not a strict requirement. A renderer may choose
	 * to ignore this setting and display the section in a different way.
	 *
	 * @var string
	 */
	protected $preferredRole = self::NAVIGATION_ROLE;

	public function __construct($title, $params = [], $children = []) {
		parent::__construct($title, $params, $children);

		if ( isset($params['preferredRole']) ) {
			if ( !array_key_exists($params['preferredRole'], self::VALID_ROLES) ) {
				throw new \InvalidArgumentException('Invalid preferred role.');
			}
			$this->preferredRole = $params['preferredRole'];
		}
	}

	protected function getJsUiElementType() {
		return 'section';
	}

	public function serializeForJs(EvaluationContext $context): array {
		$result = parent::serializeForJs($context);
		if ( $this->preferredRole !== self::NAVIGATION_ROLE ) {
			$result['preferredRole'] = $this->preferredRole;
		}
		return $result;
	}
}