<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Controls;

use YahnisElsts\AdminMenuEditor\Customizable\HtmlHelper;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvers\Resolution;
use YahnisElsts\AdminMenuEditor\WireDSL\SettingRef;

abstract class UiElement {
	/**
	 * @var string
	 */
	protected $id = '';

	/**
	 * @var string|callable|null
	 */
	protected $description = null;

	/**
	 * @var array List of CSS classes to apply to the outermost DOM node of the element.
	 * This property might not be meaningful for elements that output multiple nodes without
	 * a common parent or that don't have a visible representation.
	 */
	protected $classes = array();

	/**
	 * @var array List of CSS styles to apply to the outermost DOM node of the element.
	 */
	protected $styles = array();

	/**
	 * @var UiElement[]
	 */
	protected $children = [];

	/**
	 * @var array<string, UiElement[]> Named slots for child elements. The keys are slot names,
	 *                                 and the values are arrays of UiElement instances.
	 */
	protected array $slots = [];

	protected $renderCondition = true;

	/**
	 * Lets the renderer know that the element doesn't want new line breaks added
	 * before and after its content.
	 *
	 * - Block elements (e.g. &lt;fieldset&gt;) and elements that surround their
	 * content with &lt;p&gt; or &lt;br&gt; tags should set this to true.
	 * - Elements that output partial or unclosed tags should also set this to
	 * true to avoid producing invalid HTML.
	 *
	 * @var bool
	 */
	protected $declinesExternalLineBreaks = false;

	/**
	 * @var null|Tooltip
	 */
	protected $tooltip = null;

	public function __construct($params = [], $children = []) {
		if ( !empty($params['id']) ) {
			$this->id = $params['id'];
		}
		if ( !empty($params['description']) ) {
			$this->description = $params['description'];
		}
		if ( !empty($params['classes']) ) {
			$this->classes = (array)$params['classes'];
		}
		if ( !empty($params['styles']) ) {
			$this->styles = (array)$params['styles'];
		}
		if ( isset($params['renderCondition']) ) {
			$this->renderCondition = $params['renderCondition'];
		}
		if ( isset($params['tooltip']) ) {
			$this->tooltip = $params['tooltip'];
		}

		foreach ($children as $child) {
			$this->add($child);
		}

		if ( isset($params['slots']) && is_array($params['slots']) ) {
			foreach ($params['slots'] as $slotName => $slotChildren) {
				if ( is_array($slotChildren) ) {
					foreach ($slotChildren as $child) {
						$this->addToSlot($slotName, $child);
					}
				}
			}
		}
	}

	/**
	 * @param UiElement $child
	 * @return void
	 */
	public function add(UiElement $child) {
		$this->children[] = $child;
	}

	/**
	 * @param string $slotName
	 * @param UiElement $child
	 * @return void
	 */
	public function addToSlot(string $slotName, UiElement $child) {
		if ( !isset($this->slots[$slotName]) ) {
			$this->slots[$slotName] = [];
		}
		$this->slots[$slotName][] = $child;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	public function getHtmlIdBase(?EvaluationContext $context = null) {
		return $this->getId();
	}

	/**
	 * @return string
	 */
	public function getDescription(?EvaluationContext $context = null) {
		if ( is_string($this->description) ) {
			return $this->description;
		} elseif ( is_callable($this->description) ) {
			return call_user_func($this->description);
		} elseif ( isset($this->description) ) {
			return strval($this->description);
		} else if ( isset($this->mainBinding) && ($this->mainBinding instanceof Binding) && ($context !== null) ) {
			return $context->resolve($this->mainBinding)
				->map(fn(Resolution $r) => strval($r->getDescription($context)))
				->getOrElse('[Unresolved reference (element description)]');
		}
		return '';
	}

	/**
	 * @return array
	 */
	public function getClasses() {
		return $this->classes;
	}

	/**
	 * @return bool
	 */
	public function hasTooltip() {
		return ($this->tooltip !== null);
	}

	/**
	 * @return Tooltip|null
	 */
	public function getTooltip() {
		return $this->tooltip;
	}

	protected function buildTag($tagName, $attributes = array(), $content = null) {
		return HtmlHelper::tag($tagName, $attributes, $content);
	}

	/**
	 * @return bool
	 */
	public function declinesExternalLineBreaks() {
		return $this->declinesExternalLineBreaks;
	}

	public function shouldRender() {
		if ( is_callable($this->renderCondition) ) {
			return call_user_func($this->renderCondition);
		}
		return (bool)$this->renderCondition;
	}

	public function serializeForJs(EvaluationContext $context): array {
		$result = ['t' => $this->getJsUiElementType()];

		if ( !empty($this->id) ) {
			$result['id'] = $this->id;
		}

		$params = $this->getKoComponentParams($context);
		if ( !empty($params) ) {
			$result['params'] = $params;
		}

		if ( !empty($this->children) ) {
			$result['children'] = [];
			foreach ($this->children as $child) {
				$result['children'][] = $child->serializeForJs($context);
			}
		}

		return $result;
	}

	public function serializeForRevisedJs(EvaluationContext $context): array {
		$result = ['t' => $this->getRevisedJsUiElementType()];

		if ( !empty($this->id) ) {
			$result['id'] = $this->id;
		}

		$result = array_merge($this->getKoComponentParams($context), $result);

		if ( !empty($this->children) || !empty($this->slots) ) {
			$childContext = $this->getSerializationContextForChildren($context);

			$serializedSlots = [];
			if ( !empty($this->slots) ) {
				foreach ($this->slots as $slotName => $slotChildren) {
					$serializedSlots[$slotName] = [];
					foreach ($slotChildren as $child) {
						$serializedSlots[$slotName][] = $child->serializeForRevisedJs($childContext);
					}
				}
			}

			if ( !empty($this->children) ) {
				$serializedSlots['default'] = [];
				foreach ($this->children as $child) {
					$serializedSlots['default'][] = $child->serializeForRevisedJs($childContext);
				}
			}

			if ( !empty($serializedSlots) ) {
				$result['slots'] = $serializedSlots;
			}
		}

		return $result;
	}

	protected function getSerializationContextForChildren(EvaluationContext $context): EvaluationContext {
		return $context;
	}

	abstract protected function getJsUiElementType();

	abstract protected function getRevisedJsUiElementType(): string;

	protected function serializeDescriptionForJs(EvaluationContext $context) {
		if ( isset($this->description) ) {
			return self::serializeValueForJs($this->description, $context);
		}

		if ( isset($this->mainBinding) && ($this->mainBinding instanceof Binding) ) {
			return $context->partialResolve($this->mainBinding)
				->map(fn(Resolution $r) => strval($r->getDescription($context)))
				->getOrElse('[Unresolved reference "' . $this->mainBinding->getInternalStringId() . '"]');
		}

		return '';
	}

	protected static function serializeMinimalBindingForJs(Binding $binding, EvaluationContext $context) {
		if ( $binding instanceof AbstractSetting ) {
			return $binding->getId();
		} else if ( $binding instanceof SettingRef ) {
			return $binding->getSetting()->getId();
		} else if ( $binding instanceof \JsonSerializable ) {
			//Optionally, we could try to resolve the binding and serialize the resolved entity,
			//if it's a settings and there's no path in the resolution.
			return (object)$binding->jsonSerialize();
		}
		throw new \InvalidArgumentException('Cannot serialize binding for JS: ' . get_class($binding) . '.');
	}

	protected static function serializeValueForJs($value, EvaluationContext $context) {
		if ( is_scalar($value) ) {
			return $value;
		} elseif ( $value instanceof \JsonSerializable ) {
			return $value->jsonSerialize();
		} elseif ( is_callable($value) ) {
			return call_user_func($value);
		} elseif ( isset($value) ) {
			return $value;
		}
		return null;
	}


	/**
	 * Recursively get all settings referenced by this element and its descendants.
	 *
	 * @param EvaluationContext $context Used to resolve bindings.
	 */
	public function getAllReferencedSettings(EvaluationContext $context) {
		return [];
	}

	protected static function getSettingsReferencedByBinding(Binding $binding, EvaluationContext $context): \Generator {
		if ( $binding instanceof AbstractSetting ) {
			yield $binding->getId() => $binding;
		} else if ( $binding instanceof Binding ) {
			$option = $context->resolve($binding);
			if ( $option->isDefined() ) {
				$resolution = $option->get();
				$leafSetting = $resolution->getNearestSetting();
				if ( $leafSetting instanceof AbstractSetting ) {
					yield $leafSetting->getId() => $leafSetting;
				}
			}
		}
	}

	/**
	 * Get additional parameters for the Knockout component that renders this element.
	 *
	 * @param EvaluationContext $context
	 * @return array
	 */
	protected function getKoComponentParams(EvaluationContext $context): array {
		$params = [];
		if ( !empty($this->classes) ) {
			$params['classes'] = $this->classes;
		}
		if ( !empty($this->styles) ) {
			$params['styles'] = $this->styles;
		}

		$serializedDescription = $this->serializeDescriptionForJs($context);
		if ( !empty($serializedDescription) ) {
			$params['description'] = $serializedDescription;
		}

		if ( $this->hasTooltip() ) {
			$params['tooltip'] = $this->tooltip->serializeForJs();
		}
		return $params;
	}

	public function enqueueKoComponentDependencies() {
		//Do nothing by default.
	}

	public function enqueueRevisedComponentDependencies() {
		$this->enqueueKoComponentDependencies(); //Maybe some of these can be disentangled from the revised UI later.
		static::enqueueRevisedStyles();
	}

	public static function enqueueRevisedStyles() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$componentsStyleFileName = 'components.css';
		$componentStylePath = AME_ROOT_DIR . '/customizables/revised-ui/styles/' . $componentsStyleFileName;

		wp_enqueue_style(
			'ame-revised-ui-components',
			plugins_url($componentsStyleFileName, $componentStylePath),
			[],
			filemtime($componentStylePath)
		);
	}
}