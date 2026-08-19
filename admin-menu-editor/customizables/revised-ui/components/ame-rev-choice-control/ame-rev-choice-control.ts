import {ComponentInitParams, Control} from '../../controls.js';

export interface RevChoiceOptionData {
	value: any;
	label: string;
	description?: string;
	enabled?: boolean;
	icon?: string;
}

export class RevChoiceControlOption {
	public readonly value: any;
	public readonly label: string;
	public readonly description: string;
	public readonly enabled: boolean;
	public readonly icon: string;

	constructor(data: RevChoiceOptionData) {
		this.value = data.value;
		this.label = data.label;
		this.description = data.description || '';
		this.enabled = (typeof data.enabled === 'undefined') || data.enabled;
		this.icon = data.icon || '';
	}
}

export abstract class AmeRevChoiceControl extends Control {
	public readonly options: KnockoutObservableArray<RevChoiceControlOption>;

	protected constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		this.options = ko.observableArray<RevChoiceControlOption>([]);
		if ((typeof params['options'] !== 'undefined') && Array.isArray(params.options)) {
			this.options(params.options.map((optionData: RevChoiceOptionData) => new RevChoiceControlOption(optionData)));
		}
	}

	static componentName = 'ame-rev-choice-control';
	static template = ``;
}
