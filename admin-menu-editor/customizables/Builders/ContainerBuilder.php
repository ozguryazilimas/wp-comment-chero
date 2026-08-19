<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Builders;

use YahnisElsts\AdminMenuEditor\Customizable\Controls;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement;

abstract class ContainerBuilder extends BaseElementBuilder {
	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @param class-string<\YahnisElsts\AdminMenuEditor\Customizable\Controls\Container> $containerClass
	 * @param string $title
	 */
	protected function __construct($containerClass, $title, $children = array()) {
		parent::__construct($containerClass, [], $children);
		$this->title = $title;
	}

	public function build() {
		$className = $this->elementClass;
		return new $className($this->title, $this->buildParams(),  $this->buildChildren());
	}

	public function getTitle(): string {
		return $this->title;
	}
}