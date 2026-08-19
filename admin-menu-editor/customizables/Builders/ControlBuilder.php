<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Builders;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Settings;
use YahnisElsts\AdminMenuEditor\WireDSL\Expression;

/**
 * @template ElementClass
 */
class ControlBuilder extends BaseElementBuilder {
	/**
	 * @var \YahnisElsts\AdminMenuEditor\Customizable\Settings\Setting[]
	 */
	protected $settings;

	/**
	 * @param class-string<\YahnisElsts\AdminMenuEditor\Customizable\Controls\Control> $controlClass
	 * @param array $settings
	 * @param array $params
	 */
	public function __construct($controlClass, $settings = array(), $params = array(), $children = []) {
		parent::__construct($controlClass, $params, $children);
		$this->settings = $settings;
	}

	/**
	 * @param string|Expression|Binding|null $text
	 * @return $this
	 */
	public function label($text) {
		$this->params['label'] = $text;
		return $this;
	}

	/**
	 * @param ...$cssClassNames
	 * @return $this
	 */
	public function inputClasses(...$cssClassNames) {
		return $this->addItemsToArrayParam('inputClasses', $cssClassNames);
	}

	public function inputStyles($propertyPairs) {
		return $this->addItemsToArrayParam('inputStyles', $propertyPairs);
	}

	public function inputAttr($attributePairs) {
		return $this->addItemsToArrayParam('inputAttributes', $attributePairs);
	}

	public function setting(Settings\Setting $setting) {
		$this->settings[] = $setting;
		return $this;
	}

	/**
	 * @param bool|\YahnisElsts\AdminMenuEditor\Customizable\SettingCondition $enabled
	 * @return $this
	 */
	public function enabled($enabled) {
		$this->params['enabled'] = $enabled;
		return $this;
	}

	/**
	 * Wrap a new control group around this control.
	 *
	 * By default, the group will use the group title assigned to the associated
	 * setting, or the setting/control label.
	 *
	 * @param string|null $groupTitle
	 * @return GroupBuilder
	 */
	public function asGroup($groupTitle = null) {
		if ( $groupTitle === null ) {
			//Settings can have an optional group title assigned to them in case
			//a setting is displayed as a standalone group.
			$firstSetting = reset($this->settings);
			if ( $firstSetting instanceof Settings\AbstractSetting ) {
				$groupTitle = $firstSetting->getCustomGroupTitle();
			}

			if ( empty($groupTitle) ) {
				if ( !empty($this->params['label']) ) {
					//Use the control label as the group title.
					$groupTitle = $this->params['label'];
				} else if ( $firstSetting instanceof Settings\AbstractSetting ) {
					//Use the setting label.
					$groupTitle = $firstSetting->getLabel();
				}
			}
		}
		return new GroupBuilder($groupTitle, array($this));
	}

	public function getSettings() {
		return $this->settings;
	}

	/**
	 * @return ElementClass
	 */
	public function build() {
		$className = $this->elementClass;
		return new $className($this->settings, $this->buildParams(), $this->buildChildren());
	}
}