import {ComponentInitParams, Control, UiElement} from '../../controls.js';
import {AmeRevChoiceControl} from '../ame-rev-choice-control/ame-rev-choice-control.js';

export class AmeRevSelectBox extends AmeRevChoiceControl {
	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.elementClasses.push('ame-select-box-control', 'ame-rev-select-box');
	}

	static componentName = 'ame-rev-select-box';
	static template = `
		<select data-bind="class: classString, value: mainBindingValue, options: options,
			optionsValue: 'value', optionsText: 'label', enable: isEnabled, attr: inputAttributes"></select>
		${UiElement.tooltipTriggerTemplate}
		${Control.siblingDescriptionTemplate}
	`;
}
