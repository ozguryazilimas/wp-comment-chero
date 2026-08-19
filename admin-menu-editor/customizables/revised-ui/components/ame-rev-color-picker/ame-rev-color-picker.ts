import {ComponentInitParams, Control} from '../../controls.js';

export class AmeRevColorPicker extends Control {
	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.inputClasses.push('ame-color-picker', 'ame-color-picker-component');
	}

	//Note: The original version of this component had the input start out as hidden
	//and made it visible in koDescendantsComplete(). Unfortunately, I didn't leave
	//a comment or commit message explaining why it was implemented like that. If we
	//run into issues with this version, try that approach again. It may have been
	//necessary to work around some kind of color picker initialization issue.

	static componentName = 'ame-rev-color-picker';
	static template = `
		<input type="text" data-bind="ameColorPicker: mainBindingValue, 
			class: classString, attr: inputAttributesMap">`
}