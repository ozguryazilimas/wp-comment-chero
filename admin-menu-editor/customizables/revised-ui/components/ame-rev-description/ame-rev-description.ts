import {ComponentInitParams, Control} from '../../controls.js';

/**
 * General description component. It can be used directly, but it's primarily designed
 * to be extended by other components.
 */
export class AmeRevDescription extends Control {
	public readonly descriptionHtml: KnockoutObservable<string> | string;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		if (typeof params.descriptionHtml !== 'undefined') {
			if (typeof params.descriptionHtml === 'string' || ko.isObservable(params.descriptionHtml)) {
				this.descriptionHtml = params.descriptionHtml;
			} else {
				throw new Error('descriptionHtml must be a string or a Knockout observable.');
			}
		} else {
			this.descriptionHtml = '';
		}
	}

	static componentName = 'ame-rev-description';
	static template = `<span class="description" data-bind="html: descriptionHtml"></span>`;
}
