<?php

namespace YahnisElsts\AdminMenuEditor\Customizable;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\WireContext;

class RevisedUiPack {
	private InterfaceStructure $structure;
	private array $extraSettingsToSerialize = [];

	public function __construct(InterfaceStructure $structure) {
		$this->structure = $structure;
	}

	public function serializeForJs(): array {
		$context = new WireContext();
		$settingsToSerialize = iterator_to_array($this->structure->getAllReferencedSettings($context));
		$settingsToSerialize = array_merge($settingsToSerialize, $this->extraSettingsToSerialize);

		return [
			'settingsPack'       => AbstractSetting::serializeSettingsForRevisedJs($settingsToSerialize),
			'interfaceStructure' => $this->structure->serializeForRevisedJs($context),
		];
	}

	public function enqueueDependencies() {
		$this->structure->enqueueRevisedComponentDependencies();
	}

	public function addExtraSetting(AbstractSetting $setting) {
		$this->extraSettingsToSerialize[$setting->getId()] = $setting;
	}
}