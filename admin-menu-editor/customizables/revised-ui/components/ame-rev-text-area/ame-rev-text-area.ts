import {ComponentInitParams, Control, paramParsers} from '../../controls.js';

export class AmeRevTextArea extends Control {
	public readonly rows: KnockoutObservable<number>;
	public readonly cols: KnockoutObservable<number>;
	protected readonly placeholder: KnockoutObservable<string>;
	public readonly useTextInputBinding: KnockoutObservable<boolean>;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.elementClasses.push('large-text', 'ame-rev-text-area');

		this.rows = paramParsers.number(params, 'rows', 0, this.context);
		this.cols = paramParsers.number(params, 'cols', 0, this.context);
		this.placeholder = paramParsers.string(params, 'placeholder', '', this.context);
		this.useTextInputBinding = paramParsers.boolean(params, 'useTextInputBinding', false, this.context);
	}

	protected getEffectiveInputClasses(): string[] {
		const classes = super.getEffectiveInputClasses();
		//Since this control has no wrapper element, input classes also include the overall element classes.
		classes.push(...this.elementClasses);
		return classes;
	}

	get inputAttributesMap(): Record<string, string> {
		const attributes = super.inputAttributesMap;

		const rows = this.rows();
		const cols = this.cols();
		if (rows > 0) {
			attributes['rows'] = rows.toString();
		}
		if (cols > 0) {
			attributes['cols'] = cols.toString();
		}
		const placeholder = this.placeholder();
		if (placeholder !== '') {
			attributes['placeholder'] = placeholder;
		}

		return attributes;
	}

	static componentName = 'ame-rev-text-area';
	static template = `
		<!-- ko if: useTextInputBinding -->
			<textarea data-bind="textInput: mainBindingValue, attr: inputAttributesMap, class: inputClassString"></textarea>
		<!-- /ko --><!-- ko ifnot: useTextInputBinding -->
			<textarea data-bind="value: mainBindingValue, attr: inputAttributesMap, class: inputClassString"></textarea>
		<!-- /ko -->
		${Control.siblingDescriptionTemplate}
	`;
}
