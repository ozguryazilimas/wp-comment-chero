import { Control } from '../../controls.js';
export class AmeRevToggleCheckbox extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.onValue = (typeof params.onValue !== 'undefined') ? params.onValue : true;
        this.offValue = (typeof params.offValue !== 'undefined') ? params.offValue : false;
        this.isChecked = ko.pureComputed({
            read: () => {
                return this.mainBindingValue() === ko.unwrap(this.onValue);
            },
            write: (newValue) => {
                this.mainBindingValue(ko.unwrap(newValue ? this.onValue : this.offValue));
            },
            deferEvaluation: true
        });
        this.elementClasses.unshift('ame-toggle-checkbox-control', 'ame-rev-toggle-checkbox');
    }
}
AmeRevToggleCheckbox.componentName = 'ame-rev-toggle-checkbox';
AmeRevToggleCheckbox.template = `
		<label data-bind="class: classString">
			<input type="checkbox" data-bind="checked: isChecked, attr: inputAttributesMap, 
				class: inputClassString, enable: isEnabled">
			<span data-bind="text: label"></span>
			${Control.nestedDescriptionTemplate}
		</label>
		${Control.childrenContainerTemplate}	
	`;
//# sourceMappingURL=ame-rev-toggle-checkbox.js.map