import { Container, TooltipData, UiElement } from '../../controls.js';
export class AmeRevPostboxSection extends Container {
    constructor(params, $element) {
        super(params, $element);
        this.elementClasses.push('ws-ame-postbox', 'ame-postbox-section');
        //Optionally, remember the open/closed state of the section.
        if (this.id && this.registry && this.registry.has('collapsibleStateStore')) {
            const collapsibleStateStore = this.registry.get('collapsibleStateStore');
            this.isOpen = collapsibleStateStore.getOrCreateObservable(this.id, true);
        }
        else {
            this.isOpen = ko.observable(true);
        }
        if (this.id) {
            this.elementId = this.id;
        }
        if (this.description() && !this.tooltip) {
            this.tooltip = new TooltipData(this.description(), 'info');
        }
        if (typeof params.registerSelf === 'function') {
            params.registerSelf(this);
        }
    }
    toggle() {
        this.isOpen(!this.isOpen());
    }
}
AmeRevPostboxSection.componentName = 'ame-rev-postbox-section';
AmeRevPostboxSection.template = `
		<div class="ws-ame-postbox ame-postbox-section" 
			data-bind="css: { 'ws-ame-closed-postbox': !isOpen() }, attr: elementAttributesMap, class: classString">
			<div class="ws-ame-postbox-header">
				<h3>
					<span data-bind="text: title"></span>
					${UiElement.tooltipTriggerTemplate}
				</h3>
				<button class="ws-ame-postbox-toggle" data-bind="click: toggle"></button>
			</div>
			<div class="ws-ame-postbox-content" data-bind="class: childrenContainerClassString">
				<!-- ko foreach: {data: $component.slots['default']} -->
					<div class="ame-postbox-section-child">
					<!-- ko component: $component.generateChildComponent($data) --><!-- /ko -->
					</div>
				<!-- /ko -->			
			</div>
		</div>
	`;
//# sourceMappingURL=ame-rev-postbox-section.js.map