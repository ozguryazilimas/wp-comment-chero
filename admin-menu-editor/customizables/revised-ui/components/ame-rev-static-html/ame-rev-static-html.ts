import {ComponentInitParams, Control} from '../../controls.js';

export class AmeRevStaticHtml extends Control {
	public readonly htmlContent: string;
	public readonly containerType: string = 'span';

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		this.htmlContent = (typeof params.html === 'string') ? params.html : '';
		if (typeof params.container === 'string') {
			this.containerType = params.container;
		}
	}

	static componentName = 'ame-rev-static-html';
	static template = `
		<!-- ko if: containerType === 'div' -->
			<div data-bind="html: htmlContent"></div>
		<!-- /ko -->
		<!-- ko if: containerType === 'span' -->
			<span data-bind="html: htmlContent"></span>
		<!-- /ko -->
	`;
}
