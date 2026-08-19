<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Builders;

use YahnisElsts\AdminMenuEditor\Customizable\Controls\Tooltip;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement;

/**
 * @template ElementClass of UiElement
 */
abstract class BaseElementBuilder implements ElementBuilder {
	/**
	 * @var array
	 */
	protected $params = array();

	/**
	 * @var class-string<ElementClass>
	 */
	protected $elementClass;

	/**
	 * @var array<ElementBuilder|UiElement|array>
	 */
	protected $children = array();

	/**
	 * @var array<string,array<ElementBuilder|UiElement>>
	 */
	protected array $slots = [];

	/**
	 * @param class-string<\YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement> $elementClass
	 * @param array $params
	 */
	protected function __construct($elementClass, $params = array(), $children = array()) {
		$this->elementClass = $elementClass;
		$this->params = $params;
		$this->children = $children;
	}

	protected static function buildItems($items, $preserveKeys = false) {
		$results = array();
		foreach ($items as $key => $item) {
			if ( is_array($item) ) {
				//Flatten nested arrays of buildable things.
				$results = array_merge($results, self::buildItems($item, $preserveKeys));
				continue;
			}

			if ( $item instanceof ElementBuilder ) {
				$item = $item->build();
			} elseif ( !($item instanceof UiElement) ) {
				$typeString = is_object($item) ? get_class($item) : gettype($item);
				throw new \InvalidArgumentException(
					'Invalid item type for an element builder: ' . $typeString
				);
			}

			if ( $preserveKeys ) {
				$results[$key] = $item;
			} else {
				$results[] = $item;
			}
		}
		return $results;
	}

	public function id($string) {
		$this->params['id'] = $string;
		return $this;
	}

	public function getCustomId() {
		return isset($this->params['id']) ? $this->params['id'] : null;
	}

	/**
	 * @param string|callable $textOrCallback
	 * @return $this
	 */
	public function description($textOrCallback) {
		$this->params['description'] = $textOrCallback;
		return $this;
	}

	public function tooltip($html, $type = Tooltip::DEFAULT_TYPE) {
		$this->params['tooltip'] = new Tooltip($html, $type);
		return $this;
	}

	public function classes(...$cssClassNames) {
		return $this->addItemsToArrayParam('classes', $cssClassNames);
	}

	/**
	 * Add CSS class names if their values are truthy.
	 *
	 * @param array<string,mixed> $classEnabled ['class-a' => true, 'class-b' => false, ...]
	 * @return $this
	 */
	public function conditionalClasses($classEnabled) {
		return $this->classes(...array_keys(array_filter($classEnabled)));
	}

	public function styles($propertyPairs) {
		return $this->addItemsToArrayParam('style', $propertyPairs);
	}

	/**
	 * @param string $paramName
	 * @param $items
	 * @return $this
	 */
	protected function addItemsToArrayParam($paramName, $items) {
		if ( !isset($this->params[$paramName]) ) {
			$this->params[$paramName] = array();
		}
		$this->params[$paramName] = array_merge($this->params[$paramName], (array)$items);
		return $this;
	}

	/**
	 * Set one or more parameters for the element.
	 *
	 * Will overwrite any existing parameters with the same name.
	 *
	 * @param array<string,mixed> $additionalParams
	 * @return $this
	 */
	public function params($additionalParams) {
		$this->params = array_merge($this->params, $additionalParams);
		return $this;
	}

	public function getParam($name, $default = null) {
		return $this->params[$name] ?? $default;
	}

	/**
	 * Render the element only if the condition evaluates to true.
	 *
	 * @param bool|callable $condition
	 */
	public function onlyIf($condition) {
		$this->params['renderCondition'] = $condition;
		return $this;
	}

	/**
	 * @param ElementBuilder|UiElement ...$children
	 * @return $this
	 */
	public function add(...$children) {
		return $this->addAll($children);
	}

	/**
	 * @param array<ElementBuilder|UiElement> $children
	 * @return $this
	 */
	public function addAll(array $children) {
		foreach ($children as $child) {
			$this->children[] = $child;
		}
		return $this;
	}

	/**
	 * @return UiElement[]
	 */
	protected function buildChildren(): array {
		return self::buildItems($this->children);
	}

	/**
	 * Set the children for a named slot. This will overwrite any existing children in that slot.
	 *
	 * @param string $slotName
	 * @param ...$children
	 * @return $this
	 */
	public function slot(string $slotName, ...$children): self {
		$this->slots[$slotName] = $children;
		return $this;
	}

	/**
	 * Add one or more children to a named slot. This will append to any existing children in that slot.
	 *
	 * @param string $slotName
	 * @param ElementBuilder|UiElement ...$children
	 * @return $this
	 */
	public function addToSlot(string $slotName, ...$children): self {
		if ( !isset($this->slots[$slotName]) ) {
			$this->slots[$slotName] = [];
		}
		$this->slots[$slotName] = array_merge($this->slots[$slotName], $children);
		return $this;
	}

	protected function buildSlots(): array {
		return array_map(function ($slotChildren) {
			return self::buildItems($slotChildren);
		}, $this->slots);
	}

	protected function buildParams(): array {
		$params = $this->params;

		$slots = $this->buildSlots();
		if ( !empty($slots) ) {
			$params['slots'] = $slots;
		}

		return $params;
	}

	/**
	 * @return ElementClass
	 */
	abstract public function build();
}