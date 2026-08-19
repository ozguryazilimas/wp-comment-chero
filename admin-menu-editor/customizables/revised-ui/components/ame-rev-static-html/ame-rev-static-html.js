import { Control } from '../../controls.js';
export class AmeRevStaticHtml extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.containerType = 'span';
        this.htmlContent = (typeof params.html === 'string') ? params.html : '';
        if (typeof params.container === 'string') {
            this.containerType = params.container;
        }
    }
}
AmeRevStaticHtml.componentName = 'ame-rev-static-html';
AmeRevStaticHtml.template = `
		<!-- ko if: containerType === 'div' -->
			<div data-bind="html: htmlContent"></div>
		<!-- /ko -->
		<!-- ko if: containerType === 'span' -->
			<span data-bind="html: htmlContent"></span>
		<!-- /ko -->
	`;
//# sourceMappingURL=ame-rev-static-html.js.map