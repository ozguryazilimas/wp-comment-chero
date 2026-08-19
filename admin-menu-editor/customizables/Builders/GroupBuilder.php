<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Builders;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\ControlGroup;

class GroupBuilder extends ContainerBuilder {
	public function __construct($title = '', $children = array()) {
		parent::__construct(ControlGroup::class, $title, $children);
	}

	/**
	 * @return ControlGroup
	 */
	public function build() {
		return new ControlGroup($this->title, $this->buildParams(), $this->buildChildren());
	}

	public function stacked($isStacked = true): self {
		$this->params['stacked'] = $isStacked;
		return $this;
	}

	public function fieldset($wantsFieldset = true): self {
		$this->params['fieldset'] = $wantsFieldset;
		return $this;
	}

	public function fullWidth(): self {
		$this->params['fullWidth'] = true;
		return $this;
	}
}