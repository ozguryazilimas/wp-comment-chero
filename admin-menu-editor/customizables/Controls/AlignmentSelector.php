<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class AlignmentSelector extends RadioButtonBar {
	protected $type = 'alignment-selector';

	public function __construct($settings = array(), $params = array(), $children = []) {
		parent::__construct($settings, $params, $children);
	}

	protected function initOptions(EvaluationContext $context): array {
		$options = parent::initOptions($context);

		//Set default choice labels and icons for recognized values.
		$choices = array(
			'none'   => array(
				'description' => 'None',
				'icon'        => 'dashicons-editor-justify',
			),
			'left'   => array(
				'description' => 'Align left',
				'icon'        => 'dashicons-editor-alignleft',
			),
			'center' => array(
				'description' => 'Align center',
				'icon'        => 'dashicons-editor-aligncenter',
			),
			'right'  => array(
				'description' => 'Align right',
				'icon'        => 'dashicons-editor-alignright',
			),
		);

		foreach ($options as $option) {
			if ( isset($choices[$option->value]) ) {
				//No label, just an icon and a description in a tooltip.
				$option->label = '';
				$option->description = $choices[$option->value]['description'];
				$option->icon = $choices[$option->value]['icon'];
			}
		}

		return $options;
	}


}