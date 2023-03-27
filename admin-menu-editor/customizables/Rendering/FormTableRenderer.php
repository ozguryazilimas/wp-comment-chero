<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Rendering;

class FormTableRenderer extends ClassicRenderer {
	protected $isInsideRow = false;

	protected $sectionNestingLevel = 0;

	public function renderSection($section) {
		if ( !$section->shouldRender() ) {
			//Should this even be allowed?
			echo sprintf(
				'<!-- Notice: Rendering a section that does not think it should be rendered: %s (%s) -->',
				esc_html($section->getTitle()),
				esc_html($section->getId())
			);
		}

		echo '<div>';
		$this->renderSectionHeading($section);

		$description = $section->getDescription();
		if ( !empty($description) ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML intentionally allowed.
			echo "\n", '<p>', $description, '</p>';
		}

		//Render each section as a table.
		echo '<table class="form-table ame-rendered-form-table">';
		$this->renderSectionChildren($section);
		echo '</table>';
		echo '</div>';
	}

	protected function renderSectionHeading($section, $headingLevel = 2) {
		$title = $section->getTitle();
		if ( !empty($title) ) {
			echo sprintf('<h%d>', (int)$headingLevel);
			$this->renderSectionTitleContent($section);
			echo sprintf('</h%d>', (int)$headingLevel);
		}
	}

	protected function renderChildSection($section) {
		$this->sectionNestingLevel++;

		if ( $section->hasTitle() ) {
			$headingLevel = min(6, 2 + $this->sectionNestingLevel);
			echo '<tr class="ame-nested-section-header"><td colspan="2">';
			$this->renderSectionHeading($section, $headingLevel);
			echo '</td></tr>';
		}

		$this->renderSectionChildren($section);
		$this->sectionNestingLevel--;
	}

	protected function renderControlGroup($group) {
		$isStacked = $group->isStacked();

		/*
		 * - Render top-level groups as table rows. Optionally, their contents
		 *   can be wrapped in a fieldset element.
		 * - Render nested groups as either fieldset elements or a flat sequence
		 *   of controls.
		 */
		$isFieldset = $group->wantsFieldset();
		if ( $isFieldset === null ) {
			$isFieldset = $this->isInsideRow;
		}
		$renderAsTableRow = !$this->isInsideRow;

		$wasInsideRow = $this->isInsideRow;
		if ( $renderAsTableRow ) {
			$this->isInsideRow = true;

			printf('<tr id="%s">', esc_attr($group->getId()));
			printf(
				'<th scope="row" class="%s">',
				esc_attr($group->hasTooltip() ? 'ame-customizable-cg-has-tooltip' : '')
			);

			$this->renderGroupTitleContent($group);

			echo '</th>';
			echo '<td>';
		}

		if ( $isFieldset ) {
			/** @noinspection HtmlUnknownAttribute */
			printf('<fieldset %s>', $group->isEnabled() ? '' : 'disabled');
		}

		$this->renderGroupChildren($group, $isStacked);

		if ( $isFieldset ) {
			echo '</fieldset>';
		}
		if ( $renderAsTableRow ) {
			echo '</td>';
			echo '</tr>';
			$this->isInsideRow = $wasInsideRow;
		}
	}

	public function renderControl($control, $parentContext = null) {
		if ( !$control->shouldRender() ) {
			//This should really never happen.
			echo '<!-- Skipped control: ' . esc_html($control->getId()) . ' -->';
			return;
		}

		$addLineBreaks = ($parentContext && !$control->declinesExternalLineBreaks());
		if ( $addLineBreaks ) {
			echo '<p>';
		}

		parent::renderControl($control, $parentContext);

		if ( $addLineBreaks ) {
			echo '</p>';
		}
	}

	public function enqueueDependencies($containerSelector = '') {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		parent::enqueueDependencies($containerSelector);

		wp_enqueue_style(
			'ame-form-table-renderer',
			plugins_url('assets/form-table.css', AME_CUSTOMIZABLE_BASE_FILE),
			array(),
			'20221129'
		);
	}
}