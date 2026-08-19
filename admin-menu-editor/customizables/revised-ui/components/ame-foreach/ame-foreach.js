import { UiElement } from '../../controls.js';
import { looksLikeSerializedMinimalBinding, unserializeMinimalBinding } from '../../../../shared-dsl/client/wire-dsl.js';
export class AmeForeachBlock extends UiElement {
    constructor(params, $element) {
        super(params, $element);
        const inputItems = params.items;
        if (!looksLikeSerializedMinimalBinding(inputItems)) {
            console.error('AmeForeachBlock requires the "items" parameter to be a serialized expression.', inputItems);
            throw new Error('AmeForeachBlock requires the "items" parameter to be a serialized expression.');
        }
        const itemsBinding = this.context.resolve(unserializeMinimalBinding(inputItems));
        if (!itemsBinding?.isIterable()) {
            throw new Error('AmeForeachBlock requires the "items" binding to resolve to an iterable collection.');
        }
        const items = itemsBinding.items; //Temp variable for type narrowing.
        if (this.isObservableArray(items)) {
            this._items = items;
        }
        else {
            throw new Error('AmeForeachBlock requires the "items" binding to resolve to an observable array.');
        }
    }
    isObservableArray(value) {
        return (typeof value === 'function')
            && (typeof value.slice === 'function')
            && (typeof value.indexOf === 'function')
            && (ko.isObservable(value));
    }
    get items() {
        return this._items;
    }
}
AmeForeachBlock.componentName = 'ame-foreach';
AmeForeachBlock.template = `
		<!-- ko foreach: {data: items, as: 'dataItem'} -->
			<!-- ko foreach: {data: $component.slots['default']} -->
				<div data-bind="component: $component.generateChildComponent($data, dataItem)"></div>
			<!-- /ko -->
		<!-- /ko -->
	`;
//# sourceMappingURL=ame-foreach.js.map