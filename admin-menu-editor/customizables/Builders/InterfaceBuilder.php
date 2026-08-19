<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Builders;

use YahnisElsts\AdminMenuEditor\Customizable\Controls;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Container;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\InterfaceStructure;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement;

class InterfaceBuilder {
	protected $children = array();

	/**
	 * @param ElementBuilder|UiElement $container
	 * @return $this
	 */
	public function add($container) {
		$this->children[] = $container;
		return $this;
	}

	/**
	 * @param Container|ContainerBuilder $container
	 * @return $this
	 */
	public function prepend($container): InterfaceBuilder {
		array_unshift($this->children, $container);
		return $this;
	}

	/**
	 * Add a container before the first direct descendant that has the specified ID.
	 *
	 * If there is no child with that ID, the container will be added to the beginning
	 * of the list.
	 *
	 * @param Container|ContainerBuilder $container
	 * @param string $beforeId
	 * @return $this
	 */
	public function addBefore($container, $beforeId) {
		$index = $this->findChildIndex($beforeId);
		if ( $index === false ) {
			array_unshift($this->children, $container);
		} else {
			array_splice($this->children, $index, 0, [$container]);
		}
		return $this;
	}

	/**
	 * @param Container|ContainerBuilder $container
	 * @param string $afterId
	 * @return $this
	 */
	public function addAfter($container, $afterId) {
		$index = $this->findChildIndex($afterId);
		if ( $index === false ) {
			$this->children[] = $container;
		} else {
			array_splice($this->children, $index + 1, 0, [$container]);
		}
		return $this;
	}

	protected function findChildIndex($id) {
		foreach ($this->children as $index => $child) {
			if ( $child instanceof Controls\UiElement ) {
				$childId = $child->getId(null);
			} else if ( $child instanceof BaseElementBuilder ) {
				$childId = $child->getCustomId();
			} else {
				$childId = null;
			}

			if ( $childId === $id ) {
				return $index;
			}
		}
		return null;
	}

	/**
	 * @return InterfaceStructure
	 */
	public function build() {
		return new InterfaceStructure('', [], $this->buildChildren());
	}

	protected function buildChildren() {
		$children = array();
		foreach ($this->children as $child) {
			if ( $child instanceof ElementBuilder ) {
				$children[] = $child->build();
			} else {
				$children[] = $child;
			}
		}
		return $children;
	}
}