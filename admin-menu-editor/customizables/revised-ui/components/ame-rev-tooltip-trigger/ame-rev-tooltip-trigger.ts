import {ComponentInitParams, Control, TooltipData} from '../../controls.js';


export class AmeRevTooltipTrigger extends Control {
	public readonly text: string;
	public readonly tooltip: TooltipData;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		if ((typeof params.tooltip === 'undefined') || (params.tooltip === null)) {
			throw new Error('AmeRevTooltipTrigger requires a "tooltip" parameter.');
		}

		this.tooltip = params.tooltip as TooltipData;
		this.text = this.tooltip.htmlContent || '';

		//Convert newlines to <br> for better formatting in tooltips.
		//Some other parts of the plugin rely on the implicit conversion of newlines to <br>
		//that qTip2 apparently does when reading the title attribute, but this component
		//doesn't use the title attribute.
		this.text = this.text.replace(/\n/g, '<br>');
	}

	static componentName = 'ame-rev-tooltip-trigger';
	static template = `
		<a class="ws_tooltip_trigger ame-rev-tooltip-trigger"
			data-bind="ameTooltip: {text: text}"><span class="dashicons dashicons-info"></span></a>
	`;
}
