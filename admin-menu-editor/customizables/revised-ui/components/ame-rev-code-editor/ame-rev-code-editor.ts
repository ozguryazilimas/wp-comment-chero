import {ComponentInitParams, Control} from '../../controls.js';

export class AmeRevCodeEditor extends Control {
	protected readonly editorSettings: object | false;

	constructor(params: ComponentInitParams, $element: JQuery) {
		super(params, $element);

		if ((typeof params.editorSettings === 'object') && (params.editorSettings !== null)) {
			this.editorSettings = params.editorSettings;
		} else {
			this.editorSettings = false;
		}

		this.elementClasses.push('ame-code-editor-control-wrap', 'ame-rev-code-editor-control');
	}

	static template = `
		<div data-bind="class: classString"> 
			<textarea data-bind="attr: inputAttributesMap, value: mainBindingValue, 
				class: inputClassString, ameCodeMirror: editorSettings" 
				class="large-text" cols="50" rows="10"></textarea>
		</div>` + Control.siblingDescriptionTemplate;

	static componentName = 'ame-rev-code-editor';
}