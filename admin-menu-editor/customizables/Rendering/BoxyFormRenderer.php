<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Rendering;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\ControlGroup;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Section;
use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class BoxyFormRenderer extends ClassicRenderer {
	protected $fullWidthGroupsEnabled = false;

	public function renderStructure(InterfaceStructure $structure, EvaluationContext $context) {
		//Separate sections into the main column and sidebar.
		$main = [];
		$sidebar = [];
		foreach ($structure->getAsSections() as $section) {
			if ( $section->getId() === 'sidebar' ) {
				$sidebar[] = $section;
			} else {
				$main[] = $section;
			}
		}

		if ( empty($sidebar) ) {
			parent::renderStructure($structure, $context);
			return;
		}

		//Render the main column.
		echo '<div class="ame-form-box-main-column">';
		foreach ($main as $section) {
			$this->renderSection($section, $context);
		}
		echo '</div>';

		//Render the sidebar.
		echo '<div class="ame-form-box-sidebar-column">';
		$this->fullWidthGroupsEnabled = true;
		foreach ($sidebar as $section) {
			$this->renderSection($section, $context);
		}
		$this->fullWidthGroupsEnabled = false;
		echo '</div>';
		echo '<div class="clear"></div>';
	}

	public function renderSection(Section $section, EvaluationContext $context) {
		echo HtmlHelper::tag('div', ['class' => 'ame-form-section ame-form-box']);

		$title = $section->getTitle();
		if ( !empty($title) ) {
			echo '<div class="ame-form-box-header">';
			echo '<h2 class="ame-form-box-title">';
			$this->renderSectionTitleContent($section);
			echo '</h2></div>';
		}

		$description = $section->getDescription($context);
		if ( !empty($description) ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML intentionally allowed.
			echo "\n", '<p>', $description, '</p>';
		}

		echo '<div class="ame-form-box-content">';
		$this->renderSectionChildren($section, $context);
		echo "</div>\n";

		echo '</div>';
	}

	/**
	 * @param ControlGroup $group
	 * @param EvaluationContext $context
	 * @return void
	 */
	protected function renderControlGroup(ControlGroup $group, EvaluationContext $context) {
		$id = $group->getHtmlIdBase($context);

		$groupClasses = array_merge(['ame-form-box-group'], $group->getClasses());
		echo HtmlHelper::tag(
			'div',
			[
				'class' => $groupClasses,
				'id'    => !empty($id) ? $id : null,
			]
		);

		if ( !$this->fullWidthGroupsEnabled ) {
			echo '<div class="ame-form-box-group-title">';
			$this->renderGroupTitleContent($group);
			echo '</div>';
		}

		$contentClasses = ['ame-form-box-group-content'];
		if ( $this->fullWidthGroupsEnabled ) {
			$contentClasses[] = 'ame-form-box-full-width-content';
		}

		echo HtmlHelper::tag('div', ['class' => $contentClasses]);
		$this->renderGroupChildren($group, $context);
		echo '</div>';

		echo '</div>';
	}

	public function enqueueDependencies(string $containerSelector = '') {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		parent::enqueueDependencies($containerSelector);

		wp_enqueue_style(
			'ame-boxy-form-renderer',
			plugins_url('assets/form-box.css', AME_CUSTOMIZABLE_BASE_FILE),
			array(),
			'20220609-2'
		);
	}
}