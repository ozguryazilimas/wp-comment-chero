export class Context {
    constructor(currentItem = null, parent = null, rootSettingContainer = null) {
        this.currentItem = currentItem;
        this.parent = parent;
        this.rootSettingContainer = rootSettingContainer;
        if (parent && !this.rootSettingContainer) {
            this.rootSettingContainer = parent.getRootSettingContainer();
        }
        if (parent) {
            this.resolvers = parent.resolvers;
        }
        else {
            this.resolvers = new Map();
            this.resolvers.set('binding', new BindingResolutionStrategy());
        }
        this.functions = new Map();
    }
    registerFunction(name, callback) {
        const dslFunction = new DslFunction(name, callback);
        this.functions.set(name, dslFunction);
    }
    getFunction(name) {
        const func = this.functions.get(name);
        if (func) {
            return func;
        }
        if (this.parent) {
            return this.parent.getFunction(name);
        }
        return undefined;
    }
    resolveValue(ref) {
        const resolution = this.resolve(ref);
        if (!resolution) {
            throw new Error(`Unable to resolve reference: ${JSON.stringify(ref)}`);
        }
        return resolution.value();
    }
    resolve(ref) {
        const strategyKey = ref.getResolutionStrategyKey();
        const strategy = this.resolvers.get(strategyKey);
        if (!strategy) {
            throw new Error(`No resolution strategy registered for key: ${strategyKey}`);
        }
        return strategy.resolve(ref, this);
    }
    getRootSettingContainer() {
        return this.rootSettingContainer;
    }
    createChildContext(currentItem) {
        if (typeof currentItem === 'undefined') {
            currentItem = this.currentItem;
        }
        return new Context(currentItem, this, this.rootSettingContainer);
    }
}
//region Expressions
export class Expression {
}
export class Literal extends Expression {
    constructor(value) {
        super();
        this.$value = value;
    }
    evaluate(_ctx) {
        return this.$value;
    }
}
class ArrayExpression extends Expression {
    constructor(items) {
        super();
        this.items = items;
    }
    evaluate(ctx) {
        return this.items.map(item => item.evaluate(ctx));
    }
}
class DslFunction {
    constructor(name, callback) {
        this.name = name;
        this.callback = callback;
    }
    invoke(ctx, args) {
        return this.callback(ctx, args);
    }
}
class FunctionCall extends Expression {
    constructor(functionName, args) {
        super();
        this.functionName = functionName;
        this.args = args;
    }
    evaluate(ctx) {
        const evaluatedArgs = this.evaluateArgs(ctx, this.args);
        return this.invokeFunction(ctx, evaluatedArgs);
    }
    evaluateArgs(ctx, args) {
        if (Array.isArray(args)) {
            return args.map(arg => arg.evaluate(ctx));
        }
        return Object.keys(args).reduce((result, key) => {
            result[key] = args[key].evaluate(ctx);
            return result;
        }, {});
    }
    invokeFunction(ctx, args) {
        const dslFunction = ctx.getFunction(this.functionName);
        if (!dslFunction) {
            throw new Error(`Function "${this.functionName}" is not registered.`);
        }
        return dslFunction.invoke(ctx, args);
    }
}
class Reference extends Expression {
    evaluate(ctx) {
        return ctx.resolveValue(this);
    }
}
class BindingRef extends Reference {
    constructor(bindingString) {
        super();
        this.bindingString = bindingString;
    }
    getResolutionStrategyKey() {
        return 'binding';
    }
}
class ResolutionOf extends Expression {
    constructor(ref) {
        super();
        this.ref = ref;
    }
    evaluate(ctx) {
        const resolution = ctx.resolve(this.ref);
        if (!resolution) {
            throw new Error(`Unable to resolve reference: ${JSON.stringify(this.ref)}`);
        }
        return resolution;
    }
}
export function unserializeExpression(serialized) {
    switch (serialized.t) {
        case 'literal':
            return new Literal(serialized.value);
        case 'array':
            return new ArrayExpression(serialized.items.map(unserializeExpression));
        case 'funcCall':
            const args = Array.isArray(serialized.args)
                ? serialized.args.map(unserializeExpression)
                : Object.fromEntries(Object.entries(serialized.args).map(([key, value]) => [key, unserializeExpression(value)]));
            return new FunctionCall(serialized.functionName, args);
        case 'binding':
            return new BindingRef(serialized.bind);
        case 'resolve':
            return new ResolutionOf(unserializeMinimalBinding(serialized.ref));
        default:
            throw new Error(`Unknown serialized expression type: ${serialized.type}`);
    }
}
export function unserializeMinimalBinding(binding) {
    if (typeof binding === 'string') {
        binding = { t: 'binding', bind: binding };
    }
    if (!('t' in binding) || (binding.t !== 'binding')) {
        throw new Error(`Invalid binding format: ${JSON.stringify(binding)}`);
    }
    return new BindingRef(binding.bind);
}
export function looksLikeSerializedMinimalBinding(obj) {
    if (typeof obj === 'string') {
        return true;
    }
    if (typeof obj !== 'object' || obj === null) {
        return false;
    }
    return obj.t === 'binding' && typeof obj.bind === 'string';
}
export function looksLikeSerializedExpression(obj) {
    if (typeof obj !== 'object' || obj === null) {
        return false;
    }
    const type = obj.t;
    if (typeof type !== 'string') {
        return false;
    }
    switch (type) {
        case 'literal':
            return 'value' in obj;
        case 'array':
            return Array.isArray(obj.items);
        case 'funcCall':
            return typeof obj.functionName === 'string' && ('args' in obj);
        case 'binding':
            return typeof obj.bind === 'string';
        case 'resolve':
            return ('ref' in obj) && looksLikeSerializedMinimalBinding(obj.ref);
        default:
            return false;
    }
}
class ResolutionStrategy {
}
class BindingResolutionStrategy extends ResolutionStrategy {
    resolve(ref, ctx) {
        const path = ref.bindingString.split('.');
        //console.log('Resolving binding ref:', path, 'in context:', ctx);
        return this.resolvePath(path, ctx);
    }
    resolvePath(path, ctx) {
        let current = ctx.getRootSettingContainer();
        for (const segment of path) {
            if (segment === BindingResolutionStrategy.CURRENT_ITEM_KEY) {
                current = ctx.currentItem;
            }
            else if (current && current.isKeyed()) {
                current = current.getChild(segment).orNull();
            }
            else {
                current = null;
            }
            if (!current) {
                break;
            }
        }
        return current ? current : null;
    }
}
BindingResolutionStrategy.CURRENT_ITEM_KEY = '$item';
//endregion
//# sourceMappingURL=wire-dsl.js.map