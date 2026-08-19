import { paramParsers } from '../../controls.js';
import { AmeRevDescription } from '../ame-rev-description/ame-rev-description.js';
export class AmeRevNestedDescription extends AmeRevDescription {
    constructor(params, $element) {
        super(params, $element);
        this.includeLineBreak = paramParsers.boolean(params, 'includeLineBreak', true, this.context);
    }
}
AmeRevNestedDescription.componentName = 'ame-rev-nested-description';
AmeRevNestedDescription.template = `
		<!-- ko if: includeLineBreak --><br><!-- /ko --><span class="description" data-bind="html: descriptionHtml"></span>
	`;
//# sourceMappingURL=ame-rev-nested-description.js.map