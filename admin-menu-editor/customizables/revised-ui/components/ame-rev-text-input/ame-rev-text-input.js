import { Control } from '../../controls.js';
export class AmeRevTextInput extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.useTextInputBinding = false;
        this.isCode = !!(params.isCode || false);
        this.inputType = (params.inputType || 'text') + '';
        this.useTextInputBinding = !!(params.useTextInputBinding || false);
        this.inputAttributes['type'] = this.inputType;
        const autoInputClasses = [];
        if (!this.inputClasses.includes('large-text')) {
            autoInputClasses.push('regular-text');
        }
        if (this.isCode) {
            autoInputClasses.push('code');
        }
        autoInputClasses.push('ame-text-input-control', 'ame-rev-text-input-control');
        this.inputClasses = [...autoInputClasses, ...this.inputClasses];
    }
}
AmeRevTextInput.template = `
		<!-- ko if: useTextInputBinding -->
			<input data-bind="textInput: mainBindingValue, class: inputClassString, attr: inputAttributesMap">
		<!-- /ko --><!-- ko ifnot: useTextInputBinding -->
			<input data-bind="value: mainBindingValue, class: inputClassString, attr: inputAttributesMap">
		<!-- /ko -->
`;
AmeRevTextInput.componentName = 'ame-rev-text-input';
//# sourceMappingURL=ame-rev-text-input.js.map