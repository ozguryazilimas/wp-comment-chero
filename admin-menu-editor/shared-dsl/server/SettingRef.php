<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;

class SettingRef extends Reference implements Binding {
	protected AbstractSetting $setting;

	public function __construct(AbstractSetting $setting) {
		$this->setting = $setting;
	}

	public function evaluate(?EvaluationContext $context = null) {
		return $this->setting->getValue();
	}

	public function getSetting(): AbstractSetting {
		return $this->setting;
	}

	public function jsonSerialize(): array {
		return [
			't'      => 'binding',
			'bind' => $this->setting->getId(),
		];
	}

	public function getInternalStringId(): string {
		return $this->setting->getId();
	}

	const RESOLUTION_KEY = 'settingRef';

	public function getResolutionStrategyKey(): string {
		return self::RESOLUTION_KEY;
	}
}