<?php

namespace YahnisElsts\AdminMenuEditor\Customizable;

use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\SettingContainer;

/**
 * Utility class that combines multiple SettingContainer instances into a single lookup interface.
 */
class CombinedSettingLookup implements SettingContainer {
	protected array $settingContainers;

	public function __construct(array $settingContainers) {
		$this->settingContainers = $settingContainers;
	}

	function findDirectChildSetting(string $key): ?AbstractSetting {
		foreach ($this->settingContainers as $container) {
			$setting = $container->findDirectChildSetting($key);
			if ( $setting !== null ) {
				return $setting;
			}
		}
		return null;
	}

	function findSetting(string $settingIdOrPath): ?AbstractSetting {
		foreach ($this->settingContainers as $container) {
			$setting = $container->findSetting($settingIdOrPath);
			if ( $setting !== null ) {
				return $setting;
			}
		}
		return null;
	}
}