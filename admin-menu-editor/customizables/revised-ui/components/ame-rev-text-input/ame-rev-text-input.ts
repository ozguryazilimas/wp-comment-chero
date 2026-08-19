import {ComponentInitParams, Control} from '../../controls.js';

export class AmeRevTextInput extends Control {
	public readonly isCode: boolean;
	public readonly inputType: string;
	public readonly useTextInputBinding: boolean = false;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.isCode = !!(params.isCode || false);
		this.inputType = (params.inputType || 'text') + '';
		this.useTextInputBinding = !!(params.useTextInputBinding || false);

		this.inputAttributes['type'] = this.inputType;

		const autoInputClasses: string[] = [];
		if (!this.inputClasses.includes('large-text')) {
			autoInputClasses.push('regular-text');
		}
		if (this.isCode) {
			autoInputClasses.push('code');
		}
		autoInputClasses.push('ame-text-input-control', 'ame-rev-text-input-control');

		this.inputClasses = [...autoInputClasses, ...this.inputClasses];
	}

	static template = `
		<!-- ko if: useTextInputBinding -->
			<input data-bind="textInput: mainBindingValue, class: inputClassString, attr: inputAttributesMap">
		<!-- /ko --><!-- ko ifnot: useTextInputBinding -->
			<input data-bind="value: mainBindingValue, class: inputClassString, attr: inputAttributesMap">
		<!-- /ko -->
`;

	static componentName = 'ame-rev-text-input';
}