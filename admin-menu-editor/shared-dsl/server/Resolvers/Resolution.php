<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL\Resolvers;

use YahnisElsts\AdminMenuEditor\Customizable\Describable;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\Schema;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\WithSchema\SettingWithSchema;
use YahnisElsts\AdminMenuEditor\Options\Option;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

class Resolution {
	/**
	 * @var mixed
	 */
	protected $item;
	protected ?Schema $valueSchema;
	protected ?AbstractSetting $nearestSetting;
	protected array $pathInSetting;
	protected bool $hasFailed;

	public function __construct(
		$item,
		?Schema $valueSchema = null,
		?AbstractSetting $nearestSetting = null,
		array $pathInSetting = [],
		bool $hasFailed = false
	) {
		$this->item = $item;
		$this->valueSchema = $valueSchema;
		$this->nearestSetting = $nearestSetting;
		$this->pathInSetting = $pathInSetting;
		$this->hasFailed = $hasFailed;
	}

	//region Getters

	public function getItem() {
		return $this->item;
	}

	public function getValue() {
		if ( $this->item instanceof AbstractSetting ) {
			return $this->item->getValue();
		} else {
			return $this->item;
		}
	}

	public function getValueSchema(): ?Schema {
		return $this->valueSchema;
	}

	public function getNearestSetting(): ?AbstractSetting {
		return $this->nearestSetting;
	}

	public function getPathInSetting(): array {
		return $this->pathInSetting;
	}

	/**
	 * Returns true if the resolution points directly to a setting, and the path in that setting is empty.
	 */
	public function isLeafSetting(): bool {
		return ($this->nearestSetting !== null) && empty($this->pathInSetting);
	}

	public function isFailure(): bool {
		return $this->hasFailed;
	}

	public function isEmptyFailure(): bool {
		return (
			($this->hasFailed)
			&& ($this->item === null)
			&& ($this->nearestSetting === null)
			&& (empty($this->valueSchema))
		);
	}

	//endregion

	//region Resolution Steps

	public function enterSetting(AbstractSetting $setting): Resolution {
		return new Resolution(
			$setting->getValue(),
			($setting instanceof SettingWithSchema) ? $setting->getSchema() : null,
			$setting,
			[], //Reset the path in setting when entering a new setting.
			$this->hasFailed
		);
	}

	public function descend($pathSegment, $value, ?Schema $schema = null): Resolution {
		$path = $this->pathInSetting;
		$path[] = $pathSegment;

		return new Resolution($value, $schema, $this->nearestSetting, $path, $this->hasFailed);
	}

	public function fail($pathSegment, $value = null, ?Schema $schema = null): Resolution {
		$path = $this->pathInSetting;
		$path[] = $pathSegment;
		return new Resolution($value, $schema, null, $path, true);
	}

	//endregion

	//region Helpers
	public function getFullSettingPath() {
		if ( $this->nearestSetting ) {
			$settingPath = explode('.', $this->nearestSetting->getId());
		} else {
			$settingPath = [];
		}

		return array_merge($settingPath, $this->pathInSetting);
	}

	public function isEditableByUser(?EvaluationContext $context = null): bool {
		if ( $this->nearestSetting ) {
			return $this->nearestSetting->isEditableByUser($context);
		}
		return true;
	}

	protected function getDescribable(): ?Describable {
		//If the setting is available, and it's the final target of the reference,
		//return it as the describable.
		if ( $this->nearestSetting && empty($this->pathInSetting) ) {
			return $this->nearestSetting;
		}
		//Otherwise, use the schema.
		$schema = $this->getValueSchema();
		if ( $schema ) {
			return $schema;
		}
		return null;
	}

	public function getLabel(?EvaluationContext $context = null): string {
		$describable = $this->getDescribable();
		if ( $describable ) {
			return $describable->getLabel($context);
		}
		return '[Unresolved Describable (label)]';
	}

	public function getDescription(?EvaluationContext $context = null): string {
		$describable = $this->getDescribable();
		if ( $describable ) {
			return $describable->getDescription($context);
		}
		return '[Unresolved Describable (description)]';
	}
	//endregion

	/**
	 * @return Option<self>
	 */
	public function toOption(): Option {
		if ( $this->hasFailed ) {
			return Option::none();
		}

		return Option::some($this);
	}
}