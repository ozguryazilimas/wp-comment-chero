<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\WithSchema\CssPropertySetting;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\WithSchema\Font;
use YahnisElsts\AdminMenuEditor\ProCustomizable\Settings\WithSchema;

class SchemaFactory {
	public function string($label = null): StringSchema {
		return new StringSchema($label);
	}

	public function boolean($label = null): Schema {
		return new Boolean($label);
	}

	public function number($label = null): Number {
		return new Number($label);
	}

	public function int($label = null): Number {
		return (new Number($label))->int();
	}

	public function enum(array $values, $label = null): Enum {
		return (new Enum($label))->values($values);
	}

	public function computedEnum(callable $valueProvider, $label = null): Enum {
		return (new Enum($label))->valueCallback($valueProvider);
	}

	public function struct(array $fieldSchemas, $label = null): Struct {
		return new Struct($fieldSchemas, $label);
	}

	public function record(Schema $keySchema, Schema $itemSchema, $label = null): Record {
		return new Record($keySchema, $itemSchema, $label);
	}

	public function actorId($label = null): StringSchema {
		return $this->string($label ?? 'Actor ID')
			->min(1)->max(250)
			->regex('/^(?:user|role|special):/', 'Actor ID must start with "user:", "role:", or "special:".');
	}

	public function actorFeatureMap($label = null): Record {
		return $this->record(
			$this->actorId(),
			$this->boolean(),
			$label
		);
	}

	public function actorAccess($label = null): Record {
		return $this->actorFeatureMap($label);
	}

	/**
	 * Indexed array schema.
	 *
	 * "array" is a reserved keyword in PHP, so we can't use it as a method name.
	 *
	 * @param Schema $itemSchema
	 * @param string|null $label
	 * @return IndexedArray
	 */
	public function arr(Schema $itemSchema, ?string $label = null): IndexedArray {
		return new IndexedArray($itemSchema, $label);
	}

	/**
	 * @param Schema[] $schemas
	 * @param string|null $label
	 * @return Union
	 */
	public function union(array $schemas, ?string $label = null): Union {
		return new Union($schemas, $label);
	}

	public function cssColor($label = null, ?string $cssProperty = null): Color {
		$params = [];
		if ( $cssProperty !== null ) {
			$params['cssProperty'] = $cssProperty;
		}
		return (new Color($label))->orTransparent()->s(CssPropertySetting::class, $params);
	}

	public function cssLength(?string $label = null, ?string $cssProperty = null): Number {
		$params = [];
		if ( $cssProperty !== null ) {
			$params['cssProperty'] = $cssProperty;
		}
		return (new Number($label))->s(WithSchema\CssLengthSetting::class, $params);
	}

	public function cssFont(?string $label = null, ?bool $includesLineHeight = null): Struct {
		$params = [];
		if ( $includesLineHeight !== null ) {
			$params['includesLineHeight'] = $includesLineHeight;
		}
		return Font::createDefaultSchema($this, $label, $params);
	}

	public function cssPadding(?string $label = null): Struct {
		return WithSchema\CssBoxDimensions::createPaddingSchema($this, $label);
	}

	public function cssMargin(?string $label = null): Struct {
		return WithSchema\CssBoxDimensions::createMarginSchema($this, $label);
	}

	public function cssBorders(array $defaultWidths = []): Struct {
		return WithSchema\Borders::createDefaultSchema(
			$this,
			true,
			$defaultWidths
		)->settingParams(['label' => 'Border']);
	}

	public function cssBorderStyle(?string $label = 'Border style', bool $nullAllowed = true): Enum {
		$allowedValues = ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'];
		if ( $nullAllowed ) {
			array_unshift($allowedValues, null);
		}

		$schema = $this
			->enum($allowedValues, $label)
			->s(CssPropertySetting::class, ['cssProperty' => 'border-style']);

		if ( $nullAllowed ) {
			$schema->defaultValue(null);
		}
		return $schema;
	}

	/**
	 * Create a schema that takes a JSON string, parses it, and then validates the result
	 * against another schema.
	 *
	 * @param Schema|null $valueSchema
	 * @param string|null $label
	 * @return JsonValue
	 */
	public function json(?Schema $valueSchema = null, ?string $label = null): JsonValue {
		if ( $valueSchema === null ) {
			$valueSchema = new Anything();
		}
		return new JsonValue($valueSchema, $label);
	}
}