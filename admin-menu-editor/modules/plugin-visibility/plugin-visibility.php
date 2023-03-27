<?php

class amePluginVisibility extends amePersistentModule {
	const HIDE_USAGE_NOTICE_FLAG = 'ws_ame_hide_pv_notice';

	/**
	 * Any role that has any of the following capabilities has some degree of control
	 * over plugins, so plugin visibility settings apply to that role.
	 */
	const PLUGIN_MANAGEMENT_CAPS = array(
		'activate_plugins',
		'install_plugins',
		'edit_plugins',
		'update_plugins',
		'delete_plugins',
		'manage_network_plugins',
	);

	protected $optionName = 'ws_ame_plugin_visibility';

	protected $tabSlug = 'plugin-visibility';
	protected $tabTitle = 'Plugins';
	protected $tabOrder = 20;

	protected $defaultSettings = array(
		'plugins'              => array(),
		'grantAccessByDefault' => array(),
	);

	private static $lastInstance = null;

	/**
	 * @var Ajaw_v1_Action
	 */
	private $dismissNoticeAction;

	public function __construct($menuEditor) {
		parent::__construct($menuEditor);
		self::$lastInstance = $this;

		if ( !$this->isEnabledForRequest() ) {
			return;
		}

		//Remove "hidden" plugins from the list on the "Plugins -> Installed Plugins" page.
		add_filter('all_plugins', array($this, 'filterPluginList'), 15);

		//Hide updates for hidden plugins.
		add_filter('site_transient_update_plugins', array($this, 'filterPluginUpdates'), 15);

		//It's not possible to completely prevent a user from (de)activating "hidden" plugins because plugin API
		//functions like activate_plugin() and deactivate_plugins() don't provide a way to abort (de)activation.
		//However, we can still block edits and *some* other actions that WP verifies with check_admin_referer().
		add_action('check_admin_referer', array($this, 'authorizePluginAction'));

		//Register the plugin visibility tab.
		add_action('admin_menu_editor-header', array($this, 'handleFormSubmission'), 10, 2);

		//Display a usage hint in our tab.
		add_action('admin_notices', array($this, 'displayUsageNotice'));
		$this->dismissNoticeAction = ajaw_v1_CreateAction('ws_ame_dismiss_pv_usage_notice')
			->handler(array($this, 'ajaxDismissUsageNotice'))
			->permissionCallback(array($this->menuEditor, 'current_user_can_edit_menu'))
			->method('post')
			->register();
	}

	/**
	 * Check if a plugin is visible to the current user.
	 *
	 * Goals:
	 *  - You can easily hide a plugin from everyone, including new roles. See: isVisibleByDefault
	 *  - You can configure a role so that new plugins are hidden by default. See: grantAccessByDefault
	 *  - You can change visibility per role and per user, just like with admin menus.
	 *  - Roles that don't have access to plugins are not considered when deciding visibility.
	 *  - Precedence order: user > super admin > all roles.
	 *
	 * @param string $pluginFileName Plugin file name as returned by plugin_basename().
	 * @param WP_User $user          Current user.
	 * @return bool
	 */
	private function isPluginVisible($pluginFileName, $user = null) {
		//TODO: Can we refactor this to be shorter?
		static $isMultisite = null;
		if ( !isset($isMultisite) ) {
			$isMultisite = is_multisite();
		}

		if ( $user === null ) {
			$user = wp_get_current_user();
		}
		$settings = $this->loadSettings();

		//Do we have custom settings for this plugin?
		if ( isset($settings['plugins'][$pluginFileName]) ) {
			$isVisibleByDefault = ameUtils::get($settings['plugins'][$pluginFileName], 'isVisibleByDefault', true);
			$grantAccess = ameUtils::get($settings['plugins'][$pluginFileName], 'grantAccess', array());

			if ( $isVisibleByDefault ) {
				$grantAccess = array_merge($settings['grantAccessByDefault'], $grantAccess);
			}
		} else {
			$isVisibleByDefault = true;
			$grantAccess = $settings['grantAccessByDefault'];
		}

		//User settings take precedence over everything else.
		$userActor = 'user:' . $user->get('user_login');
		if ( isset($grantAccess[$userActor]) ) {
			return $grantAccess[$userActor];
		}

		//Super Admin is next.
		if ( $isMultisite && is_super_admin($user->ID) ) {
			//By default, the Super Admin has access to everything.
			return ameUtils::get($grantAccess, 'special:super_admin', true);
		}

		//Finally, the user can see the plugin if at least one of their roles can.
		$anyRoleHasSettings = false;
		$roles = $this->menuEditor->get_user_roles($user);
		foreach ($roles as $roleId) {
			/** @noinspection PhpRedundantOptionalArgumentInspection -- In case the default changes. */
			$hasAccess = ameUtils::get($grantAccess, 'role:' . $roleId, null);
			if ( $hasAccess !== null ) {
				$anyRoleHasSettings = true;
			} else {
				$hasAccess = $isVisibleByDefault && $this->roleCanManagePlugins($roleId);
			}

			if ( $hasAccess ) {
				return true;
			}
		}

		if ( $anyRoleHasSettings ) {
			//At least one role had per-plugin settings or access-by-default settings,
			//and those settings did not grant access.
			return false;
		} else if ( $isVisibleByDefault ) {
			//Check user capabilities.
			return $this->userCanManagePlugins($user);
		}
		return false;
	}


	/**
	 * @param string $roleId
	 * @param WP_Role $role
	 * @return bool
	 */
	private function roleCanManagePlugins($roleId, $role = null) {
		static $cache = array();

		if ( isset($cache[$roleId]) ) {
			return $cache[$roleId];
		}

		if ( !isset($role) ) {
			$role = get_role($roleId);
		}

		$result = false;
		foreach (self::PLUGIN_MANAGEMENT_CAPS as $cap) {
			if ( $role->has_cap($cap) ) {
				$result = true;
				break;
			}
		}

		$cache[$roleId] = $result;

		return $result;
	}

	/**
	 * @param \WP_User $user
	 * @return boolean
	 */
	private function userCanManagePlugins($user) {
		static $cache = array();
		$userId = $user->ID;
		if ( isset($cache[$userId]) ) {
			return $cache[$userId];
		}

		$result = false;
		foreach (self::PLUGIN_MANAGEMENT_CAPS as $cap) {
			if ( user_can($user, $cap) ) {
				$result = true;
				break;
			}
		}

		$cache[$userId] = $result;
		return $result;
	}

	/**
	 * Filter a plugin list by removing plugins that are not visible to the current user.
	 *
	 * @param array $plugins
	 * @return array
	 */
	public function filterPluginList($plugins) {
		if ( !is_array($plugins) && !($plugins instanceof ArrayAccess) ) {
			return $plugins;
		}

		$user = wp_get_current_user();
		$settings = $this->loadSettings();

		//Don't try to hide plugins outside the WP admin. It prevents WP-CLI from seeing all installed plugins.
		if ( !$user->exists() || !is_admin() ) {
			return $plugins;
		}

		$editableProperties = array(
			'Name'        => 'name',
			'Description' => 'description',
			'Author'      => 'author',
			'PluginURI'   => 'siteUrl',
			'AuthorURI'   => 'siteUrl',
			'Version'     => 'version',
		);

		$pluginFileNames = array_keys($plugins);
		foreach ($pluginFileNames as $fileName) {
			//Remove all hidden plugins.
			if ( !$this->isPluginVisible($fileName, $user) ) {
				unset($plugins[$fileName]);
				continue;
			}

			//Set custom names, descriptions, and other properties.
			foreach ($editableProperties as $header => $property) {
				$customValue = ameUtils::get($settings, array('plugins', $fileName, 'custom' . ucfirst($property)), '');
				if ( $customValue !== '' ) {
					$plugins[$fileName][$header] = $customValue;
				}
			}
		}

		return $plugins;
	}

	/**
	 * Filter out updates associated with plugins that are not visible to the current user.
	 *
	 * @param StdClass|null $updates
	 * @return StdClass|null
	 */
	public function filterPluginUpdates($updates) {
		if ( !isset($updates->response) || !is_array($updates->response) ) {
			//Either there are no updates or we don't recognize the format.
			return $updates;
		}

		//Let's not hide anything when no one is logged in. We don't check is_admin() here
		//because plugin updates can appear in the Toolbar and that's visible in the front-end.
		$user = wp_get_current_user();
		if ( !$user->exists() || (defined('DOING_CRON') && DOING_CRON) ) {
			return $updates;
		}

		$pluginFileNames = array_keys($updates->response);
		foreach ($pluginFileNames as $fileName) {
			//Remove all hidden plugins.
			if ( !$this->isPluginVisible($fileName, $user) ) {
				unset($updates->response[$fileName]);
				continue;
			}
		}

		return $updates;
	}

	/**
	 * Verify that the current user is allowed to see the plugin that they're trying to edit, activate or deactivate.
	 * Note that this doesn't catch bulk (de-)activation or various plugin management plugins.
	 *
	 * This is a callback for the "check_admin_referer" action.
	 *
	 * @param string $action
	 */
	public function authorizePluginAction($action) {
		//PHPCS special case: This hook callback runs inside a function that validates
		//nonces and selectively overrides the behaviour of that function.
		//phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- See above

		//Is the user trying to edit a plugin?
		if ( preg_match('@^edit-plugin_(?P<file>.+)$@', $action, $matches) ) {

			//The file that's being edited is part of a plugin. Find that plugin.
			$fileName = wp_normalize_path($matches['file']);
			$fileDirectory = ameUtils::getFirstDirectory($fileName);
			$selectedPlugin = null;

			$pluginFiles = array_keys(get_plugins());
			foreach ($pluginFiles as $pluginFile) {
				//Is the user editing the main plugin file?
				if ( $pluginFile === $fileName ) {
					$selectedPlugin = $pluginFile;
					break;
				}

				//Is the file inside this plugin's directory?
				$pluginDirectory = ameUtils::getFirstDirectory($pluginFile);
				if ( ($pluginDirectory !== null) && ($pluginDirectory === $fileDirectory) ) {
					$selectedPlugin = $pluginFile;
					break;
				}
			}

			if ( $selectedPlugin !== null ) {
				//Can the current user see the selected plugin?
				$isVisible = $this->isPluginVisible($selectedPlugin);

				if ( !$isVisible ) {
					wp_die('You do not have sufficient permissions to edit this plugin.');
				}
			}

			//Is the user trying to (de-)activate a single plugin?
		} elseif ( preg_match('@(?P<action>deactivate|activate)-plugin_(?P<plugin>.+)$@', $action, $matches) ) {
			//Can the current user see this plugin?
			$isVisible = $this->isPluginVisible($matches['plugin']);

			if ( !$isVisible ) {
				wp_die(sprintf(
					'You do not have sufficient permissions to %s this plugin.',
					esc_html($matches['action'])
				));
			}

			//Are they acting on multiple plugins? One of them might be hidden.
		} elseif ( ($action === 'bulk-plugins') && isset($_POST['checked']) && is_array($_POST['checked']) ) {

			$user = wp_get_current_user();
			foreach ($_POST['checked'] as $pluginFile) {
				if ( !$this->isPluginVisible(strval($pluginFile), $user) ) {
					wp_die(sprintf(
						'You do not have sufficient permissions to manage this plugin: "%s".',
						esc_html($pluginFile)
					));
				}
			}
		}
		//phpcs:enable
	}

	public function addSettingsTab($tabs) {
		$tabs[$this->tabSlug] = 'Plugins';
		return $tabs;
	}

	protected function getTemplateVariables($templateName) {
		$result = parent::getTemplateVariables($templateName);
		$result['tabUrl'] = $this->getTabUrl();
		return $result;
	}

	public function handleFormSubmission($action, $post = array()) {
		//Note: We don't need to check user permissions here because plugin core already did.
		if ( $action === 'save_plugin_visibility' ) {
			check_admin_referer($action);

			$this->settings = json_decode($post['settings'], true);
			$this->saveSettings();

			$params = array('message' => 1);

			//Re-select the same actor.
			if ( !empty($post['selected_actor']) ) {
				$params['selected_actor'] = strval($post['selected_actor']);
			}

			wp_redirect($this->getTabUrl($params));
			exit;
		}
	}

	public function enqueueTabScripts() {
		wp_register_auto_versioned_script(
			'ame-plugin-visibility',
			plugins_url('plugin-visibility.js', __FILE__),
			array(
				'ame-lodash',
				'ame-knockout',
				'ame-actor-selector',
				$this->dismissNoticeAction->getScriptHandle(),
			)
		);
		wp_enqueue_script('ame-plugin-visibility');

		//Reselect the same actor.
		$query = $this->menuEditor->get_query_params();
		$selectedActor = null;
		if ( isset($query['selected_actor']) ) {
			$selectedActor = strval($query['selected_actor']);
		}

		$scriptData = $this->getScriptData();
		$scriptData['selectedActor'] = $selectedActor;
		wp_localize_script('ame-plugin-visibility', 'wsPluginVisibilityData', $scriptData);
	}

	public function getScriptData() {
		//Pass the list of installed plugins and their state (active/inactive) to UI JavaScript.
		$installedPlugins = get_plugins();

		$activePlugins = array_map('plugin_basename', wp_get_active_and_valid_plugins());
		$activeNetworkPlugins = array();
		if ( function_exists('wp_get_active_network_plugins') ) {
			//This function is only available on Multisite.
			$activeNetworkPlugins = array_map('plugin_basename', wp_get_active_network_plugins());
		}

		$plugins = array();
		foreach ($installedPlugins as $pluginFile => $header) {
			$isActiveForNetwork = in_array($pluginFile, $activeNetworkPlugins);
			$isActive = in_array($pluginFile, $activePlugins);

			$plugins[] = array(
				'fileName' => $pluginFile,
				'isActive' => $isActive || $isActiveForNetwork,

				'name'        => $header['Name'],
				'description' => isset($header['Description']) ? $header['Description'] : '',
				'author'      => isset($header['Author']) ? $header['Author'] : '',
				'siteUrl'     => isset($header['PluginURI']) ? $header['PluginURI'] : '',
				'version'     => isset($header['Version']) ? $header['Version'] : '',
			);
		}

		//Flag roles that can manage plugins.
		$canManagePlugins = array();
		$wpRoles = ameRoleUtils::get_roles();
		foreach ($wpRoles->role_objects as $id => $role) {
			$canManagePlugins[$id] = $this->roleCanManagePlugins($id, $role);
		}

		return array(
			'settings'         => $this->loadSettings(),
			'installedPlugins' => $plugins,
			'canManagePlugins' => $canManagePlugins,
			'isMultisite'      => is_multisite(),
			'isProVersion'     => $this->menuEditor->is_pro_version(),
		);
	}

	public function enqueueTabStyles() {
		wp_enqueue_auto_versioned_style(
			'ame-plugin-visibility-css',
			plugins_url('plugin-visibility.css', __FILE__)
		);
	}

	public function displayUsageNotice() {
		if ( !$this->menuEditor->is_tab_open($this->tabSlug) ) {
			return;
		}

		//If the user has already made some changes, they probably don't need to see this notice any more.
		$settings = $this->loadSettings();
		if ( !empty($settings['plugins']) ) {
			return;
		}

		//The notice is dismissible.
		if ( get_site_option(self::HIDE_USAGE_NOTICE_FLAG, false) ) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible" id="ame-pv-usage-notice">
				<p>
					<strong>Tip:</strong> This screen lets you hide plugins from other users. 
					These settings only affect the "Plugins" page, not the admin menu or the dashboard.
				</p>
			 </div>';
	}

	public function ajaxDismissUsageNotice() {
		$result = update_site_option(self::HIDE_USAGE_NOTICE_FLAG, true);
		return array('success' => true, 'updateResult' => $result);
	}

	/**
	 * Get the most recently created instance of this class.
	 * Note: This function should only be used for testing purposes.
	 *
	 * @return amePluginVisibility|null
	 */
	public static function getLastCreatedInstance() {
		return self::$lastInstance;
	}

	/**
	 * Remove any visibility settings associated with the specified plugin.
	 *
	 * @param string $pluginFile
	 */
	public function forgetPlugin($pluginFile) {
		$settings = $this->loadSettings();
		unset($settings['plugins'][$pluginFile]);
		$this->settings = $settings;
		$this->saveSettings();
	}
}
