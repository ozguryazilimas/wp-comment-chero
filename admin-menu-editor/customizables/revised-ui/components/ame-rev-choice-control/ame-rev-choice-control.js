import { Control } from '../../controls.js';
export class RevChoiceControlOption {
    constructor(data) {
        this.value = data.value;
        this.label = data.label;
        this.description = data.description || '';
        this.enabled = (typeof data.enabled === 'undefined') || data.enabled;
        this.icon = data.icon || '';
    }
}
export class AmeRevChoiceControl extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.options = ko.observableArray([]);
        if ((typeof params['options'] !== 'undefined') && Array.isArray(params.options)) {
            this.options(params.options.map((optionData) => new RevChoiceControlOption(optionData)));
        }
    }
}
AmeRevChoiceControl.componentName = 'ame-rev-choice-control';
AmeRevChoiceControl.template = ``;
//# sourceMappingURL=ame-rev-choice-control.js.map