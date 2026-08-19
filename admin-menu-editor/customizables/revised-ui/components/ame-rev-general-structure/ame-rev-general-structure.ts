import {ComponentInitParams, Container, UiElement} from '../../controls.js';

export class AmeRevGeneralStructure extends Container {
	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);
	}

	static componentName = 'ame-rev-general-structure';
	static template = UiElement.childrenLoopTemplate;
}
