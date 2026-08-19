import { Control } from '../../controls.js';
export class AmeRevCodeEditor extends Control {
    constructor(params, $element) {
        super(params, $element);
        if ((typeof params.editorSettings === 'object') && (params.editorSettings !== null)) {
            this.editorSettings = params.editorSettings;
        }
        else {
            this.editorSettings = false;
        }
        this.elementClasses.push('ame-code-editor-control-wrap', 'ame-rev-code-editor-control');
    }
}
AmeRevCodeEditor.template = `
		<div data-bind="class: classString"> 
			<textarea data-bind="attr: inputAttributesMap, value: mainBindingValue, 
				class: inputClassString, ameCodeMirror: editorSettings" 
				class="large-text" cols="50" rows="10"></textarea>
		</div>` + Control.siblingDescriptionTemplate;
AmeRevCodeEditor.componentName = 'ame-rev-code-editor';
//# sourceMappingURL=ame-rev-code-editor.js.map