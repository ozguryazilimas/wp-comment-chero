<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Rendering;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Control;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\ControlGroup;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Section;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class TabbedPanelRenderer extends ClassicRenderer {
	protected static $panelCounter = 0;

	protected $additionalStructureClasses = [];

	public function __construct($additionalStructureClasses = []) {
		$this->additionalStructureClasses = $additionalStructureClasses;
	}

	public function renderStructure(InterfaceStructure $structure, EvaluationContext $context) {
		$panelId = 'ame-tabbed-panel-' . (++self::$panelCounter);
		$structureClasses = array_merge(['ame-tabbed-panel'], $this->additionalStructureClasses);
		echo HtmlHelper::tag('div', ['class' => $structureClasses, 'id' => $panelId]);

		//Render a list of tabs.
		echo HtmlHelper::tag('ul', ['class' => 'ame-tp-tabs']);
		foreach ($structure->getAsSections() as $section) {
			echo '<li>';
			echo HtmlHelper::tag(
				'a',
				[
					'href'  => '#' . $this->getSectionElementId($section, $context),
					'class' => 'ame-tp-tab-link',
				],
				esc_html($section->getTitle())
			);
			echo '</li>';
		}
		echo '</ul>';

		echo HtmlHelper::tag('div', ['class' => 'ame-tp-content']);
		parent::renderStructure($structure, $context);
		echo '</div>';

		echo '</div>';
	}

	public function renderSection(Section $section, EvaluationContext $context) {
		echo HtmlHelper::tag(
			'div',
			[
				'id'    => $this->getSectionElementId($section, $context),
				'class' => array_merge(['ame-tp-section'], $section->getClasses()),
			]
		);

		echo '<h3 class="ame-tp-section-title">';
		$this->renderSectionTitleContent($section);
		echo '</h3>';


		echo '<div class="ame-tp-section-children">';
		$this->renderSectionChildren($section, $context);
		echo '</div>';
		echo '</div>';
	}

	protected function renderControlGroup(ControlGroup $group, EvaluationContext $context) {
		$isFieldset = $group->wantsFieldset();
		if ( $isFieldset === null ) {
			$isFieldset = false;
		}

		$classes = array_merge(array('ame-tp-control-group'), $group->getClasses());
		if ( $group->isFullWidth() ) {
			$classes[] = 'ame-tp-full-width-control-group';
		}
		echo HtmlHelper::tag('div', ['class' => $classes]);

		if ( !$group->isFullWidth() ) {
			echo HtmlHelper::tag('div', ['class' => 'ame-tp-control-group-title']);
			$this->renderGroupTitleContent($group);
			echo '</div>';
		}

		echo HtmlHelper::tag('div', ['class' => 'ame-tp-control-group-children']);
		if ( $isFieldset ) {
			echo HtmlHelper::tag('fieldset', ['disabled' => !$group->isEnabled($context)]);
		}

		$childContext = $context->withAttributes([
			self::IS_STACKED_ATTRIBUTE => $group->isStacked(),
		]);
		$this->renderGroupChildren($group, $childContext);

		if ( $isFieldset ) {
			echo '</fieldset>';
		}
		echo '</div>';

		echo '</div>';
	}

	public function renderControl(Control $control, EvaluationContext $context) {
		$addLineBreaks = ($context->getAttribute(self::IS_STACKED_ATTRIBUTE) && !$control->declinesExternalLineBreaks());
		if ( $addLineBreaks ) {
			echo '<p>';
		}

		parent::renderControl($control, $context);

		if ( $addLineBreaks ) {
			echo '</p>';
		}
	}


	protected function getSectionElementId(Section $section, ?EvaluationContext $context = null) {
		$suffix = $section->getHtmlIdBase($context);
		if ( empty($suffix) ) {
			$suffix = sanitize_key(
				$section->getTitle() . '-' . substr(sha1(spl_object_hash($section)), 0, 8)
			);
		}
		return 'ame-tp-section-' . $suffix;
	}

	public function enqueueDependencies(string $containerSelector = '') {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		parent::enqueueDependencies($containerSelector);

		wp_enqueue_auto_versioned_style(
			'ame-tabbed-panels',
			plugins_url('assets/tabbed-panels.css', AME_CUSTOMIZABLE_BASE_FILE)
		);

		if ( !(wp_script_is('jquery-ui-tabs', 'done') || wp_script_is('jquery-ui-tabs')) ) {
			wp_enqueue_script('jquery-ui-tabs');
		}

		add_action('admin_print_footer_scripts', [$this, 'outputInitScript'], 100);
	}

	public function outputInitScript() {
		?>
		<script type="text/javascript">
			jQuery(function ($) {
				$('.ame-tabbed-panel').tabs();
			});
		</script>
		<?php
	}
}