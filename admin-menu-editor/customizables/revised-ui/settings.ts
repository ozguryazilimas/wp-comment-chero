'use strict';

import {
	ArraySchema,
	ParseError,
	RecordSchema,
	Schema,
	SerializedSchema, SerializedSchemaOrRef, StructSchema,
	SchemaDeserializer
} from './schemas.js';
import {
	IterableBinding,
	ReadableBinding,
	KeyedBinding,
	WritableBinding
} from '../../shared-dsl/client/wire-dsl.js';
import Option = AmeMiniFunc.Option;
import none = AmeMiniFunc.none;
import some = AmeMiniFunc.some;

export interface Binding<T = unknown> {
	readonly value: KnockoutObservable<T | null>;
}

export abstract class Setting<T = any, S extends Schema<T> = Schema<T>> implements Binding<T>, WritableBinding<T> {
	abstract readonly value: KnockoutObservable<T | null>;
	protected hasCustomValue: boolean;

	protected readonly schema: S;
	public readonly isValid: KnockoutComputed<boolean>;
	public abstract readonly validationError: KnockoutObservable<ParseError | null>;
	/**
	 * Whether this setting or any of its children have validation errors.
	 */
	public readonly hasValidationErrors: KnockoutObservable<boolean>;

	/**
	 * Write-once property to store the key of this setting in its parent struct or record.
	 * Only for internal use; the setting itself should not touch this property directly.
	 */
	protected keyInParent: string | null = null;
	/**
	 * Whether this setting exists in its parent struct or record.
	 */
	public readonly exists: KnockoutObservable<boolean> = ko.observable(true);

	protected constructor(schema: S, hasCustomValue: boolean = true) {
		this.schema = schema;
		this.hasCustomValue = hasCustomValue;

		this.isValid = ko.pureComputed(() => this.validationError() === null);

		this.hasValidationErrors = ko.pureComputed(() => {
			if (this.validationError() !== null) {
				return true;
			}

			if (this.isIterable()) {
				//Examine child settings.
				for (const child of this.items()) {
					if (child.hasValidationErrors()) {
						return true;
					}
				}
			}

			return false;
		})
	}

	getSchema(): S {
		return this.schema;
	}

	isWritable(): this is WritableBinding<T> {
		return true;
	}

	isKeyed(): this is KeyedBinding {
		return false;
	}

	isIterable(): this is IterableBinding {
		return false;
	}

	/**
	 * Update the value of this setting without performing validation.
	 *
	 * This is intended for use cases like initializing a form with empty values, where the form
	 * can't be submitted until the user has filled in all required fields.
	 */
	abstract updateWithoutValidation(newValue: T): void;

	/**
	 * Serialize the value of this setting to a plain JS value for storage or transmission.
	 *
	 * Unlike `value()`, this method may intentionally drop empty optional fields or otherwise
	 * transform the value to a more compact representation (depending on the schema).
	 */
	serializeValue(): any {
		return this.value();
	}

	setKeyInParent(key: string): void {
		if (this.keyInParent !== null) {
			throw new Error(`keyInParent is already set to "${this.keyInParent}". It cannot be changed.`);
		}
		this.keyInParent = key;
	}

	getKeyInParent(): string | null {
		return this.keyInParent;
	}
}

class SingularSetting extends Setting {
	readonly value: KnockoutObservable<unknown>;
	protected readonly rawValue: KnockoutObservable<unknown>;
	readonly validationError: KnockoutObservable<ParseError | null>;

	protected lastGoodValue: unknown;

	constructor(schema: Schema<unknown>, value: unknown, hasCustomValue: boolean = true) {
		super(schema, hasCustomValue);

		this.lastGoodValue = value;
		this.rawValue = ko.observable(value);
		const validationResult = ko.pureComputed(
			() => this.schema.safeParse(this.rawValue())
		);

		this.value = ko.pureComputed({
			read: () => {
				const parseResult = validationResult();
				if (parseResult.success) {
					this.lastGoodValue = parseResult.value;
					return this.lastGoodValue;
				}
				return this.lastGoodValue;
			},
			write: (newValue) => this.rawValue(newValue),
		});

		this.validationError = ko.pureComputed(() => {
			const parseResult = validationResult();
			if (!parseResult.success) {
				return parseResult.error;
			}
			return null;
		});
	}

	updateWithoutValidation(newValue: any): void {
		this.lastGoodValue = newValue;
		this.rawValue(newValue);
	}
}

export class StructSetting extends Setting<object, StructSchema> implements KeyedBinding, IterableBinding<Setting, object> {
	readonly value: KnockoutObservable<object | null>;

	public readonly items: KnockoutObservableArray<Setting>;
	protected readonly fields: Record<string, Setting>;

	readonly validationError: KnockoutObservable<ParseError | null>;

	constructor(schema: StructSchema, value: object | null, hasCustomValue: boolean = true) {
		super(schema, hasCustomValue);

		this.fields = {};
		const items: Setting[] = [];
		for (const [property, propertySchema] of Object.entries(schema.fieldSchemas)) {
			let propertyValue: unknown,
				hasCustomValueForProperty: boolean = false,
				fieldExists: boolean = false;

			if ((value !== null) && value.hasOwnProperty(property)) {
				fieldExists = true;
				propertyValue = (value as Record<string, unknown>)[property];
				hasCustomValueForProperty = hasCustomValue; //True only if the struct itself has a custom value.
			} else {
				propertyValue = null;
			}

			const newSetting = createSettingFromSchema(propertySchema, propertyValue, hasCustomValueForProperty);
			newSetting.setKeyInParent(property);
			newSetting.exists(fieldExists);

			this.fields[property] = newSetting;
			items.push(newSetting);
		}

		this.items = ko.observableArray(items);

		this.value = ko.pureComputed({
			read: () => {
				const result: Record<string, unknown> = {};
				for (const setting of this.items()) {
					if (setting.exists()) {
						const property = setting.getKeyInParent();
						if (property === null) {
							throw new Error('StructSetting: setting has no keyInParent set. This should never happen.');
						}
						result[property] = setting.value();
					}
				}
				return result;
			},
			write: (newValue) => {
				if ((typeof newValue !== 'object') || (newValue === null)) {
					const displayType = (newValue === null) ? 'null' : typeof newValue;
					throw new Error(`Expected an object for StructSetting, got ${displayType} (${newValue})`);
				}

				for (const property of Object.keys(this.schema.fieldSchemas)) {
					const setting = this.fields[property];
					if (newValue.hasOwnProperty(property)) {
						setting.exists(true);
						setting.value(newValue[property]);
					} else {
						setting.exists(false);
					}
				}
			}
		}).extend({deferred: true});

		const structValidationError = ko.pureComputed(() => {
			//Validate only the struct itself, not its children. Child validation errors are checked
			//later, in the validationError computed.
			const parseResult = this.schema.safeParse(this.value(), [], true);
			if (!parseResult.success) {
				return parseResult.error;
			}
			return null;
		});

		this.validationError = ko.pureComputed(() => {
			const structError = structValidationError();
			if (structError) {
				return structError;
			}

			for (const setting of this.items()) {
				const childError = setting.validationError();
				if (childError) {
					return childError.copyWithPrependedPath([setting.getKeyInParent()!]);
				}
			}

			return null;
		});
	}

	/**
	 * Add a new field to the struct.
	 *
	 * This is mainly intended for use with "virtual" structs that are validated client-side.
	 * Trying to submit a struct with fields that are not defined in the original schema will
	 * usually result in those fields being stripped out.
	 */
	addSetting(property: string, setting: Setting) {
		if (this.fields.hasOwnProperty(property)) {
			throw new Error(`Field "${property}" already exists in StructSetting.`);
		}

		this.fields[property] = setting;
		this.schema.fieldSchemas[property] = setting.getSchema();

		setting.setKeyInParent(property);
		setting.exists(true);

		this.items.push(setting);
	}

	removeSetting(property: string) {
		if (!this.fields.hasOwnProperty(property)) {
			throw new Error(`Field "${property}" does not exist in StructSetting.`);
		}

		const setting = this.fields[property];
		delete this.fields[property];
		delete this.schema.fieldSchemas[property];

		this.items.remove(setting);
		setting.exists(false);
	}

	getChildByPath(path: string): Option<Setting> {
		const segments = path.split('.');
		let current: ReadableBinding = this;
		for (const segment of segments) {
			if (!current.isKeyed()) {
				return none;
			}
			const childOption = current.getChild(segment);
			if (childOption.isEmpty()) {
				return none;
			}
			current = childOption.get();
		}

		if (!(current instanceof Setting)) {
			return none;
		}
		return some(current);
	}

	getChild(property: string): Option<Setting> {
		if (!this.fields.hasOwnProperty(property)) {
			return none;
		}
		if (!this.fields[property].exists()) {
			return none;
		}
		return some(this.fields[property]);
	}

	isKeyed(): this is KeyedBinding {
		return true;
	}

	isIterable(): this is IterableBinding {
		return true;
	}

	updateWithoutValidation(newValue: object): void {
		for (const property of Object.keys(this.schema.fieldSchemas)) {
			const setting = this.fields[property];
			if (newValue.hasOwnProperty(property)) {
				setting.exists(true);
				setting.updateWithoutValidation((newValue as any)[property]);
			} else {
				setting.exists(false);
			}
		}
	}

	serializeValue(): any {
		const result: Record<string, unknown> = {};
		for (const setting of this.items()) {
			if (setting.exists()) {
				const property = setting.getKeyInParent();
				if (property === null) {
					throw new Error('StructSetting: setting has no keyInParent set. This should never happen.');
				}

				const childValue = setting.serializeValue();
				const childSchema = setting.getSchema();
				//Drop optional fields that are empty, to avoid cluttering the serialized output.
				//Exception: If the field has a non-empty default value, we want to keep the empty
				//custom value so that it overrides the default.
				if (
					!this.schema.isFieldRequired(property)
					&& childSchema.isEmptyValue(childValue)
					&& !childSchema.hasNonEmptyDefaultValue()
				) {
					continue;
				}

				result[property] = childValue;
			}
		}
		return result;
	}
}

export class SparseStructSetting extends StructSetting {
	constructor() {
		super(new StructSchema({}), null, false);
	}
}

export class RecordSetting extends Setting<Record<string, unknown>, RecordSchema>
	implements KeyedBinding<Record<string, unknown>>, IterableBinding<Setting, Record<string, unknown>> {

	readonly value: KnockoutObservable<Record<string, unknown> | null>;

	/**
	 * An observable array of the settings in the record. Useful for iterating with Knockout bindings.
	 * While public, this array should not be modified directly.
	 */
	public readonly items: KnockoutObservableArray<Setting>;

	/**
	 * Lookup table for child settings by their property names.
	 */
	protected fields: Record<string, Setting>;
	readonly validationError: KnockoutObservable<ParseError | null>;

	constructor(schema: RecordSchema, value: object | null, hasCustomValue: boolean = true) {
		super(schema, hasCustomValue);

		this.fields = {};
		const items: Setting[] = [];
		if (value !== null) {
			for (const [property, propertyValue] of Object.entries(value)) {
				const setting = createSettingFromSchema(schema.valueSchema, propertyValue, hasCustomValue);
				setting.setKeyInParent(property);
				this.fields[property] = setting;
				items.push(setting);
			}
		}
		this.items = ko.observableArray(items);

		this.value = ko.pureComputed({
			read: () => {
				const result: Record<string, unknown> = {};
				for (const setting of this.items()) {
					const key = setting.getKeyInParent();
					if (key === null) {
						throw new Error('RecordSetting: setting has no keyInParent set. This should not happen.');
					}
					result[key] = setting.value();
				}
				return result;
			},
			write: (newValue) => {
				if ((typeof newValue !== 'object') || (newValue === null)) {
					const displayType = (newValue === null) ? 'null' : typeof newValue;
					throw new Error(`Expected a non-null object for RecordSetting, got ${displayType}`);
				}

				const newItems: Setting[] = [];
				for (const [property, propertyValue] of Object.entries(newValue)) {
					const existing = this.fields[property];
					if (existing) {
						existing.value(propertyValue);
						newItems.push(existing);
					} else {
						const newSetting = createSettingFromSchema(
							this.schema.valueSchema,
							propertyValue,
							hasCustomValue
						);
						newSetting.setKeyInParent(property);
						newItems.push(newSetting);
					}
				}

				this.fields = Object.fromEntries(newItems.map(s => [s.getKeyInParent()!, s]));
				this.items(newItems);
			}
		}).extend({deferred: true}); //Defer to unnecessary re-evaluations when multiple fields are updated at once.

		this.validationError = ko.pureComputed(() => {
			for (const setting of this.items()) {
				const childError = setting.validationError();
				if (childError) {
					return childError;
				}
			}
			return null;
		});
	}

	addItem(property: string, value: unknown) {
		if (this.fields.hasOwnProperty(property)) {
			throw new Error(`Field "${property}" already exists in RecordSetting.`);
		}

		const newSetting = createSettingFromSchema(this.schema.valueSchema, value, this.hasCustomValue);
		newSetting.setKeyInParent(property);
		this.fields[property] = newSetting;
		this.items.push(newSetting);
	}

	removeItem(item: Setting) {
		const key = item.getKeyInParent();
		if (key === null) {
			throw new Error('RecordSetting: setting has no keyInParent set. This should not happen.');
		}
		delete this.fields[key];
		this.items.remove(item);
	}

	getChild(property: string): Option<Setting> {
		if (!this.fields.hasOwnProperty(property)) {
			return none;
		}
		return some(this.fields[property]);
	}

	isKeyed(): this is KeyedBinding {
		return true;
	}

	isIterable(): this is IterableBinding {
		return true;
	}

	updateWithoutValidation(newValue: Record<string, unknown>): void {
		const newItems: Setting[] = [];
		for (const [property, propertyValue] of Object.entries(newValue)) {
			const existing = this.fields[property];
			if (existing) {
				existing.updateWithoutValidation(propertyValue);
				newItems.push(existing);
			} else {
				const newSetting = createSettingFromSchema(
					this.schema.valueSchema,
					propertyValue,
					this.hasCustomValue
				);
				newSetting.setKeyInParent(property);
				newItems.push(newSetting);
			}
		}
		this.fields = Object.fromEntries(newItems.map(s => [s.getKeyInParent()!, s]));
		this.items(newItems);
	}
}

export class ArraySetting extends Setting<unknown[]> implements IterableBinding<Setting, unknown[]> {
	readonly value: KnockoutObservable<unknown[] | null>;
	public readonly items: KnockoutObservableArray<Setting>;
	readonly validationError: KnockoutObservable<ParseError | null>;

	constructor(schema: ArraySchema<unknown>, value: unknown[], hasCustomValue: boolean = true) {
		super(schema, hasCustomValue);

		this.items = ko.observableArray(value.map(
			item => createSettingFromSchema(schema.itemSchema, item, hasCustomValue)
		));

		this.value = ko.pureComputed({
			read: () => {
				return this.items().map(item => item.value());
			},
			write: (newValue) => {
				if (!Array.isArray(newValue)) {
					throw new Error(`Expected array, got ${typeof newValue}`);
				}

				this.items(newValue.map(
					item => createSettingFromSchema(schema.itemSchema, item, hasCustomValue)
				));
			}
		});

		this.validationError = ko.pureComputed(() => {
			for (const item of this.items()) {
				const childError = item.validationError();
				if (childError) {
					return childError;
				}
			}
			return null;
		});
	}

	isIterable(): this is IterableBinding {
		return true;
	}

	addItem(value: unknown) {
		const newItem = createSettingFromSchema(
			(this.schema as ArraySchema<unknown>).itemSchema,
			value,
			this.hasCustomValue
		);
		this.items.push(newItem);
	}

	removeItem(item: Setting) {
		this.items.remove(item);
	}

	updateWithoutValidation(newValue: unknown[]): void {
		if (!Array.isArray(newValue)) {
			throw new Error(`Expected array, got ${typeof newValue}`);
		}

		this.items(newValue.map(
			item => createSettingFromSchema((this.schema as ArraySchema<unknown>).itemSchema, item, this.hasCustomValue)
		));
	}
}

export function createSettingFromSchema(
	schema: Schema<unknown>,
	value: unknown,
	hasCustomValue: boolean = true
): Setting {
	if (typeof value === 'undefined') {
		value = null;
		hasCustomValue = false;
	}

	if (schema instanceof StructSchema) {
		if ((typeof value !== 'object') && (value !== null)) {
			throw new Error(`Expected object or null for StructSchema, got ${typeof value}`);
		}
		return new StructSetting(schema, value, hasCustomValue);
	} else if (schema instanceof RecordSchema) {
		if ((typeof value !== 'object') && (value !== null)) {
			throw new Error(`Expected object or null for RecordSchema, got ${typeof value}`);
		}
		return new RecordSetting(schema, value, hasCustomValue);
	} else if (schema instanceof ArraySchema) {
		if (!Array.isArray(value)) {
			throw new Error(`Expected array for ArraySchema, got ${typeof value}`);
		}
		return new ArraySetting(schema, value, hasCustomValue);
	} else {
		return new SingularSetting(schema, value, hasCustomValue);
	}
}

//region Serialization
export interface SerializedSetting {
	value?: any;
	schema: SerializedSchemaOrRef;
}

export interface SerializedSettingsMap {
	[path: string]: SerializedSetting;
}

export interface SerializedSettingsPack {
	settings: SerializedSettingsMap;
	//Note: Shared schemas can contain references to other shared schemas as their fields, but
	//the top-level entries in this object must be actual schemas, not references.
	sharedSchemas: Record<string, SerializedSchema>;
}

export function deserializeSettingsPackToStruct(serializedPack: SerializedSettingsPack): SparseStructSetting {
	const schemaDeserializer = new SchemaDeserializer(serializedPack.sharedSchemas);
	const structSetting = new SparseStructSetting();

	for (const [path, serializedSetting] of Object.entries(serializedPack.settings)) {
		const schema = schemaDeserializer.deserialize(serializedSetting.schema);
		const value = serializedSetting.value;
		const hasCustomValue = typeof value !== 'undefined';

		const segments = path.split('.');
		const finalSegment = segments.pop()!;
		const parent = ensureStructPath(structSetting, segments);

		if (!(parent instanceof StructSetting)) {
			throw new Error(`Cannot add field to non-StructSetting at "${segments.join('.')}"`);
		}
		if (parent.getChild(finalSegment).isDefined()) {
			throw new Error(`Setting "${path}" conflicts with an existing setting in the struct.`);
		}

		const newSetting = createSettingFromSchema(schema, value, hasCustomValue);
		parent.addSetting(finalSegment, newSetting);
	}

	return structSetting;
}

function ensureStructPath(struct: SparseStructSetting, segments: string[]): ReadableBinding {
	let current: ReadableBinding = struct;
	for (const [i, segment] of segments.entries()) {
		if (!current.isKeyed()) {
			throw new Error(`Cannot traverse into non-traversable setting at "${segments.slice(0, i).join('.')}"`);
		}

		let child = current.getChild(segment);
		if (child.isDefined()) {
			current = child.get();
		} else {
			if (!(current instanceof SparseStructSetting)) {
				throw new Error(`Cannot add child setting to non-SparseStructSetting at "${segments.slice(0, i + 1).join('.')}"`);
			}
			const struct = new SparseStructSetting();
			current.addSetting(segment, struct);
			current = struct;
		}
	}
	return current;
}

//endregion