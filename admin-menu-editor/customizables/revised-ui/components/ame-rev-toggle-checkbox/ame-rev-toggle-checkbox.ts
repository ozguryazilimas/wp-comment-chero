import {ComponentInitParams, Control} from '../../controls.js';

export class AmeRevToggleCheckbox extends Control {
	protected readonly onValue: any;
	protected readonly offValue: any;
	public readonly isChecked: KnockoutObservable<boolean>;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		this.onValue = (typeof params.onValue !== 'undefined') ? params.onValue : true;
		this.offValue = (typeof params.offValue !== 'undefined') ? params.offValue : false;

		this.isChecked = ko.pureComputed({
			read: () => {
				return this.mainBindingValue() === ko.unwrap(this.onValue);
			},
			write: (newValue) => {
				this.mainBindingValue(
					ko.unwrap(newValue ? this.onValue : this.offValue)
				);
			},
			deferEvaluation: true
		});

		this.elementClasses.unshift('ame-toggle-checkbox-control', 'ame-rev-toggle-checkbox');
	}

	static componentName = 'ame-rev-toggle-checkbox';
	static template = `
		<label data-bind="class: classString">
			<input type="checkbox" data-bind="checked: isChecked, attr: inputAttributesMap, 
				class: inputClassString, enable: isEnabled">
			<span data-bind="text: label"></span>
			${Control.nestedDescriptionTemplate}
		</label>
		${Control.childrenContainerTemplate}	
	`;
}
