import {ServiceRegistry} from './registry.js';
import {
	Context, looksLikeSerializedExpression, SerializedBindingRef,
	SerializedExpression,
	unserializeExpression,
	unserializeMinimalBinding, WritableBinding
} from '../../shared-dsl/client/wire-dsl.js';


//region Elements
export abstract class UiElement {
	static template: string;
	static componentName: string;

	protected readonly registry: ServiceRegistry<any>;
	protected readonly customizer: ComponentParamCustomizer | null = null;

	readonly slots: Record<string, KnockoutObservableArray<AnySerializedElement>> = {};

	public readonly id: string = '';
	/**
	 * The HTML ID attribute of the root element of this component.
	 *
	 * Note: This can be different from the "id" parameter passed to the component.
	 * Empty by default, but can be set by subclasses. That's also why it's not readonly.
	 */
	public elementId: string = '';

	protected readonly elementClasses: string[] = [];
	public readonly description: KnockoutObservable<string>;
	public tooltip: TooltipData | null = null;

	protected readonly context: Context;

	/**
	 * Whether the component is bound to a comment node instead of a real DOM element.
	 */
	protected readonly isBoundToComment: boolean;

	protected constructor(params: ComponentInitParams, protected readonly $element: JQuery) {
		this.registry = params.registry;
		this.isBoundToComment = ($element[0]) && ($element[0].nodeType === Node.COMMENT_NODE);

		if (typeof params.slots === 'object' && (params.slots !== null)) {
			for (const [slotName, slotChildren] of Object.entries(params.slots)) {
				if (Array.isArray(slotChildren)) {
					this.slots[slotName] = ko.observableArray(slotChildren);
				}
			}
			//Always initialize the default slot, even if no children were provided for it.
			//This is to simplify template code.
			if (!this.slots.hasOwnProperty('default')) {
				this.slots['default'] = ko.observableArray([] as AnySerializedElement[]);
			}
		}

		if ((typeof params.context === 'object') && (params.context !== null)) {
			this.context = params.context as Context;
		} else {
			this.context = new Context();
		}

		if ((typeof params.customizer === 'function')) {
			this.customizer = params.customizer as ComponentParamCustomizer;
		}

		if (typeof params.classes === 'object' && Array.isArray(params.classes)) {
			this.elementClasses.push(...params.classes);
		}
		if (typeof params.id === 'string') {
			this.id = params.id;
		}
		this.tooltip = TooltipData.fromComponentParams(params);

		this.description = paramParsers.string(params, 'description', '', this.context);
	}

	protected getEffectiveElementClasses(): string[] {
		return this.elementClasses;
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	get classString(): string {
		return this.getEffectiveElementClasses().join(' ');
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	get elementAttributesMap(): Record<string, string> {
		const attributes: Record<string, string> = {};
		if (this.elementId !== '') {
			attributes['id'] = this.elementId;
		}
		return attributes;
	}

	public dispose(): void {
		//Override in subclasses to clean up event handlers, subscriptions, etc.
	}

	protected chooseChildComponent(childParams: ComponentInitParams): string {
		if (childParams.component && (ko.components.isRegistered(childParams.component))) {
			return childParams.component;
		}
		switch (childParams.t) {
			case 'control':
				return 'ame-rev-debug-control';
			case 'container':
				if (childParams.role === 'group') {
					return 'ame-rev-control-group';
				}
				return 'ame-rev-debug-container';
			case 'foreach':
				return 'ame-foreach';
			case 'with':
				return 'ame-rev-with';
			default:
				return 'ame-rev-debug-control';
		}
	}

	protected generateChildParams(
		childParams: AnySerializedElement,
		dataItem?: WritableBinding | null
	): ComponentInitParams {
		const childDataItem = dataItem || this.getDefaultDataItemForChildren();
		const params: ComponentInitParams = {
			...childParams,
			registry: this.registry,
			customizer: this.customizer,
			context: this.context.createChildContext(childDataItem),
		};

		if (this.customizer) {
			return this.customizer(params);
		}
		return params;
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	generateChildComponent(childParams: AnySerializedElement, dataItem?: WritableBinding | null): {
		name: string,
		params: ComponentInitParams
	} {
		const params = this.generateChildParams(childParams, dataItem);
		const name = this.chooseChildComponent(params);
		if (!name) {
			throw new Error(`Could not determine component name for child params: ${JSON.stringify(params)}`);
		}
		return {name, params};
	}

	protected getDefaultDataItemForChildren(): WritableBinding | null {
		//By default, children inherit the same data item as their parent. This helps ensure that
		//you can nest controls inside containers and still use $item bindings in those controls.
		//
		//Loops should provide the current item to their children directly.
		const currentItem = this.context.currentItem;
		if (currentItem && currentItem.isWritable()) {
			return currentItem;
		}
		return null;
	}

	protected findChild(selector: string, allowSiblingSearch: boolean | null = null): JQuery {
		if (allowSiblingSearch === null) {
			//Enable only if the component is bound to a comment (i.e. "<!-- ko component: ... -->").
			allowSiblingSearch = this.isBoundToComment;
		}

		if (this.isBoundToComment) {
			if (allowSiblingSearch) {
				return this.$element.nextAll(selector).first();
			} else {
				//We would never find anything because a comment node has no children.
				return jQuery();
			}
		}
		return this.$element.find(selector);
	}

	slotHasContent(slotName: string = 'default'): boolean {
		if (!this.slots.hasOwnProperty(slotName)) {
			return false;
		}
		const items = this.slots[slotName]();
		return (items.length > 0);
	}

	static slotContentTemplate(slotName: string = 'default'): string {
		return `
			<!-- ko if: ($component.slotHasContent('${slotName}')) -->
				<!-- ko foreach: {data: $component.slots['${slotName}']} -->
					<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
				<!-- /ko -->
			<!-- /ko -->`;
	}

	static readonly childrenLoopTemplate = `
		<!-- ko foreach: {data: $component.slots['default']} -->
			<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
		<!-- /ko -->`;

	// noinspection JSUnusedGlobalSymbols -- Used in the tooltip trigger template below.
	generateTooltipParams(extraParams: Record<string, unknown> = {}): ComponentInitParams {
		if (!this.tooltip) {
			throw new Error('Cannot generate tooltip params because this component has no tooltip.');
		}
		return {
			t: 'control',
			registry: this.registry,
			context: this.context,
			tooltip: this.tooltip,
			...extraParams
		};
	}

	static readonly tooltipTriggerTemplate = `
		<!-- ko if: tooltip -->
			<!-- ko component: {name: 'ame-rev-tooltip-trigger', params: $component.generateTooltipParams()} --><!-- /ko -->
		<!-- /ko -->`;
}

export abstract class Control extends UiElement {
	public readonly bindings: Record<string, WritableBinding> = {};
	public readonly mainBindingValue: KnockoutObservable<unknown | null>;

	public readonly label: KnockoutObservable<string>;
	public readonly isEnabled: KnockoutObservable<boolean>;

	protected inputClasses: string[] = [];
	protected readonly inputAttributes: Record<string, string> = {};

	protected constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		if ((typeof params.bindings === 'object') && (params.bindings !== null)) {
			for (const [bindingName, bindingValue] of Object.entries(params.bindings)) {
				const ref = unserializeMinimalBinding(bindingValue);
				const resolved = this.context.resolve(ref);
				if (!resolved) {
					throw new Error(`Control: Could not resolve binding "${bindingName}" with value ${JSON.stringify(bindingValue)}.`);
				}
				if (!resolved.isWritable()) {
					throw new Error(`Control: Binding "${bindingName}" resolved to a non-writable binding.`);
				}
				this.bindings[bindingName] = resolved;
			}
		}

		if (this.bindings.value) {
			this.mainBindingValue = this.bindings.value.value;
		} else {
			this.mainBindingValue = ko.observable(null);
		}

		this.label = paramParsers.string(params, 'label', '', this.context);
		this.isEnabled = paramParsers.boolean(params, 'enabled', true, this.context);

		if (typeof params.inputClasses === 'object' && Array.isArray(params.inputClasses)) {
			this.inputClasses.push(...params.inputClasses);
		}

		if ((typeof params.inputAttributes === 'object') && (params.inputAttributes !== null)) {
			for (const [attrName, attrValue] of Object.entries(params.inputAttributes)) {
				this.inputAttributes[attrName] = attrValue + '';
			}
		}
	}

	protected getEffectiveInputClasses(): string[] {
		return this.inputClasses;
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	get inputClassString(): string {
		return this.getEffectiveInputClasses().join(' ');
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	get inputAttributesMap(): Record<string, string> {
		return this.inputAttributes;
	}

	static siblingDescriptionTemplate = `
		<!-- ko if: (description) -->
			<!-- ko component: {name: 'ame-rev-sibling-description', params: generateDescriptionParams()} --><!-- /ko -->
		<!-- /ko -->`;

	static nestedDescriptionTemplate = `
		<!-- ko if: (description) -->
			<!-- ko component: {
				name: 'ame-rev-nested-description', 
				params: generateDescriptionParams({includeLineBreak: false})
			} --><!-- /ko -->
		<!-- /ko -->`;

	// noinspection JSUnusedGlobalSymbols -- Used in the sibling description template above.
	generateDescriptionParams(extraParams: Record<string, unknown> = {}): ComponentInitParams {
		return {
			t: 'control',
			registry: this.registry,
			descriptionHtml: this.description,
			...extraParams
		};
	}

	static childrenContainerTemplate = `
		<!-- ko if: (slots['default'] && (slots['default']().length > 0)) -->
			<div class="ame-general-control-children">
				<!-- ko foreach: {data: $component.slots['default']} -->    
					<div class="ame-control-child">
					<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
					</div>
				<!-- /ko -->
			</div>
		<!-- /ko -->`;
}

export class Container extends UiElement {
	public readonly title: KnockoutObservable<string>;
	protected readonly isTitleDisabled: KnockoutObservable<boolean>;
	protected readonly childrenContainerClasses: KnockoutObservable<string[]>;

	protected constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.title = paramParsers.string(params, 'title', '', this.context);
		this.isTitleDisabled = paramParsers.boolean(params, 'titleDisabled', false, this.context);

		this.childrenContainerClasses = paramParsers.array(
			params, 'childrenContainerClasses', [], this.context
		) as KnockoutObservable<string[]>;
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	get childrenContainerClassString(): string {
		return this.childrenContainerClasses().join(' ');
	}

	// noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
	/**
	 * Should the title of this container be displayed?
	 */
	get isTitleVisible(): boolean {
		const titleValue = this.title();
		return ((typeof titleValue === 'string') && (titleValue.trim() !== '') && !this.isTitleDisabled());
	}
}

//endregion

//region Component parameters
export type ComponentInitParams = AnySerializedElement & {
	registry: ServiceRegistry<any>;
	context?: Context;
	customizer?: ComponentParamCustomizer | null;

	[key: string]: unknown;
}

export type ComponentParamCustomizer = (params: ComponentInitParams) => ComponentInitParams;

interface ParamTypeSpec<T> {
	//typeof result for a valid plain value of this type
	typeName: string;
	//Coerce the result of an evaluated expression to this type
	coerceExpr: (value: unknown) => T;
	//Human-readable name for error messages
	label: string;
}

const paramTypeSpecs = {
	string: {
		typeName: 'string',
		coerceExpr: (v: unknown): string => v + '',
		label: 'string',
	},
	boolean: {
		typeName: 'boolean',
		coerceExpr: (v: unknown): boolean => !!v,
		label: 'boolean',
	},
	number: {
		typeName: 'number',
		coerceExpr: (v: unknown): number => +(v as any),
		label: 'number',
	},
	array: {
		typeName: 'object', // typeof [] === 'object', see isPlainValue below
		coerceExpr: (v: unknown): unknown[] => (Array.isArray(v) ? v : [v]),
		label: 'array',
	},
} as const;

function makeParamParser<T>(spec: ParamTypeSpec<T>, isPlainValue: (v: unknown) => boolean) {
	return (
		params: ComponentInitParams,
		name: string,
		defaultValue: T,
		ctx: Context
	): KnockoutObservable<T> => {
		const value = params[name];

		// Already an observable → reuse it directly.
		if (ko.isObservable(value)) {
			return value as KnockoutObservable<T>;
		}

		if (isPlainValue(value)) {
			return ko.pureComputed(() => params[name] as T);
		}

		if (looksLikeSerializedExpression(value)) {
			const expr = unserializeExpression(value);
			return ko.pureComputed(() => spec.coerceExpr(expr.evaluate(ctx)));
		}

		if (value === undefined) {
			return ko.pureComputed(() => defaultValue);
		}

		throw new Error(
			`Invalid parameter type for "${name}". Expected ${spec.label} or expression, got ${typeof value}.`
		);
	};
}

export const paramParsers = {
	string: makeParamParser(paramTypeSpecs.string, (v) => typeof v === 'string'),
	boolean: makeParamParser(paramTypeSpecs.boolean, (v) => typeof v === 'boolean'),
	number: makeParamParser(paramTypeSpecs.number, (v) => typeof v === 'number'),
	array: makeParamParser(paramTypeSpecs.array, (v) => Array.isArray(v)),
};

//endregion

//region Serialized elements

export type SerializedBindingValue = string | SerializedBindingRef;

export interface SerializedUiElement {
	slots?: Record<string, AnySerializedElement[]>;
	component?: string;
	description?: string | SerializedExpression;

	[key: string]: unknown;
}

export interface SerializedControl extends SerializedUiElement {
	t: 'control';
	bindings?: {
		[childName: string]: SerializedBindingValue;
	};
	label?: string | SerializedExpression;
}

export interface SerializedContainer extends SerializedUiElement {
	t: 'container';
	title?: string | SerializedExpression;
	childrenContainerClasses?: string[];

	//Preferred role for the container.
	role?: 'group' | string;
}

export interface SerializedForEachBlock extends SerializedUiElement {
	t: 'foreach';
	items: SerializedBindingValue;
}

export interface SerializedWithBlock extends SerializedUiElement {
	t: 'with';
	item: SerializedBindingValue;
}

export type AnySerializedElement =
	SerializedControl
	| SerializedContainer
	| SerializedForEachBlock
	| SerializedWithBlock;

//endregion

//region Knockout component registration
interface ComponentViewModelStatic<T extends UiElement = UiElement> {
	template: string;
	componentName: string;

	new(params: ComponentInitParams, $element: JQuery, ...extras: any): T;
}

export function registerKoComponent<T extends UiElement>(
	ctor: ComponentViewModelStatic<T>,
	koInstance?: KnockoutStatic
): void {
	koInstance = koInstance || ko;
	const componentName = ctor.componentName;

	//Catch classes that forget to define a template or component name. We can't enforce
	//this with TypeScript because static properties can't be abstract.
	if (!componentName || componentName.trim() === '') {
		throw new Error(`Cannot register component with empty component name.`);
	}
	if (ctor.template.trim() === '') {
		throw new Error(`Cannot register component ${componentName} with empty template.`);
	}

	koInstance.components.register(componentName, {
		viewModel: {
			createViewModel: function (params: any, componentInfo: KnockoutComponentTypes.ComponentInfo) {
				const $element = jQuery(componentInfo.element);
				return new ctor(params, $element);
			}
		},
		template: ctor.template,
	});
}

//endregion

//region Tooltips
export class TooltipData {
	public readonly htmlContent: string
	public readonly type: 'info' | 'experimental';
	public readonly extraClasses: string[];

	constructor(htmlContent: string, type: 'info' | 'experimental' = 'info', extraClasses: string[] = []) {
		this.htmlContent = htmlContent;
		this.type = type;
		this.extraClasses = extraClasses;
	}

	static fromComponentParams(params: ComponentInitParams): TooltipData | null {
		if (typeof params.tooltip === 'object' && params.tooltip !== null) {
			const tooltipParams = params.tooltip as Record<string, unknown>;

			const htmlContent = (typeof tooltipParams.htmlContent === 'string')
				? tooltipParams.htmlContent
				: '';

			const type = (tooltipParams.type === 'experimental') ? 'experimental' : 'info';

			const extraClasses = (Array.isArray(tooltipParams.extraClasses))
				? tooltipParams.extraClasses.map(String)
				: [];

			return new TooltipData(htmlContent, type, extraClasses);
		}
		return null;
	}
}

//endregion


//region Debug components
class DebugParamsRenderer {
	public readonly html: KnockoutComputed<string>;

	constructor(paramsToShow: ComponentInitParams) {
		const {registry, children, context, ...debugParams} = paramsToShow;
		const output = this.renderJsonTree(ko.toJS(debugParams)); //toJS unwraps nested observables

		//Not live because re-rendering the entire JSON tree on every change is very expensive.
		//Also, since components typically have a reference to the root setting, a change in any
		//setting will trigger a re-render of all debug components, which is not ideal.
		this.html = ko.pureComputed(() => output);
	}

	escapeHtml(s: string): string {
		return s.replace(/[&<>"]/g, c =>
			({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]!));
	}

	renderJsonTree(value: unknown, key: string | null = null, depth = 0): string {
		const label = key !== null
			? `<span class="json-key">${this.escapeHtml(key)}:</span> `
			: '';

		if (value === null || typeof value !== 'object') {
			const text = (value === undefined) ? 'undefined'
				: (typeof value === 'function') ? '[Function]'
					: JSON.stringify(value);
			if (typeof text === 'undefined') {
				console.log('Value is undefined for key:', key, 'value:', value, typeof value);
				throw new Error(`Unexpected undefined text value for key "${key}"`);
			}
			return `<div class="json-leaf">${label}<span class="json-value">${this.escapeHtml(text)}</span></div>`;
		}

		const isArray = Array.isArray(value);
		const entries = Object.entries(value as object);
		const preview = isArray ? `Array(${entries.length})` : `Object {${entries.length}}`;
		// Top level starts open, everything below starts collapsed:
		const open = depth === 0 ? ' open' : '';

		return `<details class="json-node"${open}>
		<summary>${label}<span class="json-preview">${this.escapeHtml(preview)}</span></summary>
		<div class="json-children">
			${entries.map(([k, v]) => this.renderJsonTree(v, k, depth + 1)).join('')}
		</div>
	</details>`;
	}
}

const DEBUG_PARAMS_RENDERER_TEMPLATE = `
	<div class="ame-rev-debug-tree" data-bind="html: debugTree.html"></div>
`;

export class DebugContainer extends Container {
	public readonly debugTree: DebugParamsRenderer;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.debugTree = new DebugParamsRenderer(params);
	}

	static componentName: string = 'ame-rev-debug-container';
	static template: string = `
		<div class="ame-rev-debug-container">
			<h3><span data-bind="text: title"></span>  <span class="ame-rev-debug-component-flag">[Debug Container]</span></h3>
			${DEBUG_PARAMS_RENDERER_TEMPLATE}
			<h4>Container Children</h4>
			<ol data-bind="foreach: slots['default']">
				<li>
					<div data-bind="component: $component.generateChildComponent($data)"></div>
				</li>
			</ol>
		</div>`;
}

export class DebugControl extends Control {
	public readonly debugTree: DebugParamsRenderer;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.debugTree = new DebugParamsRenderer(params);
	}

	static componentName: string = 'ame-rev-debug-control';
	static template: string = `
		<div class="ame-rev-debug-control">
			<h3><span data-bind="text: label"></span> <span class="ame-rev-debug-component-flag">[Debug Control]</span></h3>
			${DEBUG_PARAMS_RENDERER_TEMPLATE}
			<!-- ko if: slots['default'] && (slots['default']().length > 0) -->
			<h4>Control Children</h4>
			<ol data-bind="foreach: slots['default']">
				<li>
					<div data-bind="component: $component.generateChildComponent($data)"></div>
				</li>
			</ol>
			<!-- /ko -->
		</div>`;
}

//endregion