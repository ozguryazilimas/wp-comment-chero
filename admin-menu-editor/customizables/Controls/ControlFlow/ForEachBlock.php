<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls\ControlFlow;

use YahnisElsts\AdminMenuEditor\Customizable\Builders\ElementBuilder;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\StaticHtml;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\UiElement;
use YahnisElsts\AdminMenuEditor\Customizable\Rendering\Renderer;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Collection;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvers\Resolution;

class ForEachBlock extends ControlFlowBlock {
	/**
	 * @var Binding
	 */
	private $items;
	/**
	 * @var array<UiElement|ElementBuilder>
	 */
	private $itemTemplateChildren;

	public function __construct(Binding $items, $itemTemplateChildren = [], $params = array(), $children = []) {
		parent::__construct($params, $children);
		$this->items = $items;
		$this->itemTemplateChildren = $itemTemplateChildren;
	}

	protected function getJsUiElementType(): string {
		return 'foreach';
	}

	protected function getRevisedJsUiElementType(): string {
		return $this->getJsUiElementType();
	}

	public function renderContent(Renderer $renderer, EvaluationContext $context) {
		$optionResult = $context->resolve($this->items);
		if ( $optionResult->isEmpty() ) {
			$renderer->renderElement(
				new StaticHtml(sprintf(
					'<p><em>ForEachBlock: could not resolve "%s".</em></p>',
					esc_html($this->items->getInternalStringId())
				)),
				$context,
				$this
			);
			return;
		}

		$resolved = $optionResult->get();
		$itemList = $resolved->getValue();
		if ( $itemList === null ) {
			$itemList = [];
		}

		if ( !is_array($itemList) ) {
			$renderer->renderElement(
				new StaticHtml(sprintf(
					'<p><em>ForEachBlock: "%s" did not resolve to an array.</em></p>',
					esc_html($this->items->getInternalStringId())
				)),
				$context,
				$this
			);
			return;
		}

		$itemSchema = null;
		$listSchema = $resolved->getValueSchema();
		if ( $listSchema instanceof Collection ) {
			$itemSchema = $listSchema->getItemSchema();
		}

		$templateElements = $this->getBuiltTemplateElements();
		$innerContext = $context->createChildContext();
		foreach ($itemList as $key => $item) {
			$path = $resolved->getPathInSetting();
			$path[] = $key;

			$innerContext->setCurrentItem(new Resolution(
				$item,
				$itemSchema,
				$resolved->getNearestSetting(),
				$path
			));

			$renderer->renderItems($templateElements, $innerContext, $this);
		}
	}

	protected bool $isTemplateBuilt = false;

	protected function getBuiltTemplateElements(): array {
		//Build the template children only once.
		if ( !$this->isTemplateBuilt ) {
			$elements = [];
			foreach ($this->itemTemplateChildren as $child) {
				if ( $child instanceof ElementBuilder ) {
					$elements[] = $child->build();
				} elseif ( $child instanceof UiElement ) {
					$elements[] = $child;
				} else {
					$typeString = is_object($child) ? get_class($child) : gettype($child);
					throw new \InvalidArgumentException(
						'Invalid item type for ForEachBlock template: ' . $typeString
					);
				}
			}
			$this->itemTemplateChildren = $elements;
			$this->isTemplateBuilt = true;
		}

		return $this->itemTemplateChildren;
	}

	public function serializeForJs(EvaluationContext $context): array {
		$result = parent::serializeForJs($context);

		$result['items'] = self::serializeMinimalBindingForJs($this->items, $context);

		$templateElements = $this->getBuiltTemplateElements();
		if ( !empty($templateElements) ) {
			$result['children'] = [];
			foreach ($templateElements as $child) {
				//Commented out because the JS side doesn't support references yet.
				//$result['children'][] = $child->serializeForJs($context);
			}
		}

		return $result;
	}

	public function serializeForRevisedJs(EvaluationContext $context): array {
		$result = parent::serializeForRevisedJs($context);

		$result['items'] = self::serializeMinimalBindingForJs($this->items, $context);

		$templateElements = $this->getBuiltTemplateElements();
		if ( !empty($templateElements) ) {
			$innerContext = $context->createChildContext();

			//Try to resolve the items binding to get the schema of the items.
			//The children can use this schema to set up control properties like labels, descriptions,
			//ranges for number inputs, etc.
			$resolvedOption = $context->partialResolve($this->items);
			if ( $resolvedOption->isDefined() ) {
				$resolved = $resolvedOption->get();
				$itemSchema = null;
				$listSchema = $resolved->getValueSchema();
				if ( $listSchema instanceof Collection ) {
					$itemSchema = $listSchema->getItemSchema();
				}
				$innerContext->setCurrentItem(new Resolution(
					null,
					$itemSchema,
					$resolved->getNearestSetting(),
					$resolved->getPathInSetting()
				));
			}

			$serializedElements = [];
			foreach ($templateElements as $child) {
				$serializedElements[] = $child->serializeForRevisedJs($innerContext);
			}
			$result['slots'] = ['default' => $serializedElements];
		}

		return $result;
	}


	public function getAllReferencedSettings(EvaluationContext $context) {
		yield from self::getSettingsReferencedByBinding($this->items, $context);
	}
}