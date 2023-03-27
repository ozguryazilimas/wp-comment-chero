<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

/**
 * A group of closely related controls.
 *
 * Often has only a single control for which it serves as a named wrapper.
 * This is particularly useful when controls are rendered in a two-column layout
 * like "title | control", or in a vertical layout that shows a title above each
 * control.
 */
class ControlGroup extends Container {
	use Toggleable;

	/**
	 * @var string|null|false The ID of the HTML input element that should
	 * be focused when the user clicks the title of this group.
	 */
	protected $labelFor = null;

	/**
	 * @var bool Indicates if the control group wants its children to be rendered
	 *           in a vertical layout, stacked on top of each other. It's up to
	 *           the renderer to decide how to implement that.
	 */
	protected $isStacked = false;

	/**
	 * @var bool|null Whether the control group wants its children to be wrapped in
	 *           a fieldset element. Set this to NULL to let the renderer decide.
	 */
	protected $wantsFieldset = null;

	protected $declinesExternalLineBreaks = true;

	/**
	 * @var bool Whether the group wants to cover the entire width of the row
	 *           when using two-column "label | content" layout.
	 */
	protected $isFullWidth = false;

	public function __construct($title, $children = [], $params = []) {
		parent::__construct($title, $children, $params);
		if ( isset($params['stacked']) ) {
			$this->isStacked = boolval($params['stacked']);
		}
		if ( array_key_exists('fieldset', $params) ) {
			$this->wantsFieldset = $params['fieldset'];
		}
		if ( isset($params['fullWidth']) ) {
			$this->isFullWidth = boolval($params['fullWidth']);
		}
		$this->parseEnabledParam($params);
	}

	public function add($child) {
		if ( $child instanceof Section ) {
			throw new \InvalidArgumentException('Control groups cannot contain sections.');
		}

		parent::add($child);
	}

	/**
	 * @return string|null
	 */
	public function getLabelFor() {
		if ( $this->labelFor === null ) {
			//Automatically choose a target for the label only if there is exactly
			//one control that wants a label, and it is the first child.
			$target = null;
			$childNumber = 0;

			foreach ($this->children as $child) {
				$childNumber++;
				if ( ($child instanceof Control) && $child->parentLabelEnabled() ) {
					if ( $childNumber === 1 ) {
						$target = $child;
					} else {
						//Either this is not the first child, or more than one
						//child wants a label.
						$target = null;
						break;
					}
				}
			}

			if ( $target instanceof Control ) {
				$this->labelFor = $target->getPrimaryInputId();
			} else {
				$this->labelFor = false;
			}
		}

		return $this->labelFor ?: null;
	}

	public function isStacked() {
		return $this->isStacked;
	}

	public function wantsFieldset() {
		return $this->wantsFieldset;
	}

	/**
	 * @return bool
	 */
	public function isFullWidth() {
		return $this->isFullWidth;
	}

	protected function getJsUiElementType() {
		return 'control-group';
	}

	protected function getKoComponentParams() {
		$params = parent::getKoComponentParams();
		$params['enabled'] = $this->serializeConditionForJs();

		$labelFor = $this->getLabelFor();
		if ( $labelFor !== null ) {
			$params['labelFor'] = $labelFor;
		}

		//The "full width" flag is not directly relevant to group components because they
		//are usually already full width, but it is sometimes used to disable the title
		//(e.g. for groups that contain only a single checkbox).
		if ($this->isFullWidth()) {
			$params['isFullWidth'] = true;
			$params['titleDisabled'] = true;
		}

		return $params;
	}
}