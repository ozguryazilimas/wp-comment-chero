<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Design;

require_once __DIR__ . '/constants.php';

use ameModule;
use WPMenuEditor;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\ElementBuilderFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\SettingFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Tooltip;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Boolean;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SingularSetting;
use YahnisElsts\AdminMenuEditor\Customizable\SettingsForm;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\AbstractSettingsDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\CompressedStorage;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\ScopedOptionStorage;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

//todo: This can be in a namespaces because that requires PHP 5.3 and we already require PHP 5.6.

//TODO: This could be a "Core Settings Module" that is always loaded, defines the UI, and handles form submission.
class SampleModule extends ameModule {
	protected $tabSlug = 'settings-refactor';
	protected $tabTitle = 'Settings X';

	protected $settingsFormAction = 'save-settings-v2';

	/**
	 * @var SettingsForm|null
	 */
	private $form = null;

	/**
	 * @var AmeCoreSettings|null
	 */
	private $settings = null;

	protected function outputMainTemplate() {
		$this->getSettingsForm()->output();
		return true;
	}

	public function handleSettingsForm($post = array()) {
		$this->getSettingsForm()->handleUpdateRequest($post);
	}

	private function getSettingsForm() {
		if ( $this->form === null ) {
			$this->form = SettingsForm::builder($this->settingsFormAction)
				->id('ws_plugin_settings_form')
				->structure($this->getInterfaceStructure())
				->settings($this->getSettingsDictionary()->getRegisteredSettings())
				->submitUrl($this->getTabUrl(array('noheader' => 1)))
				->redirectAfterSaving($this->getTabUrl(), array('message' => '1'))
				->treatMissingFieldsAsEmpty()
				->postProcessSettings(
				/**
				 * @param $values
				 * @param array<string,\YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting> $settingsById
				 * @return void
				 */
					function ($values, $settingsById) {
						//Update the allowed user ID when changing "who can access this plugin".
						if ( isset($settingsById['plugin_access'], $settingsById['allowed_user_id']) ) {
							if ( $settingsById['plugin_access']->getValue() === 'specific_user' ) {
								$settingsById['allowed_user_id']->update(get_current_user_id());
							} else {
								$settingsById['allowed_user_id']->update(null);
							}
						}
					}
				)
				->build();
		}
		return $this->form;
	}

	private function getSettingsDictionary() {
		if ( $this->settings === null ) {
			$optionName = $this->menuEditor->is_pro_version() ? 'ws_menu_editor_pro' : 'ws_menu_editor';

			$store = new ScopedOptionStorage(
				$optionName,
				//Core settings are always global even if menu settings are per-site.
				ScopedOptionStorage::GLOBAL_SCOPE
			);

			$this->settings = new AmeCoreSettings($this->menuEditor, $store);
		}
		return $this->settings;
	}

	private function getInterfaceStructure() {
		$isProVersion = $this->menuEditor->is_pro_version();
		$settings = $this->getSettingsDictionary();

		$b = new ElementBuilderFactory($settings);
		$structure = $b->structure(
			$b->group(
				'Who can access this plugin',
				$b->radioGroup('plugin_access'),
				$b->auto('hide_plugin_from_others')
			)->stacked(),

			$b->auto('menu_config_scope'),

			$this->createModulesGroup($b),

			$b->group(
				'Interface',
				$b->auto('hide_advanced_settings'),
				$b->auto('show_deprecated_hide_button')->onlyIf($isProVersion)
			)->stacked(),

			$b->group(
				'Editor colour scheme',
				$b->radioGroup('ui_colour_scheme')
			),

			$b->auto('submenu_icons_enabled')->onlyIf($isProVersion),

			$b->group(
				'New menu visibility',
				$b->radioGroup('unused_item_permissions')
			)
				->onlyIf($isProVersion)
				->tooltip(
					"This setting controls the default permissions of menu items that are
					 not present in the last saved menu configuration.
					 <br><br>
					 This includes new menus added by plugins and themes.
					 In Multisite, it also applies to menus that exist on some sites but not others.
					 It doesn't affect menu items that you add through the Admin Menu Editor interface."
				),

			$b->auto('unused_item_position')
				->asGroup()
				->tooltip(
					"This setting controls the position of menu items that are not present 
					 in the last saved menu	configuration.
					 <br><br>
					 This includes new menus added by plugins and themes.
					 In Multisite, it also applies to menus that exist only on certain sites but not on all sites.
					 It doesn't affect menu items that you add through the Admin Menu Editor interface."
				),

			$b->auto('deep_nesting_enabled')
				->asGroup()
				->tooltip(
					"Caution: Experimental feature.<br>
					 This feature might not work as expected, and it could cause conflicts with other plugins or themes.",
					Tooltip::EXPERIMENTAL
				)
				//The free version lacks the ability to render deeply nested menus in the dashboard, so the nesting
				//options are hidden by default. However, if the user somehow acquires a configuration where this
				//feature is enabled (e.g. by importing config from the Pro version), the free version can display
				//and even edit that configuration to a limited extent.
				->onlyIf(
					$isProVersion || $settings->get('was_nesting_ever_changed')
				),

			$b->group(
				'WPML support',
				$b->auto('wpml_support_enabled')
			)->stacked(),

			$b->group(
				'bbPress override',
				$b->auto('bbpress_override_enabled')
			)->stacked(),

			$b->auto('error_verbosity'),

			$b->group(
				'Debugging',
				$b->auto('security_logging_enabled'),
				$b->auto('force_custom_dashicons'),
				$b->auto('compress_custom_menu')
			)->stacked(),

			$b->group(
				'Server info',
				$b->html(
					"<figure>
						<figcaption>PHP error log:</figcaption>

						<code>" . esc_html(ini_get('error_log')) . "</code>
					</figure>

					<figure>
						<figcaption>PHP memory usage:</figcaption> "
					. sprintf(
						'%.2f MiB of %s',
						memory_get_peak_usage() / (1024 * 1024),
						esc_html(ini_get('memory_limit'))
					)
					. " </figure>"
				)
			)
		);

		return $structure->build();
	}

	private function createModulesGroup(ElementBuilderFactory $b) {
		$activeModulesGroup = $b->group('Modules')
			->id('ame-available-modules')
			->stacked()
			->fieldset()
			->tooltip(
				'Modules are plugin features that can be turned on or off. <br>'
				. 'Turning off unused features will slightly increase performance '
				. 'and may help with certain compatibility issues.'
			);

		foreach ($this->menuEditor->get_available_modules() as $id => $module) {
			if ( !empty($module['isAlwaysActive']) ) {
				continue;
			}

			$isCompatible = $this->menuEditor->is_module_compatible($module);
			$compatibilityNote = '';
			if ( !$isCompatible && !empty($module['requiredPhpVersion']) ) {
				if ( version_compare(phpversion(), $module['requiredPhpVersion'], '<') ) {
					$compatibilityNote = sprintf(
						'Required PHP version: %1$s or later. Installed PHP version: %2$s',
						htmlspecialchars($module['requiredPhpVersion']),
						htmlspecialchars(phpversion())
					);
				}
			}

			$activeModulesGroup->add(
				$b->checkbox()
					->label(htmlspecialchars(!empty($module['title']) ? $module['title'] : $id))
					->description($compatibilityNote)
					->enabled($isCompatible)
					->params(array(
						'inputAttributes' => array(
							'name'    => 'is_active_module[]',
							'value'   => $id,
							'checked' => $this->menuEditor->is_module_active($id, $module),
						),
					))
			);
		}
		return $activeModulesGroup;
	}
}

class AmeCoreSettings extends AbstractSettingsDictionary {
	/**
	 * @var WPMenuEditor
	 */
	protected $menuEditor;

	public function __construct(WPMenuEditor $menuEditor, ?StorageInterface $store = null) {
		if ( !isset($store) ) {
			$store = new ScopedOptionStorage(
				'ws_menu_editor_pro',
				ScopedOptionStorage::GLOBAL_SCOPE
			);
		}
		$this->menuEditor = $menuEditor;
		parent::__construct($store);

		if ( $this->store instanceof CompressedStorage ) {
			$this->store->setCompressionEnabled($this->get('compress_custom_menu', false));
		}
	}

	protected function createDefaults() {
		return $this->menuEditor->get_default_options();
	}

	protected function createSettings() {
		$factory = new SettingFactory($this->store, $this->defaults);

		$isMultisite = is_multisite();
		$isSuperAdmin = is_super_admin();
		$isProVersion = $this->menuEditor->is_pro_version();
		$currentUser = wp_get_current_user();
		$menuEditor = $this->menuEditor;

		$settings = array(
			$factory->stringEnum(
				'plugin_access',
				array('super_admin', 'manage_options', 'specific_user'),
				'Who can access this plugin'
			)
				->describeChoice(
					'super_admin',
					'Super Admin',
					$isMultisite ? null : 'On a single site installation this is usually the same as the Administrator role.',
					$isSuperAdmin
				)
				->describeChoice(
					'manage_options',
					'Anyone with the "manage_options" capability',
					'By default only Administrators have this capability.',
					current_user_can('manage_options')
				)
				->describeChoice(
					'specific_user',
					'Only the current user',
					'Login: ' . esc_html($currentUser->user_login) . ', user ID: ' . esc_html(get_current_user_id()),
					//In Multisite only Super Admins can choose this option.
					$isSuperAdmin || !$isMultisite
				),
			$factory->integer(
				'allowed_user_id',
				'(Internal) ID of the user that can access the plugin when "plugin_access" is "specific_user".',
				array(
					'default'    => null,
					'isEditable' => '__return_false', //Not directly editable by the user.
				)
			),
			new AmeHidePluginSetting('hide_plugin_from_others', $this->store),
			$factory->stringEnum(
				'menu_config_scope',
				array('global', 'site'),
				'Multisite settings',
				array(
					'isEditable' => function () use ($isMultisite) {
						return $isMultisite && is_super_admin();
					},
				)
			)
				->describeChoice(
					'global',
					'Global &mdash;	Use the same admin menu settings for all network sites.'
				)
				->describeChoice(
					'site',
					'Per-site &mdash; Use different admin menu settings for each site.'
				),
			$factory->boolean(
				'hide_advanced_settings',
				'Hide advanced menu options by default'
			),
			$factory->boolean(
				'security_logging_enabled',
				'Show menu access checks performed by the plugin on every admin page',
				array(
					'description' => "This can help track down configuration problems 
						and figure out why your menu permissions don't work the way they should.

						Note: It's not recommended to use this option on a live site as
						it can reveal information about your menu configuration.",
				)
			),
			$factory->boolean(
				'show_deprecated_hide_button',
				'Enable the "Hide (cosmetic)" toolbar button',
				array(
					'description' => "This button hides the selected menu item without making it inaccessible.",
				)
			),
			$factory->stringEnum(
				'submenu_icons_enabled',
				array('always', 'if_custom', 'never'),
				'Show submenu icons'
			)->describeChoice('if_custom', 'Only when manually selected'),
			$factory->boolean(
				'force_custom_dashicons',
				'Attempt to override menu icon CSS that was added by other plugins'
			),
			$factory->stringEnum(
				'ui_colour_scheme',
				array('classic', 'modern-one', 'wp-grey'),
				'Editor colour scheme'
			)
				->describeChoice('classic', 'Blue and yellow')
				->describeChoice('modern-one', 'Modern')
				->describeChoice('wp-grey', 'Grey'),
			$factory->stringEnum(
				'unused_item_position',
				array('relative', 'bottom'),
				'New menu position'
			)
				->describeChoice(
					'relative',
					'Maintain relative order',
					'Attempts to put new items in the same relative positions '
					. 'as they would be in in the default admin menu.'
				)
				->describeChoice(
					'bottom',
					'Bottom',
					'Puts new items at the bottom of the admin menu.'
				),
			$factory->stringEnum(
				'unused_item_permissions',
				array('unchanged', 'match_plugin_access'),
				'New menu visibility'
			)
				->describeChoice(
					'unchanged',
					'Leave unchanged (default)',
					'No special restrictions. Visibility will depend on the plugin that added the menus.'
				)
				->describeChoice(
					'match_plugin_access',
					'Show only to users who can access this plugin',
					'Automatically hides all new and unrecognized menus from regular users. '
					. 'To make new menus visible, you have to manually enable them in the menu editor.'
				),
			$factory->enum(
				'error_verbosity',
				array(
					WPMenuEditor::VERBOSITY_LOW,
					WPMenuEditor::VERBOSITY_NORMAL,
					WPMenuEditor::VERBOSITY_VERBOSE,
				),
				'Error verbosity level'
			)
				->describeChoice(
					WPMenuEditor::VERBOSITY_LOW,
					'Low',
					'Shows a generic error message without any details.'
				)
				->describeChoice(
					WPMenuEditor::VERBOSITY_NORMAL,
					'Normal',
					'Shows a one or two sentence explanation. For example: "The current'
					. ' user doesn\'t have the "manage_options" capability that is required'
					. ' to access the "Settings" menu item."'
				)
				->describeChoice(
					WPMenuEditor::VERBOSITY_VERBOSE,
					'Verbose',
					'Like "normal", but also includes a log of menu settings and permissions
					 that caused the current menu to be hidden. Useful for debugging.'
				),
			$factory->boolean(
				'compress_custom_menu',
				"Compress menu configuration data that's stored in the database",
				array(
					'description' => sprintf(
						"Significantly reduces the size of the <code>%s</code> DB option,
						but adds decompression overhead to every page.",
						esc_html($this->store->getStorageKey())
					),
				)
			),
			$factory->boolean(
				'wpml_support_enabled',
				'Make edited menu titles translatable with WPML',
				array(
					'description' => 'The titles will appear in the "Strings" section in WPML. '
						. 'If you don\'t use WPML or a similar translation plugin, '
						. 'you can safely disable this option.',
				)
			),
			$factory->boolean(
				'bbpress_override_enabled',
				'Prevent bbPress from resetting role capabilities',
				array(
					'description' => 'By default, bbPress will automatically undo any '
						. 'changes that are made to dynamic bbPress roles. Enable this '
						. 'option to override that behaviour and make it possible to '
						. 'change bbPress role capabilities.',
				)
			),
			$factory->enum(
				'deep_nesting_enabled',
				array(null, true, false),
				'Three level menus',
				array('default' => null)
			)
				->describeChoice(null, 'Ask on first use')
				->describeChoice(true, 'Enabled' . ($isProVersion ? '' : ' (only in editor)'))
				->describeChoice(false, 'Disabled'),
			$factory->custom(
				'is_active_module',
				'array',
				function ($inputValue) use ($menuEditor) {
					if ( empty($inputValue) ) {
						return array();
					}

					//Convert to [$moduleId => $enabled].
					$activeModules = (array)$inputValue;
					$activeModules = array_fill_keys(array_map('strval', $activeModules), true);

					//Filter out modules that are invalid or not installed.
					$availableModules = $menuEditor->get_available_modules();
					$activeModules = array_intersect_key($activeModules, $availableModules);

					//Explicitly set disabled modules to false.
					return array_merge(
						array_map('__return_false', $availableModules),
						$activeModules
					);
				},
				'Modules'
			),
		);

		//Index settings by ID.
		$result = array();
		foreach ($settings as $setting) {
			$result[$setting->getId()] = $setting;
		}
		return $result;
	}
}

class AmeHidePluginSetting extends SingularSetting {
	const SETTING_KEY = 'plugins_page_allowed_user_id';

	protected $defaultValue = false;

	public function __construct($id, ?StorageInterface $store = null, $params = array()) {
		$isProVersion = self::isProVersion();
		if ( !isset($params['label']) ) {
			$label = 'Hide "Admin Menu Editor' . ($isProVersion ? ' Pro' : '') . '"';
			if ( defined('WS_ADMIN_BAR_EDITOR_FILE') || defined('AME_BRANDING_ADD_ON_FILE') ) {
				$label .= ' and its add-ons ';
			}
			$label .= ' from the "Plugins" page for other users';
			if ( !$isProVersion ) {
				$label .= ' (Pro version only)';
			}
			$params['label'] = $label;
		}

		$schema = new Boolean($params['label']);
		if ( array_key_exists('default', $params) ) {
			$schema = $schema->defaultValue($params['default']);
		}

		parent::__construct($schema, $id, $store, $params);
	}

	public function getValue($customDefault = null) {
		/** @noinspection PhpRedundantOptionalArgumentInspection */
		$userId = $this->store->getPath(self::SETTING_KEY, null);
		return ($userId !== null);
	}

	public function update($validValue) {
		if ( !$this->store ) {
			return false;
		}
		$success = $this->store->setPath(
			self::SETTING_KEY,
			$validValue ? get_current_user_id() : null
		);
		$this->notifyUpdated();
		return $success;
	}

	public function isEditableByUser(?EvaluationContext $context = null): bool {
		if ( !self::isProVersion() ) {
			return false;
		}

		if ( is_multisite() ) {
			$allowed = is_super_admin();
		} else {
			$allowed = current_user_can('manage_options');
		}
		return $allowed && parent::isEditableByUser($context);
	}

	protected static function isProVersion() {
		static $isPro = null;
		if ( $isPro === null ) {
			$isPro = apply_filters('admin_menu_editor_is_pro', false);
		}
		return $isPro;
	}
}