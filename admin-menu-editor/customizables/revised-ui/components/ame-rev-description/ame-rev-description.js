import { Control } from '../../controls.js';
/**
 * General description component. It can be used directly, but it's primarily designed
 * to be extended by other components.
 */
export class AmeRevDescription extends Control {
    constructor(params, $element) {
        super(params, $element);
        if (typeof params.descriptionHtml !== 'undefined') {
            if (typeof params.descriptionHtml === 'string' || ko.isObservable(params.descriptionHtml)) {
                this.descriptionHtml = params.descriptionHtml;
            }
            else {
                throw new Error('descriptionHtml must be a string or a Knockout observable.');
            }
        }
        else {
            this.descriptionHtml = '';
        }
    }
}
AmeRevDescription.componentName = 'ame-rev-description';
AmeRevDescription.template = `<span class="description" data-bind="html: descriptionHtml"></span>`;
//# sourceMappingURL=ame-rev-description.js.map