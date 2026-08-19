import { Control, UiElement } from '../../controls.js';
import { AmeRevChoiceControl } from '../ame-rev-choice-control/ame-rev-choice-control.js';
export class AmeRevSelectBox extends AmeRevChoiceControl {
    constructor(params, $element) {
        super(params, $element);
        this.elementClasses.push('ame-select-box-control', 'ame-rev-select-box');
    }
}
AmeRevSelectBox.componentName = 'ame-rev-select-box';
AmeRevSelectBox.template = `
		<select data-bind="class: classString, value: mainBindingValue, options: options,
			optionsValue: 'value', optionsText: 'label', enable: isEnabled, attr: inputAttributes"></select>
		${UiElement.tooltipTriggerTemplate}
		${Control.siblingDescriptionTemplate}
	`;
//# sourceMappingURL=ame-rev-select-box.js.map