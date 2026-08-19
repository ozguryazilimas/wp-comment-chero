import Option = AmeMiniFunc.Option;

export class Context {
	/**
	 * A map of resolution strategies keyed by their unique string identifiers.
	 * These are meant to be shared across all contexts since they are stateless.
	 */
	protected readonly resolvers: Map<string, ResolutionStrategy>;
	protected readonly functions: Map<string, DslFunction>;

	constructor(
		public readonly currentItem: ReadableBinding | null = null,
		public readonly parent: Context | null = null,
		protected readonly rootSettingContainer: KeyedBinding | null = null
	) {
		if (parent && !this.rootSettingContainer) {
			this.rootSettingContainer = parent.getRootSettingContainer();
		}

		if (parent) {
			this.resolvers = parent.resolvers;
		} else {
			this.resolvers = new Map<string, ResolutionStrategy>();
			this.resolvers.set('binding', new BindingResolutionStrategy());
		}

		this.functions = new Map<string, DslFunction>();
	}

	public registerFunction(name: string, callback: FunctionCallback): void {
		const dslFunction = new DslFunction(name, callback);
		this.functions.set(name, dslFunction);
	}

	public getFunction(name: string): DslFunction | undefined {
		const func = this.functions.get(name);
		if (func) {
			return func;
		}
		if (this.parent) {
			return this.parent.getFunction(name);
		}
		return undefined;
	}

	resolveValue(ref: Reference): unknown {
		const resolution = this.resolve(ref);
		if (!resolution) {
			throw new Error(`Unable to resolve reference: ${JSON.stringify(ref)}`);
		}
		return resolution.value();
	}

	resolve(ref: Reference): ReferenceResolutionResult {
		const strategyKey = ref.getResolutionStrategyKey();
		const strategy = this.resolvers.get(strategyKey);
		if (!strategy) {
			throw new Error(`No resolution strategy registered for key: ${strategyKey}`);
		}
		return strategy.resolve(ref, this);
	}

	getRootSettingContainer(): KeyedBinding | null {
		return this.rootSettingContainer;
	}

	createChildContext(currentItem?: ReadableBinding | null | undefined): Context {
		if (typeof currentItem === 'undefined') {
			currentItem = this.currentItem;
		}
		return new Context(currentItem, this, this.rootSettingContainer);
	}
}

//region Expressions
export abstract class Expression {
	abstract evaluate(ctx: Context): any;
}

export class Literal extends Expression {
	private readonly $value: any;

	constructor(value: any) {
		super();
		this.$value = value;
	}

	evaluate(_ctx: Context): any {
		return this.$value;
	}
}

class ArrayExpression extends Expression {
	private readonly items: Expression[];

	constructor(items: Expression[]) {
		super();
		this.items = items;
	}

	evaluate(ctx: Context): any[] {
		return this.items.map(item => item.evaluate(ctx));
	}
}

type FunctionCallArgs = any[] | Record<string, any>;

type FunctionCallback = (ctx: Context, args: FunctionCallArgs) => any;

type BoxedFunctionCallArgs = { [key: string]: Expression } | Expression[];

class DslFunction {
	constructor(
		public readonly name: string,
		public readonly callback: FunctionCallback
	) {
	}

	public invoke(ctx: Context, args: FunctionCallArgs): any {
		return this.callback(ctx, args);
	}
}

class FunctionCall extends Expression {
	protected readonly functionName: string;
	protected readonly args: BoxedFunctionCallArgs;

	constructor(functionName: string, args: BoxedFunctionCallArgs) {
		super();
		this.functionName = functionName;
		this.args = args;
	}

	evaluate(ctx: Context): any {
		const evaluatedArgs = this.evaluateArgs(ctx, this.args);
		return this.invokeFunction(ctx, evaluatedArgs);
	}

	protected evaluateArgs(ctx: Context, args: BoxedFunctionCallArgs): FunctionCallArgs {
		if (Array.isArray(args)) {
			return args.map(arg => arg.evaluate(ctx));
		}

		return Object.keys(args).reduce((result, key) => {
			result[key] = args[key].evaluate(ctx);
			return result;
		}, {} as Record<string, any>);
	}

	protected invokeFunction(ctx: Context, args: FunctionCallArgs): any {
		const dslFunction = ctx.getFunction(this.functionName);
		if (!dslFunction) {
			throw new Error(`Function "${this.functionName}" is not registered.`);
		}

		return dslFunction.invoke(ctx, args);
	}
}

abstract class Reference extends Expression {
	abstract getResolutionStrategyKey(): string;

	evaluate(ctx: Context): any {
		return ctx.resolveValue(this);
	}
}

class BindingRef extends Reference {
	constructor(public readonly bindingString: string) {
		super();
	}

	getResolutionStrategyKey(): string {
		return 'binding';
	}
}

class ResolutionOf extends Expression {
	constructor(public readonly ref: BindingRef) {
		super();
	}

	evaluate(ctx: Context): any {
		const resolution = ctx.resolve(this.ref);
		if (!resolution) {
			throw new Error(`Unable to resolve reference: ${JSON.stringify(this.ref)}`);
		}
		return resolution;
	}
}

//endregion

//region Serialization
interface SerializedLiteral {
	t: 'literal';
	value: any;
}

interface SerializedArrayExpression {
	t: 'array';
	items: SerializedExpression[];
}

interface SerializedFunctionCall {
	t: 'funcCall';
	functionName: string;
	args: SerializedExpression[] | Record<string, SerializedExpression>;
}

export interface SerializedBindingRef {
	t: 'binding';
	bind: string;
}

export interface SerializedResolutionOf {
	t: 'resolve';
	ref: SerializedBindingRef;
}

export type SerializedExpression =
	SerializedLiteral
	| SerializedArrayExpression
	| SerializedFunctionCall
	| SerializedBindingRef
	| SerializedResolutionOf;

export function unserializeExpression(serialized: SerializedExpression): Expression {
	switch (serialized.t) {
		case 'literal':
			return new Literal(serialized.value);
		case 'array':
			return new ArrayExpression(serialized.items.map(unserializeExpression));
		case 'funcCall':
			const args = Array.isArray(serialized.args)
				? serialized.args.map(unserializeExpression)
				: Object.fromEntries(
					Object.entries(serialized.args).map(([key, value]) => [key, unserializeExpression(value)])
				);
			return new FunctionCall(serialized.functionName, args);
		case 'binding':
			return new BindingRef(serialized.bind);
		case 'resolve':
			return new ResolutionOf(unserializeMinimalBinding(serialized.ref));
		default:
			throw new Error(`Unknown serialized expression type: ${(serialized as any).type}`);
	}
}

export function unserializeMinimalBinding(binding: string | SerializedExpression): BindingRef {
	if (typeof binding === 'string') {
		binding = {t: 'binding', bind: binding};
	}
	if (!('t' in binding) || (binding.t !== 'binding')) {
		throw new Error(`Invalid binding format: ${JSON.stringify(binding)}`);
	}
	return new BindingRef(binding.bind);
}

export function looksLikeSerializedMinimalBinding(obj: any): obj is SerializedBindingRef | string {
	if (typeof obj === 'string') {
		return true;
	}
	if (typeof obj !== 'object' || obj === null) {
		return false;
	}
	return obj.t === 'binding' && typeof obj.bind === 'string';
}

export function looksLikeSerializedExpression(obj: any): obj is SerializedExpression {
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

//endregion

//region Bindings

export interface ReadableBinding<T = any> {
	readonly value: KnockoutObservable<T | null>;

	isWritable(): this is WritableBinding<T>;

	isKeyed(): this is KeyedBinding;

	isIterable(): this is IterableBinding;
}

export interface WritableBinding<T = any> extends ReadableBinding<T> {
	readonly isValid: KnockoutComputed<boolean>;
	readonly hasValidationErrors: KnockoutObservable<boolean>;
}

export interface KeyedBinding<T = any> extends ReadableBinding<T> {
	getChild(property: string): Option<ReadableBinding>;
}

export interface IterableBinding<I extends WritableBinding = WritableBinding, T = any>
	extends ReadableBinding<T> {
	items(): I[];
}

//endregion

//region Resolvers
type ReferenceResolutionResult = ReadableBinding | null;

abstract class ResolutionStrategy {
	abstract resolve(ref: Reference, ctx: Context): ReferenceResolutionResult;
}

class BindingResolutionStrategy extends ResolutionStrategy {
	static readonly CURRENT_ITEM_KEY = '$item';

	resolve(ref: BindingRef, ctx: Context): ReferenceResolutionResult {
		const path = ref.bindingString.split('.');
		//console.log('Resolving binding ref:', path, 'in context:', ctx);
		return this.resolvePath(path, ctx);
	}

	protected resolvePath(path: string[], ctx: Context): ReferenceResolutionResult {
		let current: ReadableBinding | undefined | null = ctx.getRootSettingContainer();

		for (const segment of path) {
			if (segment === BindingResolutionStrategy.CURRENT_ITEM_KEY) {
				current = ctx.currentItem;
			} else if (current && current.isKeyed()) {
				current = current.getChild(segment).orNull();
			} else {
				current = null;
			}

			if (!current) {
				break;
			}
		}

		return current ? current : null;
	}
}

//endregion
