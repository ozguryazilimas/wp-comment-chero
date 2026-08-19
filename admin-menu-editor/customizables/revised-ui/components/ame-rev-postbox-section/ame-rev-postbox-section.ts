import {ComponentInitParams, Container, TooltipData, UiElement} from '../../controls.js';

export class AmeRevPostboxSection extends Container {
	public readonly isOpen: KnockoutObservable<boolean>;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
		this.elementClasses.push('ws-ame-postbox', 'ame-postbox-section');

		//Optionally, remember the open/closed state of the section.
		if (this.id && this.registry && this.registry.has('collapsibleStateStore')) {
			const collapsibleStateStore: AmeCollapsibleStateStore = this.registry.get('collapsibleStateStore');
			this.isOpen = collapsibleStateStore.getOrCreateObservable(this.id, true);
		} else {
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

	public toggle(): void {
		this.isOpen(!this.isOpen());
	}

	static componentName = 'ame-rev-postbox-section';
	static template = `
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
}