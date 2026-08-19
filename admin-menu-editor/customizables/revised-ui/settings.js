'use strict';
import { ArraySchema, RecordSchema, StructSchema, SchemaDeserializer } from './schemas.js';
var none = AmeMiniFunc.none;
var some = AmeMiniFunc.some;
export class Setting {
    constructor(schema, hasCustomValue = true) {
        /**
         * Write-once property to store the key of this setting in its parent struct or record.
         * Only for internal use; the setting itself should not touch this property directly.
         */
        this.keyInParent = null;
        /**
         * Whether this setting exists in its parent struct or record.
         */
        this.exists = ko.observable(true);
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
        });
    }
    getSchema() {
        return this.schema;
    }
    isWritable() {
        return true;
    }
    isKeyed() {
        return false;
    }
    isIterable() {
        return false;
    }
    /**
     * Serialize the value of this setting to a plain JS value for storage or transmission.
     *
     * Unlike `value()`, this method may intentionally drop empty optional fields or otherwise
     * transform the value to a more compact representation (depending on the schema).
     */
    serializeValue() {
        return this.value();
    }
    setKeyInParent(key) {
        if (this.keyInParent !== null) {
            throw new Error(`keyInParent is already set to "${this.keyInParent}". It cannot be changed.`);
        }
        this.keyInParent = key;
    }
    getKeyInParent() {
        return this.keyInParent;
    }
}
class SingularSetting extends Setting {
    constructor(schema, value, hasCustomValue = true) {
        super(schema, hasCustomValue);
        this.lastGoodValue = value;
        this.rawValue = ko.observable(value);
        const validationResult = ko.pureComputed(() => this.schema.safeParse(this.rawValue()));
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
    updateWithoutValidation(newValue) {
        this.lastGoodValue = newValue;
        this.rawValue(newValue);
    }
}
export class StructSetting extends Setting {
    constructor(schema, value, hasCustomValue = true) {
        super(schema, hasCustomValue);
        this.fields = {};
        const items = [];
        for (const [property, propertySchema] of Object.entries(schema.fieldSchemas)) {
            let propertyValue, hasCustomValueForProperty = false, fieldExists = false;
            if ((value !== null) && value.hasOwnProperty(property)) {
                fieldExists = true;
                propertyValue = value[property];
                hasCustomValueForProperty = hasCustomValue; //True only if the struct itself has a custom value.
            }
            else {
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
                const result = {};
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
                    }
                    else {
                        setting.exists(false);
                    }
                }
            }
        }).extend({ deferred: true });
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
                    return childError.copyWithPrependedPath([setting.getKeyInParent()]);
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
    addSetting(property, setting) {
        if (this.fields.hasOwnProperty(property)) {
            throw new Error(`Field "${property}" already exists in StructSetting.`);
        }
        this.fields[property] = setting;
        this.schema.fieldSchemas[property] = setting.getSchema();
        setting.setKeyInParent(property);
        setting.exists(true);
        this.items.push(setting);
    }
    removeSetting(property) {
        if (!this.fields.hasOwnProperty(property)) {
            throw new Error(`Field "${property}" does not exist in StructSetting.`);
        }
        const setting = this.fields[property];
        delete this.fields[property];
        delete this.schema.fieldSchemas[property];
        this.items.remove(setting);
        setting.exists(false);
    }
    getChildByPath(path) {
        const segments = path.split('.');
        let current = this;
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
    getChild(property) {
        if (!this.fields.hasOwnProperty(property)) {
            return none;
        }
        if (!this.fields[property].exists()) {
            return none;
        }
        return some(this.fields[property]);
    }
    isKeyed() {
        return true;
    }
    isIterable() {
        return true;
    }
    updateWithoutValidation(newValue) {
        for (const property of Object.keys(this.schema.fieldSchemas)) {
            const setting = this.fields[property];
            if (newValue.hasOwnProperty(property)) {
                setting.exists(true);
                setting.updateWithoutValidation(newValue[property]);
            }
            else {
                setting.exists(false);
            }
        }
    }
    serializeValue() {
        const result = {};
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
                if (!this.schema.isFieldRequired(property)
                    && childSchema.isEmptyValue(childValue)
                    && !childSchema.hasNonEmptyDefaultValue()) {
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
export class RecordSetting extends Setting {
    constructor(schema, value, hasCustomValue = true) {
        super(schema, hasCustomValue);
        this.fields = {};
        const items = [];
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
                const result = {};
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
                const newItems = [];
                for (const [property, propertyValue] of Object.entries(newValue)) {
                    const existing = this.fields[property];
                    if (existing) {
                        existing.value(propertyValue);
                        newItems.push(existing);
                    }
                    else {
                        const newSetting = createSettingFromSchema(this.schema.valueSchema, propertyValue, hasCustomValue);
                        newSetting.setKeyInParent(property);
                        newItems.push(newSetting);
                    }
                }
                this.fields = Object.fromEntries(newItems.map(s => [s.getKeyInParent(), s]));
                this.items(newItems);
            }
        }).extend({ deferred: true }); //Defer to unnecessary re-evaluations when multiple fields are updated at once.
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
    addItem(property, value) {
        if (this.fields.hasOwnProperty(property)) {
            throw new Error(`Field "${property}" already exists in RecordSetting.`);
        }
        const newSetting = createSettingFromSchema(this.schema.valueSchema, value, this.hasCustomValue);
        newSetting.setKeyInParent(property);
        this.fields[property] = newSetting;
        this.items.push(newSetting);
    }
    removeItem(item) {
        const key = item.getKeyInParent();
        if (key === null) {
            throw new Error('RecordSetting: setting has no keyInParent set. This should not happen.');
        }
        delete this.fields[key];
        this.items.remove(item);
    }
    getChild(property) {
        if (!this.fields.hasOwnProperty(property)) {
            return none;
        }
        return some(this.fields[property]);
    }
    isKeyed() {
        return true;
    }
    isIterable() {
        return true;
    }
    updateWithoutValidation(newValue) {
        const newItems = [];
        for (const [property, propertyValue] of Object.entries(newValue)) {
            const existing = this.fields[property];
            if (existing) {
                existing.updateWithoutValidation(propertyValue);
                newItems.push(existing);
            }
            else {
                const newSetting = createSettingFromSchema(this.schema.valueSchema, propertyValue, this.hasCustomValue);
                newSetting.setKeyInParent(property);
                newItems.push(newSetting);
            }
        }
        this.fields = Object.fromEntries(newItems.map(s => [s.getKeyInParent(), s]));
        this.items(newItems);
    }
}
export class ArraySetting extends Setting {
    constructor(schema, value, hasCustomValue = true) {
        super(schema, hasCustomValue);
        this.items = ko.observableArray(value.map(item => createSettingFromSchema(schema.itemSchema, item, hasCustomValue)));
        this.value = ko.pureComputed({
            read: () => {
                return this.items().map(item => item.value());
            },
            write: (newValue) => {
                if (!Array.isArray(newValue)) {
                    throw new Error(`Expected array, got ${typeof newValue}`);
                }
                this.items(newValue.map(item => createSettingFromSchema(schema.itemSchema, item, hasCustomValue)));
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
    isIterable() {
        return true;
    }
    addItem(value) {
        const newItem = createSettingFromSchema(this.schema.itemSchema, value, this.hasCustomValue);
        this.items.push(newItem);
    }
    removeItem(item) {
        this.items.remove(item);
    }
    updateWithoutValidation(newValue) {
        if (!Array.isArray(newValue)) {
            throw new Error(`Expected array, got ${typeof newValue}`);
        }
        this.items(newValue.map(item => createSettingFromSchema(this.schema.itemSchema, item, this.hasCustomValue)));
    }
}
export function createSettingFromSchema(schema, value, hasCustomValue = true) {
    if (typeof value === 'undefined') {
        value = null;
        hasCustomValue = false;
    }
    if (schema instanceof StructSchema) {
        if ((typeof value !== 'object') && (value !== null)) {
            throw new Error(`Expected object or null for StructSchema, got ${typeof value}`);
        }
        return new StructSetting(schema, value, hasCustomValue);
    }
    else if (schema instanceof RecordSchema) {
        if ((typeof value !== 'object') && (value !== null)) {
            throw new Error(`Expected object or null for RecordSchema, got ${typeof value}`);
        }
        return new RecordSetting(schema, value, hasCustomValue);
    }
    else if (schema instanceof ArraySchema) {
        if (!Array.isArray(value)) {
            throw new Error(`Expected array for ArraySchema, got ${typeof value}`);
        }
        return new ArraySetting(schema, value, hasCustomValue);
    }
    else {
        return new SingularSetting(schema, value, hasCustomValue);
    }
}
export function deserializeSettingsPackToStruct(serializedPack) {
    const schemaDeserializer = new SchemaDeserializer(serializedPack.sharedSchemas);
    const structSetting = new SparseStructSetting();
    for (const [path, serializedSetting] of Object.entries(serializedPack.settings)) {
        const schema = schemaDeserializer.deserialize(serializedSetting.schema);
        const value = serializedSetting.value;
        const hasCustomValue = typeof value !== 'undefined';
        const segments = path.split('.');
        const finalSegment = segments.pop();
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
function ensureStructPath(struct, segments) {
    let current = struct;
    for (const [i, segment] of segments.entries()) {
        if (!current.isKeyed()) {
            throw new Error(`Cannot traverse into non-traversable setting at "${segments.slice(0, i).join('.')}"`);
        }
        let child = current.getChild(segment);
        if (child.isDefined()) {
            current = child.get();
        }
        else {
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
//# sourceMappingURL=settings.js.map