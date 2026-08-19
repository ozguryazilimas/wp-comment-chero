<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Settings;

interface SettingContainer {
	function findSetting(string $settingIdOrPath): ?AbstractSetting;

	function findDirectChildSetting(string $key): ?AbstractSetting;
}