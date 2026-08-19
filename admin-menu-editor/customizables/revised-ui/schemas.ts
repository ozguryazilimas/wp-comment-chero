'use strict';

//region Schemas
import Either = AmeMiniFunc.Either;
import Option = AmeMiniFunc.Option;
import none = AmeMiniFunc.none;
import some = AmeMiniFunc.some;

type IssuePath = (string | number)[];

export abstract class Schema<T> {
	abstract safeParse(value: unknown, path?: IssuePath): SafeParseResult<T>;

	abstract readonly defaultValue: Option<T | null>;

	/**
	 * Determines if the given value is considered "empty" for this schema.
	 */
	isEmptyValue(value: unknown): boolean {
		return (value === null) || (typeof value === 'undefined');
	}

	hasNonEmptyDefaultValue(): boolean {
		return this.defaultValue.map(value => !this.isEmptyValue(value)).getOrElse(() => false);
	}
}

class BooleanSchema extends Schema<boolean> {
	readonly defaultValue: Option<boolean | null> = none;

	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<boolean> {
		if (typeof value === 'boolean') {
			return {success: true, value};
		} else {
			return {
				success: false,
				error: ParseError.fromMessage(`Expected boolean, got ${typeof value}`, 'type_error', path)
			};
		}
	}
}

abstract class CheckableSchema<T> extends Schema<T> {
	protected constructor(public readonly checks: SerializedCheck[] = []) {
		super();
	}

	protected applyChecks(value: T, path: IssuePath = []): SafeParseResult<T> {
		let convertedValue: T = value;
		const issues: ParseIssue[] = [];

		for (const check of this.checks) {
			const checkFunction = this.getCheckFunction(check.kind);
			if (!checkFunction) {
				issues.push(new ParseIssue(`Unknown check kind: ${check.kind}`, 'unknown_check', path));
				continue;
			}

			const checkResult = checkFunction(convertedValue, check.value);
			if (checkResult.isLeft()) {
				issues.push(checkResult.value);
			} else if (checkResult.isRight()) {
				convertedValue = checkResult.value;
			}
		}

		if (issues.length > 0) {
			return {success: false, error: new ParseError(issues)};
		} else {
			return {success: true, value: convertedValue};
		}
	}

	protected abstract getCheckFunction(kind: string): CheckFunction<T> | undefined;
}

class StringSchema extends CheckableSchema<string> {
	readonly defaultValue: Option<string | null> = none;

	constructor(checks: SerializedCheck[] = []) {
		super(checks);
	}

	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<string> {
		if (typeof value !== 'string') {
			return {
				success: false,
				error: ParseError.fromMessage(`Expected string, got ${typeof value}`, 'type_error', path)
			};
		}
		return this.applyChecks(value, path);
	}

	isEmptyValue(value: unknown): boolean {
		return (value === '') || super.isEmptyValue(value);
	}

	protected getCheckFunction(kind: string): CheckFunction<string> | undefined {
		return builtInStringChecks[kind];
	}
}

class EnumSchema extends Schema<unknown> {
	readonly defaultValue: Option<unknown | null> = none;

	constructor(public readonly values: unknown[]) {
		super();
	}

	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<unknown> {
		if (this.values.includes(value)) {
			return {success: true, value};
		} else {
			return {
				success: false,
				error: ParseError.fromMessage(
					`Expected one of ${JSON.stringify(this.values)}, got ${JSON.stringify(value)}`,
					'type_error',
					path
				)
			};
		}
	}
}

class NumberSchema extends CheckableSchema<number> {
	readonly defaultValue: Option<number | null> = none;

	constructor(checks: SerializedCheck[] = []) {
		super(checks);
	}

	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<number> {
		//Convert numeric strings to numbers.
		if (typeof value === 'string') {
			const parsedValue = parseFloat(value);
			if (!isNaN(parsedValue)) {
				value = parsedValue;
			}
		}

		if (typeof value !== 'number') {
			return {
				success: false,
				error: ParseError.fromMessage(`Expected number, got ${typeof value}`, 'type_error', path)
			};
		}
		return this.applyChecks(value, path);
	}

	protected getCheckFunction(kind: string): CheckFunction<number> | undefined {
		return builtinNumberChecks[kind];
	}
}

export class StructSchema extends Schema<{ [key: string]: unknown }> {
	readonly defaultValue: Option<{ [key: string]: unknown } | null> = none;

	constructor(
		public readonly fieldSchemas: { [key: string]: Schema<unknown> },
		private readonly requiredFields: string[] = []
	) {
		super();
	}

	safeParse(value: unknown, path: IssuePath = [], skipChildren: boolean = false): SafeParseResult<{
		[key: string]: unknown
	}> {
		if (typeof value !== 'object' || value === null) {
			return {
				success: false,
				error: ParseError.fromMessage(`Expected object, got ${typeof value}`, 'type_error', path)
			};
		}

		const resultObject: { [key: string]: unknown } = {};
		const issues: ParseIssue[] = [];

		for (const property of this.requiredFields) {
			if (!value.hasOwnProperty(property)) {
				issues.push(new ParseIssue(`Missing required property: ${property}`, 'missing_property', [...path, property]));
			}
		}

		if (!skipChildren) {
			for (const [property, schema] of Object.entries(this.fieldSchemas)) {
				if (value.hasOwnProperty(property)) {
					const parseResult = schema.safeParse(
						(value as { [key: string]: unknown })[property],
						[...path, property]
					);
					if (parseResult.success) {
						resultObject[property] = parseResult.value;
					} else {
						issues.push(new ParseIssue(
							`Error parsing property "${property}": ${parseResult.error.issues.map(issue => issue.message).join('; ')}`,
							'property_parse_error',
							[...path, property]
						));
					}
				}
			}
		}

		if (issues.length > 0) {
			return {success: false, error: new ParseError(issues)};
		} else {
			return {success: true, value: resultObject};
		}
	}

	isFieldRequired(fieldName: string): boolean {
		return this.requiredFields.includes(fieldName);
	}

	isEmptyValue(value: unknown): boolean {
		if (super.isEmptyValue(value)) {
			return true;
		}
		return isEmptyObject(value);
	}
}

export class RecordSchema extends Schema<{ [key: string]: unknown }> {
	constructor(
		public readonly keySchema: StringSchema,
		public readonly valueSchema: Schema<unknown>,
		readonly defaultValue: Option<{ [p: string]: unknown } | null> = none
	) {
		super();
	}

	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<{ [key: string]: unknown }> {
		if (typeof value !== 'object' || value === null) {
			return {
				success: false,
				error: ParseError.fromMessage(`Expected object, got ${typeof value}`, 'type_error', path)
			};
		}

		const resultObject: { [key: string]: unknown } = {};
		const issues: ParseIssue[] = [];

		for (const [key, val] of Object.entries(value)) {
			const keyParseResult = this.keySchema.safeParse(key);
			if (!keyParseResult.success) {
				issues.push(new ParseIssue(
					`Error parsing key "${key}": ${keyParseResult.error.issues.map(issue => issue.message).join('; ')}`,
					'key_parse_error',
					[...path, key]
				));
				continue;
			}

			const valueParseResult = this.valueSchema.safeParse(val);
			if (!valueParseResult.success) {
				issues.push(new ParseIssue(
					`Error parsing value for key "${key}": ${valueParseResult.error.issues.map(issue => issue.message).join('; ')}`,
					'value_parse_error',
					[...path, key]
				));
				continue;
			}

			resultObject[key] = valueParseResult.value;
		}

		if (issues.length > 0) {
			return {success: false, error: new ParseError(issues)};
		} else {
			return {success: true, value: resultObject};
		}
	}

	isEmptyValue(value: unknown): boolean {
		if (super.isEmptyValue(value)) {
			return true;
		}
		return isEmptyObject(value);
	}
}

export class ArraySchema<T extends unknown> extends Schema<T[]> {
	constructor(public readonly itemSchema: Schema<T>, readonly defaultValue: Option<T[] | null> = none) {
		super();
	}

	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<T[]> {
		if (!Array.isArray(value)) {
			return {
				success: false,
				error: ParseError.fromMessage(`Expected array, got ${typeof value}`, 'type_error', path)
			};
		}

		const resultArray: T[] = [];
		const issues: ParseIssue[] = [];

		for (let index = 0; index < value.length; index++) {
			const item = value[index];
			const parseResult = this.itemSchema.safeParse(item);
			if (parseResult.success) {
				resultArray.push(parseResult.value);
			} else {
				issues.push(new ParseIssue(
					`Error parsing item at index ${index}: ${parseResult.error.issues.map(issue => issue.message).join('; ')}`,
					'item_parse_error',
					[...path, index]
				));
			}
		}

		if (issues.length > 0) {
			return {success: false, error: new ParseError(issues)};
		} else {
			return {success: true, value: resultArray};
		}
	}


	isEmptyValue(value: unknown): boolean {
		if (super.isEmptyValue(value)) {
			return true;
		}
		if (Array.isArray(value)) {
			return value.length === 0;
		}
		return false;
	}
}

class ColorSchema extends StringSchema {
	safeParse(value: unknown, path: IssuePath = []): SafeParseResult<string> {
		const result = super.safeParse(value, path);
		if (!result.success) {
			return result;
		}

		//TODO: Color validation logic (e.g., regex for hex colors, named colors, etc.)

		return {success: true, value: result.value};
	}
}

function isEmptyObject(obj: unknown): boolean {
	if (typeof obj !== 'object' || obj === null) {
		return false;
	}
	return Object.keys(obj).length === 0;
}

//endregion

//region Parsing result types
type SafeParseResult<T> = {
	success: true;
	value: T;
} | {
	success: false;
	error: ParseError;
}

export class ParseError {
	public readonly issues: ParseIssue[];

	constructor(issues: ParseIssue[]) {
		this.issues = issues;
	}

	copyWithPrependedPath(newPath: (string | number)[]): ParseError {
		const newIssues = this.issues.map(issue => new ParseIssue(
			issue.message,
			issue.code,
			[...newPath, ...issue.path]
		));
		return new ParseError(newIssues);
	}

	static fromMessage(message: string, code?: string, path: (string | number)[] = []): ParseError {
		return new ParseError([new ParseIssue(message, code, path)]);
	}
}

class ParseIssue {
	public readonly message: string;
	public readonly code?: string;
	public readonly path: (string | number)[];

	constructor(message: string, code?: string, path: (string | number)[] = []) {
		this.message = message;
		this.code = code;
		this.path = path;
	}
}

//endregion

//region Serialization
export type SerializedSchema =
	SerializedBooleanSchema
	| SerializedStringSchema
	| SerializedEnumSchema
	| SerializedNumberSchema
	| SerializedStructSchema
	| SerializedRecordSchema
	| SerializedArraySchema
	| SerializedColorSchema;

export type SerializedSchemaOrRef = SerializedSchema | SharedSchemaRef;

interface BaseSerializedSchema {
	nullable?: boolean;
	convertEmptyStringsToNull?: boolean;
}

export interface SerializedBooleanSchema extends BaseSerializedSchema {
	type: 'boolean';
}

interface SerializedCheck {
	kind: string;
	value?: unknown;
}

interface SerializedCheckableSchema {
	checks?: SerializedCheck[];
}

interface SerializedStringLikeSchema extends SerializedCheckableSchema, BaseSerializedSchema {
	strict?: boolean;
}

export interface SerializedStringSchema extends SerializedStringLikeSchema {
	type: 'string';
}

export interface SerializedColorSchema extends SerializedStringLikeSchema {
	type: 'color';
	transparentAllowed?: boolean;
}

export interface SerializedEnumSchema extends BaseSerializedSchema {
	type: 'enum';
	values: unknown[];
}

export interface SerializedNumberSchema extends SerializedCheckableSchema, BaseSerializedSchema {
	type: 'number';
}

export interface SerializedStructSchema extends BaseSerializedSchema {
	type: 'struct';
	fieldSchemas: { [key: string]: SerializedSchemaOrRef };
	requiredFields: string[];
}

export interface SerializedRecordSchema extends BaseSerializedSchema {
	type: 'record';
	keySchema: SerializedStringSchema | SerializedSchemaOrRef;
	itemSchema: SerializedSchemaOrRef;
	defaultValue?: Record<string, unknown> | null;
}

export interface SerializedArraySchema extends BaseSerializedSchema {
	type: 'array';
	itemSchema: SerializedSchemaOrRef;
}

interface SharedSchemaRef {
	_ref: string;
}

export type SharedSchemasCollection = Record<string, Schema<unknown>>;

export class SchemaDeserializer {
	private readonly sharedSchemas: SharedSchemasCollection = {};

	constructor(protected readonly serializedSharedSchemas: Record<string, SerializedSchema> = {}) {

	}

	deserialize(serialized: SerializedSchemaOrRef): Schema<unknown> {
		if ('_ref' in serialized) {
			const ref = (serialized as SharedSchemaRef)._ref;
			if (ref in this.sharedSchemas) {
				return this.sharedSchemas[ref];
			}
			//Deserialize the shared schema on demand.
			if (ref in this.serializedSharedSchemas) {
				this.sharedSchemas[ref] = this.deserialize(this.serializedSharedSchemas[ref]);
				return this.sharedSchemas[ref];
			}
			throw new Error(`Referenced schema "${ref}" not found in shared schemas.`);
		}

		switch (serialized.type) {
			case 'boolean':
				return new BooleanSchema();
			case 'string':
				return new StringSchema(serialized.checks);
			case 'enum':
				return new EnumSchema(serialized.values);
			case 'number':
				return new NumberSchema(serialized.checks);
			case 'struct':
				const fieldSchemas: { [key: string]: Schema<unknown> } = {};
				for (const [field, fieldSchema] of Object.entries(serialized.fieldSchemas)) {
					fieldSchemas[field] = this.deserialize(fieldSchema);
				}
				return new StructSchema(fieldSchemas, serialized.requiredFields);
			case 'record':
				return new RecordSchema(
					this.deserialize(serialized.keySchema) as StringSchema,
					this.deserialize(serialized.itemSchema),
					(typeof serialized.defaultValue !== 'undefined')
						? some(serialized.defaultValue)
						: none
				);
			case 'color':
				return new ColorSchema();
			case 'array':
				return new ArraySchema(this.deserialize(serialized.itemSchema));
			default:
				throw new Error(`Unknown schema type: ${(serialized as any).type}`);
		}
	}
}
//endregion

//region Built-in checks

type CheckResult<T> = Either<ParseIssue, T>;

type CheckFunction<T> = (inputValue: unknown, checkValue: any) => CheckResult<T>;

const builtInStringChecks: { [key: string]: CheckFunction<string> } = {
	'minLength': (value: unknown, minLength: number) => {
		if (typeof value === 'string') {
			return (value.length >= minLength)
				? Either.right(value)
				: Either.left(new ParseIssue(`String is shorter than ${minLength} characters.`, 'minLength'));
		}
		return Either.left(new ParseIssue('Value is not a string.', 'type'));
	},
	'maxLength': (value: unknown, maxLength: number) => {
		if (typeof value === 'string') {
			return (value.length <= maxLength)
				? Either.right(value)
				: Either.left(new ParseIssue(`String is longer than ${maxLength} characters.`, 'maxLength'));
		}
		return Either.left(new ParseIssue('Value is not a string.', 'type'));
	},
	'regex': (value: unknown, pattern: string) => {
		if (typeof value === 'string') {
			const regex = new RegExp(pattern);
			return regex.test(value)
				? Either.right(value)
				: Either.left(new ParseIssue(`String does not match the pattern ${pattern}.`, 'regex'));
		}
		return Either.left(new ParseIssue('Value is not a string.', 'type'));
	},
	'trim': (value: unknown) => {
		if (typeof value === 'string') {
			return Either.right(value.trim());
		}
		return Either.left(new ParseIssue('Value is not a string.', 'type'));
	}
}

const builtinNumberChecks: { [key: string]: CheckFunction<number> } = {
	'min': (value: unknown, minValue: number) => {
		if (typeof value === 'number') {
			return (value >= minValue)
				? Either.right(value)
				: Either.left(new ParseIssue(`Number is less than ${minValue}.`, 'min'));
		}
		return Either.left(new ParseIssue('Value is not a number.', 'type'));
	},
	'max': (value: unknown, maxValue: number) => {
		if (typeof value === 'number') {
			return (value <= maxValue)
				? Either.right(value)
				: Either.left(new ParseIssue(`Number is greater than ${maxValue}.`, 'max'));
		}
		return Either.left(new ParseIssue('Value is not a number.', 'type'));
	}
};

//endregion