<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

abstract class ClassicControl extends Control {
	/**
	 * Output description before or after the control.
	 */
	protected function outputSiblingDescription(?EvaluationContext $context = null) {
		$description = $this->getDescription($context);
		if ( !empty($description) ) {
			//HTML is intentionally allowed. The description should never contain user input.
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n", '<p class="description">', $description, '</p>';
		}
	}

	/**
	 * Output description inside the control. This is primarily intended
	 * for controls that are wrapped in a label element.
	 */
	protected function outputNestedDescription(?EvaluationContext $context = null) {
		$description = $this->getDescription($context);
		if ( !empty($description) ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::formatNestedDescription($description);
		}
	}

	/**
	 * Generate HTML for a description that is nested inside the control.
	 *
	 * @param string $content The description. HTML is allowed.
	 * @return string HTML code
	 */
	protected static function formatNestedDescription($content) {
		return "\n" . '<br><span class="description">' . $content . '</span>';
	}

	protected static function enqueueDependencies() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		//Use the Pro version control stylesheet if it exists.
		$proStylesheet = AME_ROOT_DIR . '/extras/pro-customizables/assets/controls.css';
		$proStylesheetExists = file_exists($proStylesheet);
		$isProbablyPro = $proStylesheetExists;
		if ( $proStylesheetExists ) {
			$stylesheetUrl = plugins_url('controls.css', $proStylesheet);
		} else {
			$stylesheetUrl = plugins_url('assets/controls.css', AME_CUSTOMIZABLE_BASE_FILE);
		}

		wp_enqueue_auto_versioned_style(
			'ame-combined-control-styles',
			$stylesheetUrl,
			['wp-color-picker']
		);

		$controlDependencies = ['jquery', 'wp-color-picker'];
		if ( $isProbablyPro ) {
			$controlDependencies[] = 'ame-ko-extensions';
		}
		wp_enqueue_auto_versioned_script(
			'ame-combined-control-scripts',
			plugins_url('assets/combined-controls.js', AME_CUSTOMIZABLE_BASE_FILE),
			$controlDependencies
		);

		//Also enqueue the Pro version's combined controls if the file exists.
		$proCombinedControls = AME_ROOT_DIR . '/extras/pro-customizables/assets/combined-pro-controls.js';
		if ( file_exists($proCombinedControls) ) {
			wp_enqueue_auto_versioned_script(
				'ame-combined-pro-control-scripts',
				plugins_url('combined-pro-controls.js', $proCombinedControls),
				$controlDependencies
			);
		}
	}

	public function enqueueKoComponentDependencies() {
		parent::enqueueKoComponentDependencies();

		//Due to late static binding, this should properly call the method on
		//the class that the current instance belongs to.
		static::enqueueDependencies();
	}
}