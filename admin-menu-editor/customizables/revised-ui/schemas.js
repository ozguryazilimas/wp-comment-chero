'use strict';
//region Schemas
var Either = AmeMiniFunc.Either;
var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;
export class Schema {
    /**
     * Determines if the given value is considered "empty" for this schema.
     */
    isEmptyValue(value) {
        return (value === null) || (typeof value === 'undefined');
    }
    hasNonEmptyDefaultValue() {
        return this.defaultValue.map(value => !this.isEmptyValue(value)).getOrElse(() => false);
    }
}
class BooleanSchema extends Schema {
    constructor() {
        super(...arguments);
        this.defaultValue = none;
    }
    safeParse(value, path = []) {
        if (typeof value === 'boolean') {
            return { success: true, value };
        }
        else {
            return {
                success: false,
                error: ParseError.fromMessage(`Expected boolean, got ${typeof value}`, 'type_error', path)
            };
        }
    }
}
class CheckableSchema extends Schema {
    constructor(checks = []) {
        super();
        this.checks = checks;
    }
    applyChecks(value, path = []) {
        let convertedValue = value;
        const issues = [];
        for (const check of this.checks) {
            const checkFunction = this.getCheckFunction(check.kind);
            if (!checkFunction) {
                issues.push(new ParseIssue(`Unknown check kind: ${check.kind}`, 'unknown_check', path));
                continue;
            }
            const checkResult = checkFunction(convertedValue, check.value);
            if (checkResult.isLeft()) {
                issues.push(checkResult.value);
            }
            else if (checkResult.isRight()) {
                convertedValue = checkResult.value;
            }
        }
        if (issues.length > 0) {
            return { success: false, error: new ParseError(issues) };
        }
        else {
            return { success: true, value: convertedValue };
        }
    }
}
class StringSchema extends CheckableSchema {
    constructor(checks = []) {
        super(checks);
        this.defaultValue = none;
    }
    safeParse(value, path = []) {
        if (typeof value !== 'string') {
            return {
                success: false,
                error: ParseError.fromMessage(`Expected string, got ${typeof value}`, 'type_error', path)
            };
        }
        return this.applyChecks(value, path);
    }
    isEmptyValue(value) {
        return (value === '') || super.isEmptyValue(value);
    }
    getCheckFunction(kind) {
        return builtInStringChecks[kind];
    }
}
class EnumSchema extends Schema {
    constructor(values) {
        super();
        this.values = values;
        this.defaultValue = none;
    }
    safeParse(value, path = []) {
        if (this.values.includes(value)) {
            return { success: true, value };
        }
        else {
            return {
                success: false,
                error: ParseError.fromMessage(`Expected one of ${JSON.stringify(this.values)}, got ${JSON.stringify(value)}`, 'type_error', path)
            };
        }
    }
}
class NumberSchema extends CheckableSchema {
    constructor(checks = []) {
        super(checks);
        this.defaultValue = none;
    }
    safeParse(value, path = []) {
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
    getCheckFunction(kind) {
        return builtinNumberChecks[kind];
    }
}
export class StructSchema extends Schema {
    constructor(fieldSchemas, requiredFields = []) {
        super();
        this.fieldSchemas = fieldSchemas;
        this.requiredFields = requiredFields;
        this.defaultValue = none;
    }
    safeParse(value, path = [], skipChildren = false) {
        if (typeof value !== 'object' || value === null) {
            return {
                success: false,
                error: ParseError.fromMessage(`Expected object, got ${typeof value}`, 'type_error', path)
            };
        }
        const resultObject = {};
        const issues = [];
        for (const property of this.requiredFields) {
            if (!value.hasOwnProperty(property)) {
                issues.push(new ParseIssue(`Missing required property: ${property}`, 'missing_property', [...path, property]));
            }
        }
        if (!skipChildren) {
            for (const [property, schema] of Object.entries(this.fieldSchemas)) {
                if (value.hasOwnProperty(property)) {
                    const parseResult = schema.safeParse(value[property], [...path, property]);
                    if (parseResult.success) {
                        resultObject[property] = parseResult.value;
                    }
                    else {
                        issues.push(new ParseIssue(`Error parsing property "${property}": ${parseResult.error.issues.map(issue => issue.message).join('; ')}`, 'property_parse_error', [...path, property]));
                    }
                }
            }
        }
        if (issues.length > 0) {
            return { success: false, error: new ParseError(issues) };
        }
        else {
            return { success: true, value: resultObject };
        }
    }
    isFieldRequired(fieldName) {
        return this.requiredFields.includes(fieldName);
    }
    isEmptyValue(value) {
        if (super.isEmptyValue(value)) {
            return true;
        }
        return isEmptyObject(value);
    }
}
export class RecordSchema extends Schema {
    constructor(keySchema, valueSchema, defaultValue = none) {
        super();
        this.keySchema = keySchema;
        this.valueSchema = valueSchema;
        this.defaultValue = defaultValue;
    }
    safeParse(value, path = []) {
        if (typeof value !== 'object' || value === null) {
            return {
                success: false,
                error: ParseError.fromMessage(`Expected object, got ${typeof value}`, 'type_error', path)
            };
        }
        const resultObject = {};
        const issues = [];
        for (const [key, val] of Object.entries(value)) {
            const keyParseResult = this.keySchema.safeParse(key);
            if (!keyParseResult.success) {
                issues.push(new ParseIssue(`Error parsing key "${key}": ${keyParseResult.error.issues.map(issue => issue.message).join('; ')}`, 'key_parse_error', [...path, key]));
                continue;
            }
            const valueParseResult = this.valueSchema.safeParse(val);
            if (!valueParseResult.success) {
                issues.push(new ParseIssue(`Error parsing value for key "${key}": ${valueParseResult.error.issues.map(issue => issue.message).join('; ')}`, 'value_parse_error', [...path, key]));
                continue;
            }
            resultObject[key] = valueParseResult.value;
        }
        if (issues.length > 0) {
            return { success: false, error: new ParseError(issues) };
        }
        else {
            return { success: true, value: resultObject };
        }
    }
    isEmptyValue(value) {
        if (super.isEmptyValue(value)) {
            return true;
        }
        return isEmptyObject(value);
    }
}
export class ArraySchema extends Schema {
    constructor(itemSchema, defaultValue = none) {
        super();
        this.itemSchema = itemSchema;
        this.defaultValue = defaultValue;
    }
    safeParse(value, path = []) {
        if (!Array.isArray(value)) {
            return {
                success: false,
                error: ParseError.fromMessage(`Expected array, got ${typeof value}`, 'type_error', path)
            };
        }
        const resultArray = [];
        const issues = [];
        for (let index = 0; index < value.length; index++) {
            const item = value[index];
            const parseResult = this.itemSchema.safeParse(item);
            if (parseResult.success) {
                resultArray.push(parseResult.value);
            }
            else {
                issues.push(new ParseIssue(`Error parsing item at index ${index}: ${parseResult.error.issues.map(issue => issue.message).join('; ')}`, 'item_parse_error', [...path, index]));
            }
        }
        if (issues.length > 0) {
            return { success: false, error: new ParseError(issues) };
        }
        else {
            return { success: true, value: resultArray };
        }
    }
    isEmptyValue(value) {
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
    safeParse(value, path = []) {
        const result = super.safeParse(value, path);
        if (!result.success) {
            return result;
        }
        //TODO: Color validation logic (e.g., regex for hex colors, named colors, etc.)
        return { success: true, value: result.value };
    }
}
function isEmptyObject(obj) {
    if (typeof obj !== 'object' || obj === null) {
        return false;
    }
    return Object.keys(obj).length === 0;
}
export class ParseError {
    constructor(issues) {
        this.issues = issues;
    }
    copyWithPrependedPath(newPath) {
        const newIssues = this.issues.map(issue => new ParseIssue(issue.message, issue.code, [...newPath, ...issue.path]));
        return new ParseError(newIssues);
    }
    static fromMessage(message, code, path = []) {
        return new ParseError([new ParseIssue(message, code, path)]);
    }
}
class ParseIssue {
    constructor(message, code, path = []) {
        this.message = message;
        this.code = code;
        this.path = path;
    }
}
export class SchemaDeserializer {
    constructor(serializedSharedSchemas = {}) {
        this.serializedSharedSchemas = serializedSharedSchemas;
        this.sharedSchemas = {};
    }
    deserialize(serialized) {
        if ('_ref' in serialized) {
            const ref = serialized._ref;
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
                const fieldSchemas = {};
                for (const [field, fieldSchema] of Object.entries(serialized.fieldSchemas)) {
                    fieldSchemas[field] = this.deserialize(fieldSchema);
                }
                return new StructSchema(fieldSchemas, serialized.requiredFields);
            case 'record':
                return new RecordSchema(this.deserialize(serialized.keySchema), this.deserialize(serialized.itemSchema), (typeof serialized.defaultValue !== 'undefined')
                    ? some(serialized.defaultValue)
                    : none);
            case 'color':
                return new ColorSchema();
            case 'array':
                return new ArraySchema(this.deserialize(serialized.itemSchema));
            default:
                throw new Error(`Unknown schema type: ${serialized.type}`);
        }
    }
}
const builtInStringChecks = {
    'minLength': (value, minLength) => {
        if (typeof value === 'string') {
            return (value.length >= minLength)
                ? Either.right(value)
                : Either.left(new ParseIssue(`String is shorter than ${minLength} characters.`, 'minLength'));
        }
        return Either.left(new ParseIssue('Value is not a string.', 'type'));
    },
    'maxLength': (value, maxLength) => {
        if (typeof value === 'string') {
            return (value.length <= maxLength)
                ? Either.right(value)
                : Either.left(new ParseIssue(`String is longer than ${maxLength} characters.`, 'maxLength'));
        }
        return Either.left(new ParseIssue('Value is not a string.', 'type'));
    },
    'regex': (value, pattern) => {
        if (typeof value === 'string') {
            const regex = new RegExp(pattern);
            return regex.test(value)
                ? Either.right(value)
                : Either.left(new ParseIssue(`String does not match the pattern ${pattern}.`, 'regex'));
        }
        return Either.left(new ParseIssue('Value is not a string.', 'type'));
    },
    'trim': (value) => {
        if (typeof value === 'string') {
            return Either.right(value.trim());
        }
        return Either.left(new ParseIssue('Value is not a string.', 'type'));
    }
};
const builtinNumberChecks = {
    'min': (value, minValue) => {
        if (typeof value === 'number') {
            return (value >= minValue)
                ? Either.right(value)
                : Either.left(new ParseIssue(`Number is less than ${minValue}.`, 'min'));
        }
        return Either.left(new ParseIssue('Value is not a number.', 'type'));
    },
    'max': (value, maxValue) => {
        if (typeof value === 'number') {
            return (value <= maxValue)
                ? Either.right(value)
                : Either.left(new ParseIssue(`Number is greater than ${maxValue}.`, 'max'));
        }
        return Either.left(new ParseIssue('Value is not a number.', 'type'));
    }
};
//# sourceMappingURL=schemas.js.map