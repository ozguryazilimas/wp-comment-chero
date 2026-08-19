<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\SettingCondition;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvers\Resolution;

trait Toggleable {
	/**
	 * @var callable
	 */
	protected $enabled = '__return_true';

	protected function parseEnabledParam($params) {
		if ( array_key_exists('enabled', $params) ) {
			if (
				is_bool($params['enabled'])
				|| is_numeric($params['enabled'])
				|| ($params['enabled'] === null)
			) {
				$this->enabled = $params['enabled'] ? '__return_true' : '__return_false';
			} else {
				$this->enabled = $params['enabled'];
			}
		} else if ( !empty($this->mainBinding) ) {
			$this->enabled = function (?EvaluationContext $context = null) {
				if ( $context ) {
					return $context->resolve($this->mainBinding)
						->map(fn(Resolution $r) => $r->isEditableByUser($context))
						->getOrElse(true);
				} else if ( $this->mainBinding instanceof AbstractSetting ) {
					return $this->mainBinding->isEditableByUser();
				}
				return true;
			};
		}
	}

	/**
	 * @param EvaluationContext|null $context
	 * @return bool
	 */
	public function isEnabled(?EvaluationContext $context = null) {
		return call_user_func($this->enabled, $context);
	}

	protected function getKoEnableBinding() {
		if ( $this->enabled instanceof SettingCondition ) {
			return ['enable' => $this->enabled->getJsKoExpression()];
		}
		return $this->isEnabled() ? [] : ['enable' => false];
	}

	protected function serializeConditionForJs() {
		if ( $this->enabled instanceof SettingCondition ) {
			return $this->enabled->serializeForJs();
		}
		return $this->isEnabled();
	}
}