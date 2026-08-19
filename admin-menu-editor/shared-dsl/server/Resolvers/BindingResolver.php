<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL\Resolvers;

use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Schema;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\SettingContainer;
use YahnisElsts\AdminMenuEditor\WireDSL\BindingRef;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvable;

/**
 * @implements ResolutionStrategy<BindingRef>
 */
class BindingResolver implements ResolutionStrategy {
	const CURRENT_ITEM_KEY = '$item';

	/**
	 * @param BindingRef $r
	 * @param EvaluationContext $context
	 * @param mixed $customDefault
	 * @return Resolution
	 */
	public function resolve(Resolvable $r, EvaluationContext $context, $customDefault = null): Resolution {
		$path = explode('.', $r->getBindingString());
		if ( empty($path) ) {
			return new Resolution(null, null, null, [], true);
		}

		$step = new Resolution($context->getRootSettingContainer());
		foreach ($path as $segment) {
			$step = $this->advance($step, $segment, $context);
		}

		return $step;
	}

	protected function advance(Resolution $step, string $segment, EvaluationContext $context): Resolution {
		if ( $segment === self::CURRENT_ITEM_KEY ) {
			$currentItem = $context->getCurrentItem();
			if ( $currentItem ) {
				return $currentItem;
			} else {
				return $step->fail($segment);
			}
		}

		$current = $step->getItem();
		if ( $current instanceof SettingContainer ) {
			$setting = $current->findDirectChildSetting($segment);
			if ( $setting ) {
				return $step->enterSetting($setting);
			} else {
				return $step->fail($segment);
			}
		} elseif ( is_array($current) && array_key_exists($segment, $current) ) {
			$nextItem = $current[$segment];
			return $step->descend($segment, $nextItem, $this->getChildSchema($step, $segment));
		} elseif ( is_object($current) && property_exists($current, $segment) ) {
			$nextItem = $current->$segment;
			return $step->descend($segment, $nextItem, $this->getChildSchema($step, $segment));
		} else {
			//Could not resolve the segment.
			//However, for collection/struct schemas, we could do a partial resolution to get
			//the item schema and default value.
			$nextSchema = $this->getChildSchema($step, $segment);
			if ( $nextSchema ) {
				$defaultValue = $nextSchema->hasDefaultValue() ? $nextSchema->getDefaultValue() : null;
				return $step->fail($segment, $defaultValue, $nextSchema);
			}
			return $step->fail($segment);
		}
	}

	protected function getChildSchema(Resolution $step, $childKey): ?Schema {
		$valueSchema = $step->getValueSchema();
		if ( $valueSchema ) {
			return $valueSchema->getChildSchema($childKey);
		}
		return null;
	}
}