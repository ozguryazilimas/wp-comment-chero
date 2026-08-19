import { UiElement } from '../../controls.js';
import { looksLikeSerializedMinimalBinding, unserializeMinimalBinding } from '../../../../shared-dsl/client/wire-dsl.js';
export class AmeRevWith extends UiElement {
    constructor(params, $element) {
        super(params, $element);
        const inputItem = params.item;
        if (!looksLikeSerializedMinimalBinding(inputItem)) {
            console.error('AmeRevWith requires the "item" parameter to be a serialized expression.', inputItem);
            throw new Error('AmeRevWith requires the "item" parameter to be a serialized expression.');
        }
        const itemBinding = this.context.resolve(unserializeMinimalBinding(inputItem));
        if (!itemBinding) {
            throw new Error('AmeRevWith requires the "item" binding to resolve to a valid value.');
        }
        this.item = itemBinding;
    }
}
AmeRevWith.componentName = 'ame-rev-with';
AmeRevWith.template = `
		<!-- ko foreach: {data: $component.slots['default']} -->
			<!-- ko component: $component.generateChildComponent($data, $component.item) -->
		<!-- /ko -->
	`;
//# sourceMappingURL=ame-rev-with.js.map