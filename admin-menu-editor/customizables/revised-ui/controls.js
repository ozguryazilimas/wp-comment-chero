import { Context, looksLikeSerializedExpression, unserializeExpression, unserializeMinimalBinding } from '../../shared-dsl/client/wire-dsl.js';
//region Elements
export class UiElement {
    constructor(params, $element) {
        this.$element = $element;
        this.customizer = null;
        this.slots = {};
        this.id = '';
        /**
         * The HTML ID attribute of the root element of this component.
         *
         * Note: This can be different from the "id" parameter passed to the component.
         * Empty by default, but can be set by subclasses. That's also why it's not readonly.
         */
        this.elementId = '';
        this.elementClasses = [];
        this.tooltip = null;
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
                this.slots['default'] = ko.observableArray([]);
            }
        }
        if ((typeof params.context === 'object') && (params.context !== null)) {
            this.context = params.context;
        }
        else {
            this.context = new Context();
        }
        if ((typeof params.customizer === 'function')) {
            this.customizer = params.customizer;
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
    getEffectiveElementClasses() {
        return this.elementClasses;
    }
    // noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
    get classString() {
        return this.getEffectiveElementClasses().join(' ');
    }
    // noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
    get elementAttributesMap() {
        const attributes = {};
        if (this.elementId !== '') {
            attributes['id'] = this.elementId;
        }
        return attributes;
    }
    dispose() {
        //Override in subclasses to clean up event handlers, subscriptions, etc.
    }
    chooseChildComponent(childParams) {
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
    generateChildParams(childParams, dataItem) {
        const childDataItem = dataItem || this.getDefaultDataItemForChildren();
        const params = {
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
    generateChildComponent(childParams, dataItem) {
        const params = this.generateChildParams(childParams, dataItem);
        const name = this.chooseChildComponent(params);
        if (!name) {
            throw new Error(`Could not determine component name for child params: ${JSON.stringify(params)}`);
        }
        return { name, params };
    }
    getDefaultDataItemForChildren() {
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
    findChild(selector, allowSiblingSearch = null) {
        if (allowSiblingSearch === null) {
            //Enable only if the component is bound to a comment (i.e. "<!-- ko component: ... -->").
            allowSiblingSearch = this.isBoundToComment;
        }
        if (this.isBoundToComment) {
            if (allowSiblingSearch) {
                return this.$element.nextAll(selector).first();
            }
            else {
                //We would never find anything because a comment node has no children.
                return jQuery();
            }
        }
        return this.$element.find(selector);
    }
    slotHasContent(slotName = 'default') {
        if (!this.slots.hasOwnProperty(slotName)) {
            return false;
        }
        const items = this.slots[slotName]();
        return (items.length > 0);
    }
    static slotContentTemplate(slotName = 'default') {
        return `
			<!-- ko if: ($component.slotHasContent('${slotName}')) -->
				<!-- ko foreach: {data: $component.slots['${slotName}']} -->
					<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
				<!-- /ko -->
			<!-- /ko -->`;
    }
    // noinspection JSUnusedGlobalSymbols -- Used in the tooltip trigger template below.
    generateTooltipParams(extraParams = {}) {
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
}
UiElement.childrenLoopTemplate = `
		<!-- ko foreach: {data: $component.slots['default']} -->
			<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
		<!-- /ko -->`;
UiElement.tooltipTriggerTemplate = `
		<!-- ko if: tooltip -->
			<!-- ko component: {name: 'ame-rev-tooltip-trigger', params: $component.generateTooltipParams()} --><!-- /ko -->
		<!-- /ko -->`;
export class Control extends UiElement {
    constructor(params, $element) {
        super(params, $element);
        this.bindings = {};
        this.inputClasses = [];
        this.inputAttributes = {};
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
        }
        else {
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
    getEffectiveInputClasses() {
        return this.inputClasses;
    }
    // noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
    get inputClassString() {
        return this.getEffectiveInputClasses().join(' ');
    }
    // noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
    get inputAttributesMap() {
        return this.inputAttributes;
    }
    // noinspection JSUnusedGlobalSymbols -- Used in the sibling description template above.
    generateDescriptionParams(extraParams = {}) {
        return {
            t: 'control',
            registry: this.registry,
            descriptionHtml: this.description,
            ...extraParams
        };
    }
}
Control.siblingDescriptionTemplate = `
		<!-- ko if: (description) -->
			<!-- ko component: {name: 'ame-rev-sibling-description', params: generateDescriptionParams()} --><!-- /ko -->
		<!-- /ko -->`;
Control.nestedDescriptionTemplate = `
		<!-- ko if: (description) -->
			<!-- ko component: {
				name: 'ame-rev-nested-description', 
				params: generateDescriptionParams({includeLineBreak: false})
			} --><!-- /ko -->
		<!-- /ko -->`;
Control.childrenContainerTemplate = `
		<!-- ko if: (slots['default'] && (slots['default']().length > 0)) -->
			<div class="ame-general-control-children">
				<!-- ko foreach: {data: $component.slots['default']} -->    
					<div class="ame-control-child">
					<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
					</div>
				<!-- /ko -->
			</div>
		<!-- /ko -->`;
export class Container extends UiElement {
    constructor(params, $element) {
        super(params, $element);
        this.title = paramParsers.string(params, 'title', '', this.context);
        this.isTitleDisabled = paramParsers.boolean(params, 'titleDisabled', false, this.context);
        this.childrenContainerClasses = paramParsers.array(params, 'childrenContainerClasses', [], this.context);
    }
    // noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
    get childrenContainerClassString() {
        return this.childrenContainerClasses().join(' ');
    }
    // noinspection JSUnusedGlobalSymbols -- Used in Knockout templates.
    /**
     * Should the title of this container be displayed?
     */
    get isTitleVisible() {
        const titleValue = this.title();
        return ((typeof titleValue === 'string') && (titleValue.trim() !== '') && !this.isTitleDisabled());
    }
}
const paramTypeSpecs = {
    string: {
        typeName: 'string',
        coerceExpr: (v) => v + '',
        label: 'string',
    },
    boolean: {
        typeName: 'boolean',
        coerceExpr: (v) => !!v,
        label: 'boolean',
    },
    number: {
        typeName: 'number',
        coerceExpr: (v) => +v,
        label: 'number',
    },
    array: {
        typeName: 'object', // typeof [] === 'object', see isPlainValue below
        coerceExpr: (v) => (Array.isArray(v) ? v : [v]),
        label: 'array',
    },
};
function makeParamParser(spec, isPlainValue) {
    return (params, name, defaultValue, ctx) => {
        const value = params[name];
        // Already an observable → reuse it directly.
        if (ko.isObservable(value)) {
            return value;
        }
        if (isPlainValue(value)) {
            return ko.pureComputed(() => params[name]);
        }
        if (looksLikeSerializedExpression(value)) {
            const expr = unserializeExpression(value);
            return ko.pureComputed(() => spec.coerceExpr(expr.evaluate(ctx)));
        }
        if (value === undefined) {
            return ko.pureComputed(() => defaultValue);
        }
        throw new Error(`Invalid parameter type for "${name}". Expected ${spec.label} or expression, got ${typeof value}.`);
    };
}
export const paramParsers = {
    string: makeParamParser(paramTypeSpecs.string, (v) => typeof v === 'string'),
    boolean: makeParamParser(paramTypeSpecs.boolean, (v) => typeof v === 'boolean'),
    number: makeParamParser(paramTypeSpecs.number, (v) => typeof v === 'number'),
    array: makeParamParser(paramTypeSpecs.array, (v) => Array.isArray(v)),
};
export function registerKoComponent(ctor, koInstance) {
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
            createViewModel: function (params, componentInfo) {
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
    constructor(htmlContent, type = 'info', extraClasses = []) {
        this.htmlContent = htmlContent;
        this.type = type;
        this.extraClasses = extraClasses;
    }
    static fromComponentParams(params) {
        if (typeof params.tooltip === 'object' && params.tooltip !== null) {
            const tooltipParams = params.tooltip;
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
    constructor(paramsToShow) {
        const { registry, children, context, ...debugParams } = paramsToShow;
        const output = this.renderJsonTree(ko.toJS(debugParams)); //toJS unwraps nested observables
        //Not live because re-rendering the entire JSON tree on every change is very expensive.
        //Also, since components typically have a reference to the root setting, a change in any
        //setting will trigger a re-render of all debug components, which is not ideal.
        this.html = ko.pureComputed(() => output);
    }
    escapeHtml(s) {
        return s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    }
    renderJsonTree(value, key = null, depth = 0) {
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
        const entries = Object.entries(value);
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
    constructor(params, $element) {
        super(params, $element);
        this.debugTree = new DebugParamsRenderer(params);
    }
}
DebugContainer.componentName = 'ame-rev-debug-container';
DebugContainer.template = `
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
export class DebugControl extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.debugTree = new DebugParamsRenderer(params);
    }
}
DebugControl.componentName = 'ame-rev-debug-control';
DebugControl.template = `
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
//endregion
//# sourceMappingURL=controls.js.map