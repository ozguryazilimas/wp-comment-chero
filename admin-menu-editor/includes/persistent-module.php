<?php

use YahnisElsts\AdminMenuEditor\Customizable\Storage\ModuleSettings;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ScopedOptionStorage;

abstract class amePersistentModule extends ameModule {
	/**
	 * @var string Database option where module settings are stored.
	 */
	protected $optionName = '';

	/**
	 * @var ModuleSettings|array|null Module settings. NULL when settings haven't been loaded yet.
	 */
	protected $settings = null;

	/**
	 * @var array Default module settings.
	 */
	protected $defaultSettings = [];

	protected $settingsWrapperEnabled = false;

	protected $lastModifiedSettingEnabled = false;

	public function __construct($menuEditor, $moduleDir = null) {
		if ( $this->optionName === '' ) {
			throw new LogicException(__CLASS__ . '::$optionName is an empty string. You must set it to a valid option name.');
		}

		parent::__construct($menuEditor, $moduleDir);
	}

	public function loadSettings() {
		if ( isset($this->settings) ) {
			return $this->settings;
		}

		if ( $this->settingsWrapperEnabled ) {
			$scope = ($this->menuEditor->get_plugin_option('menu_config_scope') === 'site')
				? ScopedOptionStorage::SITE_SCOPE
				: ScopedOptionStorage::GLOBAL_SCOPE;

			$this->settings = new ModuleSettings(
				$this->optionName,
				$scope,
				$this->defaultSettings,
				[$this, 'createSettingInstances'],
				true,
				$this->lastModifiedSettingEnabled
			);
			$this->settings->addReadAliases($this->getSettingAliases());
		} else {
			$json = $this->getScopedOption($this->optionName, null);
			if ( is_string($json) && !empty($json) ) {
				$settings = json_decode($json, true);
				if ( !is_array($settings) ) {
					$settings = []; //JSON decoding failed, fall back to an empty array.
				}
			} else {
				$settings = [];
			}

			$this->settings = array_merge($this->defaultSettings, $settings);
		}

		return $this->settings;
	}

	public function saveSettings() {
		if ( $this->settingsWrapperEnabled ) {
			if ( $this->settings ) {
				$this->settings->save();
			}
		} else {
			$settings = wp_json_encode($this->settings);
			//Save per site or site-wide based on plugin configuration.
			$this->setScopedOption($this->optionName, $settings);
		}
	}

	public function mergeSettingsWith($newSettings) {
		if ( $this->settingsWrapperEnabled ) {
			$settings = $this->loadSettings();
			$settings->mergeWith($newSettings);
			return $settings->toArray();
		} else {
			$this->settings = array_merge($this->loadSettings(), $newSettings);
			return $this->settings;
		}
	}

	protected function getTemplateVariables($templateName) {
		$variables = parent::getTemplateVariables($templateName);
		if ( $templateName === $this->moduleId ) {
			$variables = array_merge(
				$variables,
				[
					'settings' => $this->loadSettings(),
				]
			);
		}
		return $variables;
	}

	public function createSettingInstances(ModuleSettings $settings) {
		//Subclasses should override this to create Setting instances.
		return [];
	}

	protected function getSettingAliases() {
		return [];
	}

	/**
	 * Is it meaningful and safe to export the module settings?
	 *
	 * Defaults to true. Subclasses should override this if necessary. Some modules have settings
	 * that only make sense on the current site, and exporting them either wouldn't work or would
	 * break things on the target site.
	 *
	 * Note that the module doesn't have to implement any export functionality to return true here.
	 * This method is intended for external code that wants to know if the module should be exported.
	 *
	 * @return bool
	 */
	public function isSuitableForExport() {
		return true;
	}
}