import {ComponentInitParams, paramParsers} from '../../controls.js';
import {AmeRevDescription} from '../ame-rev-description/ame-rev-description.js';

export class AmeRevNestedDescription extends AmeRevDescription {
	public readonly includeLineBreak: KnockoutObservable<boolean>;
	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.includeLineBreak = paramParsers.boolean(params, 'includeLineBreak', true, this.context);
	}

	static componentName = 'ame-rev-nested-description';
	static template = `
		<!-- ko if: includeLineBreak --><br><!-- /ko --><span class="description" data-bind="html: descriptionHtml"></span>
	`;
}
