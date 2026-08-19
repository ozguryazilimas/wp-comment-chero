<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

abstract class Container extends UiElement implements \IteratorAggregate, ControlContainer {
	/**
	 * @var string
	 */
	protected $title = '';

	/**
	 * @var string[] List of CSS classes to apply to the children container element (for containers that have one).
	 */
	protected $childrenContainerClasses = [];

	public function __construct($title, $params = [], $children = []) {
		parent::__construct($params, $children);
		$this->title = $title;

		if ( !empty($params['childrenContainerClasses']) ) {
			$this->childrenContainerClasses = (array)$params['childrenContainerClasses'];
		}
	}

	/**
	 * @return UiElement[]
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * Sort the container's children using the specified comparison function.
	 *
	 * @param callable(UiElement,UiElement):int $compareFunction
	 * @return void
	 */
	public function sortChildren($compareFunction) {
		usort($this->children, $compareFunction);
	}

	/**
	 * Recursively filter the container's children using the specified callback.
	 *
	 * The children list is replaced with the filtered list.
	 *
	 * @param callable(UiElement):bool $callback
	 * @return void
	 */
	public function recursiveFilterChildrenInPlace($callback) {
		foreach ($this->children as $key => $child) {
			//Depth-first traversal.
			if ( $child instanceof Container ) {
				$child->recursiveFilterChildrenInPlace($callback);
			}
			if ( $callback($child) === false ) {
				unset($this->children[$key]);
			}
		}

		//Re-index the array. Note: array_filter() would not help much since it does not re-index the array.
		$this->children = array_values($this->children);
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param string $newTitle
	 */
	public function setTitle($newTitle) {
		$this->title = $newTitle;
	}

	public function hasTitle() {
		return !empty($this->title);
	}

	/**
	 * Recursively search the container for a UI element that has the specified ID.
	 *
	 * @param string $id
	 * @return UiElement|null
	 */
	public function findChildById($id) {
		foreach ($this->children as $child) {
			if ( $child->getId() === $id ) {
				return $child;
			} else if ( $child instanceof Container ) {
				$result = $child->findChildById($id);
				if ( $result !== null ) {
					return $result;
				}
			}
		}
		return null;
	}

	public function isEmpty() {
		return empty($this->children);
	}

	public function hasChildren() {
		return !empty($this->children);
	}

	public function serializeForJs(EvaluationContext $context): array {
		$result = parent::serializeForJs($context);
		if ( $this->hasTitle() ) {
			$result['title'] = $this->title;
		}

		if ( !empty($this->childrenContainerClasses) ) {
			$result['childrenContainerClasses'] = $this->childrenContainerClasses;
		}

		return $result;
	}

	public function serializeForRevisedJs(EvaluationContext $context): array {
		$result = parent::serializeForRevisedJs($context);

		if ( $this->hasTitle() ) {
			$result['title'] = $this->title;
		}
		if ( !empty($this->childrenContainerClasses) ) {
			$result['childrenContainerClasses'] = $this->childrenContainerClasses;
		}

		return $result;
	}

	protected function getRevisedJsUiElementType(): string {
		return 'container';
	}

	public function enqueueKoComponentDependencies() {
		parent::enqueueKoComponentDependencies();
		foreach ($this->children as $child) {
			$child->enqueueKoComponentDependencies();
		}
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator($this->children);
	}

	/**
	 * Recursively get all descendants of this container.
	 *
	 * @return \Generator
	 */
	public function getAllDescendants() {
		foreach ($this->children as $child) {
			yield $child;
			if ( $child instanceof ControlContainer ) {
				yield from $child->getAllDescendants();
			}
		}
	}

	public function getAllReferencedSettings(EvaluationContext $context) {
		foreach ($this->getChildren() as $child) {
			yield from $child->getAllReferencedSettings($context);
		}
	}
}