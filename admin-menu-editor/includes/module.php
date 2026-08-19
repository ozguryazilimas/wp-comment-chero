<?php

use YahnisElsts\WpDependencyWrapper\v1\ScriptDependency;

abstract class ameModule {
	protected $tabSlug = '';
	protected $tabTitle = '';
	protected $tabOrder = 10;

	protected $moduleId = '';
	protected $moduleDir = '';

	protected $settingsFormAction = '';

	/**
	 * CSS stylesheets to automatically enqueue in the module tab.
	 *
	 * @var array<string,string> [$handle => $relativeUrl]
	 */
	protected $localTabStyles = [];

	/**
	 * @var WPMenuEditor
	 */
	protected $menuEditor;

	public function __construct($menuEditor, $moduleDir = null) {
		$this->menuEditor = $menuEditor;

		if ( $moduleDir !== null ) {
			$this->moduleDir = rtrim($moduleDir, '/');
			$this->moduleId = basename($this->moduleDir);
		} else if ( class_exists('ReflectionClass', false) ) {
			//This should never throw an exception since the current class must exist for this constructor to be run.
			$reflector = new ReflectionClass(get_class($this));
			$this->moduleDir = dirname($reflector->getFileName());
			$this->moduleId = basename($this->moduleDir);
		}

		if ( !$this->isEnabledForRequest() ) {
			return;
		}

		add_action('admin_menu_editor-register_scripts', array($this, 'registerScripts'));
		add_filter('admin_menu_editor-base_scripts', array($this, 'addBaseScripts'));

		//Register the module tab.
		if ( ($this->tabSlug !== '') && is_string($this->tabSlug) ) {
			add_action('admin_menu_editor-tabs', array($this, 'addTab'), $this->tabOrder);
			add_action('admin_menu_editor-section-' . $this->tabSlug, array($this, 'displaySettingsPage'));

			add_action('admin_menu_editor-enqueue_scripts-' . $this->tabSlug, array($this, 'enqueueTabScripts'));
			add_action('admin_menu_editor-enqueue_styles-' . $this->tabSlug, array($this, 'enqueueTabStyles'));

			//Optionally, handle settings form submission.
			if ( $this->settingsFormAction !== '' ) {
				add_action(
					'admin_menu_editor-page_action-' . $this->settingsFormAction,
					array($this, '_processAction')
				);
			}
		}
	}

	/**
	 * Does this module need to do anything for the current request?
	 *
	 * For example, some modules work in the normal dashboard but not in the network admin.
	 * Other modules don't need to run during AJAX requests or when WP is running Cron jobs.
	 */
	protected function isEnabledForRequest() {
		return true;
	}

	public function addTab($tabs) {
		$tabs[$this->tabSlug] = !empty($this->tabTitle) ? $this->tabTitle : $this->tabSlug;
		return $tabs;
	}

	public function displaySettingsPage() {
		$this->menuEditor->display_settings_page_header($this->getWrapClasses());

		if ( !$this->outputMainTemplate() ) {
			printf(
				"[ %1\$s : Module \"%2\$s\" doesn't have a primary template. ]",
				esc_html(__METHOD__),
				esc_html($this->moduleId)
			);
		}

		$this->menuEditor->display_settings_page_footer();
	}

	public function getTabUrl($queryParameters = array()) {
		$queryParameters = array_merge(
			array('sub_section' => $this->tabSlug),
			$queryParameters
		);
		return $this->menuEditor->get_plugin_page_url($queryParameters);
	}

	protected function outputMainTemplate() {
		return $this->outputTemplate($this->moduleId);
	}

	public function outputTemplate($name) {
		$templateFile = $this->moduleDir . '/' . $name . '-template.php';
		if ( file_exists($templateFile) ) {
			$moduleTabUrl = $this->getTabUrl();

			$templateVariables = $this->getTemplateVariables($name);
			if ( !empty($templateVariables) ) {
				extract($templateVariables, EXTR_SKIP);
			}

			require $templateFile;
			return true;
		}
		return false;
	}

	protected function getTemplateVariables($templateName) {
		//Override this method to pass variables to a template.
		return array();
	}

	/**
	 * Get extra CSS classes to add to the .wrap element in the module tab.
	 *
	 * @return string[]
	 */
	protected function getWrapClasses() {
		return [];
	}

	public function registerScripts() {
		//Override this method to register scripts.
	}

	public function enqueueTabScripts() {
		//Override this method to add scripts to the $this->tabSlug tab.
	}

	public function enqueueTabStyles() {
		//Enqueue predefined local styles.
		foreach ($this->localTabStyles as $handle => $relativePath) {
			$this->enqueueLocalStyle($handle, $relativePath);
		}

		//Override this method to add more stylesheets to the $this->tabSlug tab.
	}

	/**
	 * Add script dependencies that can be used in other parts of the plugin, not just this module.
	 *
	 * @param ScriptDependency[] $deps
	 * @return ScriptDependency[]
	 */
	public function addBaseScripts(array $deps): array {
		return $deps;
	}

	/**
	 * @access private
	 * @param array $post
	 */
	public function _processAction($post = array()) {
		check_admin_referer($this->settingsFormAction);
		$this->handleSettingsForm($post);
	}

	public function handleSettingsForm($post = array()) {
		//Override this method to process a form submitted from the module's tab.
	}

	public function getSettingsFormAction(): string {
		return $this->settingsFormAction;
	}

	protected function getScopedOption($name, $defaultValue = null) {
		if ( $this->menuEditor->get_plugin_option('menu_config_scope') === 'site' ) {
			return get_option($name, $defaultValue);
		} else {
			return get_site_option($name, $defaultValue);
		}
	}

	protected function setScopedOption($name, $value, $autoload = null) {
		if ( $this->menuEditor->get_plugin_option('menu_config_scope') === 'site' ) {
			update_option($name, $value, $autoload);
		} else {
			WPMenuEditor::atomic_update_site_option($name, $value);
		}
	}

	public function getModuleId() {
		return $this->moduleId;
	}

	public function getTabTitle() {
		return $this->tabTitle;
	}

	protected function enqueueLocalScript($handle, $relativePath, $dependencies = [], $inFooter = false) {
		list($scriptUrl, $version) = $this->findModuleBrowserDependency($relativePath);
		wp_enqueue_script($handle, $scriptUrl, $dependencies, $version, $inFooter);
	}

	protected function enqueueLocalStyle($handle, $relativePath, $dependencies = [], $media = 'all') {
		list($styleUrl, $version) = $this->findModuleBrowserDependency($relativePath);
		wp_enqueue_style($handle, $styleUrl, $dependencies, $version, $media);
	}

	protected function registerLocalStyle($handle, $relativePath, $dependencies = [], $media = 'all') {
		list($styleUrl, $version) = $this->findModuleBrowserDependency($relativePath);
		wp_register_style($handle, $styleUrl, $dependencies, $version, $media);
	}

	public function registerLocalScript($handle, $relativePath, $dependencies = [], $inFooter = false): ScriptDependency {
		$dependency = $this->createScriptDependency($relativePath, $handle);
		if ( $inFooter ) {
			$dependency->setInFooter();
		}
		if ( !empty($dependencies) ) {
			$dependency->addDependencies(...$dependencies);
		}
		return $dependency->register();
	}

	/**
	 * @param string $relativePath
	 * @param string|null $handle
	 * @return ScriptDependency
	 */
	public function createScriptDependency($relativePath, $handle = null) {
		$relativePath = ltrim($relativePath, '/');
		$fullPath = $this->moduleDir . '/' . $relativePath;

		return ScriptDependency::create(
			plugins_url($relativePath, $this->moduleDir . '/dummy.php'),
			$handle,
			$fullPath
		);
	}

	/**
	 * @param string $relativePath
	 * @return string[] Dependency URL and version, in that order.
	 */
	private function findModuleBrowserDependency($relativePath) {
		$relativePath = ltrim($relativePath, '/');
		$url = plugins_url($relativePath, $this->moduleDir . '/dummy.php');
		$version = $this->getModuleFileVersion($relativePath);
		return array($url, $version);
	}

	/**
	 * @param string $relativePath
	 * @return string|false
	 */
	private function getModuleFileVersion($relativePath) {
		$fullPath = $this->moduleDir . '/' . $relativePath;
		if ( is_file($fullPath) ) {
			$modTime = filemtime($fullPath);
			if ( $modTime !== false ) {
				return (string)$modTime;
			}
		}
		return false;
	}
}

class ameBaseScriptDependencies implements ArrayAccess, IteratorAggregate {
	protected $deps = [];

	public function __construct(array $deps) {
		$this->deps = $deps;
	}

	public function list(): ameBaseDependencyCollection {
		return new ameBaseDependencyCollection($this);
	}

	public function __invoke(): ameBaseDependencyCollection {
		return $this->list();
	}

	//region Array compatibility
	public function offsetExists($offset): bool {
		return isset($this->deps[$offset]);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		return $this->deps[$offset] ?? null;
	}

	public function offsetSet($offset, $value): void {
		$this->deps[$offset] = $value;
	}

	public function offsetUnset($offset): void {
		unset($this->deps[$offset]);
	}

	public function getIterator(): Traversable {
		return new ArrayIterator($this->deps);
	}
	//endregion
}

class ameBaseDependencyCollection implements IteratorAggregate {
	protected $deps;
	protected $selectedDeps = [];

	public function __construct(ameBaseScriptDependencies $deps) {
		$this->deps = $deps;
	}

	protected function add($handle) {
		$dep = $this->deps[$handle] ?? null;
		if ( $dep !== null ) {
			$this->selectedDeps[$handle] = $dep;
		} else {
			throw new InvalidArgumentException("Dependency with handle \"$handle\" not found.");
		}
	}

	public function getIterator(): Traversable {
		//PHP < 8.1 compatibility: We use the iterator with a splat operator later, which
		//doesn't support arrays/Traversable with string keys. So we reindex the array here.
		return new ArrayIterator(array_values($this->selectedDeps));
	}

	public function ko(): self {
		$this->add('ame-knockout');
		return $this;
	}

	public function koExtensions(): self {
		if ( !empty($this->deps['ame-ko-extensions']) ) {
			$this->add('ame-ko-extensions');
			//Note: We don't need to explicitly include "ame-knockout" or "ame-free-ko-extensions" since
			//"ame-ko-extensions" already has those as a dependencies.
		} else {
			//Fallback for the free version.
			$this->add('ame-free-ko-extensions');
		}
		return $this;
	}

	public function koSortable(): self {
		$this->add('ame-knockout-sortable');
		return $this;
	}

	public function actorManager(): self {
		$this->add('ame-actor-manager');
		return $this;
	}

	public function selector(): self {
		$this->add('ame-actor-selector');
		$this->add('ame-actor-manager');
		return $this;
	}

	public function cookies(): self {
		$this->add('ame-jquery-cookie');
		return $this;
	}

	public function qtip(): self {
		$this->add('jquery-qtip');
		return $this;
	}

	public function lodash(): self {
		$this->add('ame-lodash');
		return $this;
	}

	public function proCommonLib(): self {
		$this->add('ame-pro-common-lib');
		return $this;
	}

	/**
	 * Add Knockout and it's extensions, actor selector/manager, and Lodash.
	 *
	 * This is a common combination used in multiple modules.
	 *
	 * @return self
	 */
	public function koPackage(): self {
		return $this->koExtensions()->selector()->lodash();
	}
}