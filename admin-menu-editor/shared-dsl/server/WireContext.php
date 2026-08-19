<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

use YahnisElsts\AdminMenuEditor\Customizable\CombinedSettingLookup;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\SettingContainer;
use YahnisElsts\AdminMenuEditor\Options\Option;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvers\Resolution;
use YahnisElsts\AdminMenuEditor\WireDSL\Resolvers\ResolutionStrategy;

class WireContext implements EvaluationContext {
	protected static int $contextCounter = 0;

	/**
	 * @var string Internal unique ID for this context instance. Only for debugging.
	 */
	protected string $contextId;
	/**
	 * @var int Incremented each time the context is modified, to invalidate caches.
	 */
	protected int $version = 1;

	/**
	 * @var array<string,ResolutionStrategy>
	 */
	protected array $resolvers = [];

	protected SettingContainer $rootSettingContainer;
	protected ?Resolution $currentItem;
	protected ?EvaluationContext $parent;
	protected array $attributes = [];

	public function __construct(
		$rootSettingContainers = [],
		?Resolution $currentItem = null,
		?EvaluationContext $parent = null,
		array $attributes = [],
		string $idPrefix = ''
	) {
		++self::$contextCounter;
		$this->contextId = ($idPrefix ?: 'context') . ' {' . self::$contextCounter . '}';

		$this->currentItem = $currentItem;
		$this->parent = $parent;
		$this->attributes = $attributes;

		if ( is_array($rootSettingContainers) ) {
			if ( empty($rootSettingContainers) && $parent ) {
				//If no root setting containers are provided, inherit from parent context.
				$this->rootSettingContainer = $parent->getRootSettingContainer();
			} else {
				$this->rootSettingContainer = new CombinedSettingLookup($rootSettingContainers);
			}
		} elseif ( $rootSettingContainers instanceof SettingContainer ) {
			$this->rootSettingContainer = $rootSettingContainers;
		} else {
			throw new \InvalidArgumentException('Invalid root setting container(s) provided.');
		}

		if ( $parent ) {
			//Inherit resolvers from parent context.
			$this->resolvers = $parent->getResolvers();
		} else {
			//Register default resolvers.
			$this->resolvers = [
				AbstractSetting::RESOLUTION_KEY => new Resolvers\SettingResolver(),
				BindingRef::RESOLUTION_KEY      => new Resolvers\BindingResolver(),
				SettingRef::RESOLUTION_KEY      => new Resolvers\SettingRefResolver(),
			];
		}
	}

	function resolveValue(Resolvable $resolvable, $customDefault = null) {
		$resolved = $this->resolve($resolvable, $customDefault);
		if ( $resolved->isDefined() ) {
			return $resolved->get()->getValue();
		} else {
			return $customDefault;
		}
	}

	/**
	 * @inheritDoc
	 */
	function resolve(Resolvable $resolvable, $customDefault = null): Option {
		$resolution = $this->getResolver($resolvable)->resolve($resolvable, $this, $customDefault);
		return $resolution->toOption();
	}

	function partialResolve(Resolvable $resolvable, $customDefault = null): Option {
		$resolution = $this->getResolver($resolvable)->resolve($resolvable, $this, $customDefault);
		if ( $resolution->isEmptyFailure() ) {
			return Option::none();
		} else {
			return Option::some($resolution);
		}
	}

	protected function getResolver(Resolvable $resolvable): ResolutionStrategy {
		$key = $resolvable->getResolutionStrategyKey();
		if ( isset($this->resolvers[$key]) ) {
			return $this->resolvers[$key];
		} else {
			throw new \RuntimeException("No resolver registered for strategy key: $key");
		}
	}


	function getRootSettingContainer(): SettingContainer {
		return $this->rootSettingContainer;
	}

	function getId(): string {
		return $this->contextId;
	}

	function getVersion(): int {
		return $this->version;
	}

	function getResolvers(): array {
		return $this->resolvers;
	}

	function setCurrentItem(Resolution $item): void {
		$this->currentItem = $item;
		$this->version++;
	}

	function getCurrentItem(): ?Resolution {
		return $this->currentItem;
	}

	function createChildContext(): EvaluationContext {
		return new WireContext(
			$this->rootSettingContainer,
			$this->currentItem,
			$this, [],
			$this->contextId . ' > '
		);
	}

	function withAttributes(array $attributes): EvaluationContext {
		return new WireContext(
			$this->rootSettingContainer,
			$this->currentItem,
			$this,
			array_merge($this->attributes, $attributes),
			$this->contextId . ' > '
		);
	}

	function getAttribute(string $key, $default = null) {
		return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
	}

	function encodeValueForForm(Resolvable $resolvable, $value): string {
		//Shortcut for the most common case.
		if ( $resolvable instanceof AbstractSetting ) {
			return $resolvable->encodeForForm($value);
		}

		$resolution = $this->resolve($resolvable);
		if ( $resolution->isDefined() ) {
			$resolved = $resolution->get();
			if ( $resolved->isLeafSetting() ) {
				return $resolved->getNearestSetting()->encodeForForm($value);
			}

			$schema = $resolved->getValueSchema();
			if ( $schema ) {
				return $schema->encodeValueForForm($value);
			}
		}
		return (string)$value;
	}
}