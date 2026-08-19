<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Builders;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\EventButton;
use YahnisElsts\AdminMenuEditor\WireDSL\Expression;

class EventButtonBuilder extends ControlBuilder {
	public function __construct($params = []) {
		parent::__construct(EventButton::class, [], $params);
	}

	public function eventName(string $name): self {
		$this->params['eventName'] = $name;
		return $this;
	}

	/**
	 * @param mixed|Expression $data
	 * @return $this
	 */
	public function eventData($data): self {
		$this->params['eventData'] = $data;
		return $this;
	}

	public function iconClass(string $class): self {
		$this->params['iconClass'] = $class;
		return $this;
	}

	public function kind(string $kind): self {
		if ( !isset(EventButton::VALID_KINDS[$kind]) ) {
			throw new \InvalidArgumentException("Invalid kind: $kind");
		}
		$this->params['kind'] = $kind;
		return $this;
	}
}