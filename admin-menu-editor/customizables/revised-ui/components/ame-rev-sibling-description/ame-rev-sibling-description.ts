import {AmeRevDescription} from '../ame-rev-description/ame-rev-description.js';

export class AmeRevSiblingDescription extends AmeRevDescription {
	static componentName = 'ame-rev-sibling-description';
	static template = `<p class="description" data-bind="html: descriptionHtml"></p>`;
}