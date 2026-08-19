import {DebugContainer, DebugControl, registerKoComponent} from './controls.js';
import {AmeRevTextInput} from './components/ame-rev-text-input/ame-rev-text-input.js';
import {AmeForeachBlock} from './components/ame-foreach/ame-foreach.js';
import {ActorFeatureCheckbox} from './components/ame-rev-actor-feature/ame-rev-actor-feature.js';
import {AmeRevCodeEditor} from './components/ame-rev-code-editor/ame-rev-code-editor.js';
import {AmeRevSiblingDescription} from './components/ame-rev-sibling-description/ame-rev-sibling-description.js';
import {AmeRevControlGroup} from './components/ame-rev-control-group/ame-rev-control-group.js';
import {AmeRevColorPicker} from './components/ame-rev-color-picker/ame-rev-color-picker.js';
import {AmeRevPostboxSection} from './components/ame-rev-postbox-section/ame-rev-postbox-section.js';
import {AmeRevGeneralStructure} from './components/ame-rev-general-structure/ame-rev-general-structure.js';
import {AmeRevEventButton} from './components/ame-rev-event-button/ame-rev-event-button.js';
import {AmeRevNestedDescription} from './components/ame-rev-nested-description/ame-rev-nested-description.js';
import {AmeRevDescription} from './components/ame-rev-description/ame-rev-description.js';
import {AmeRevTooltipTrigger} from './components/ame-rev-tooltip-trigger/ame-rev-tooltip-trigger.js';
import {AmeRevSelectBox} from './components/ame-rev-select-box/ame-rev-select-box.js';
import {AmeRevTextArea} from './components/ame-rev-text-area/ame-rev-text-area.js';
import {AmeRevWith} from './components/ame-rev-with/ame-rev-with.js';
import {AmeRevToggleCheckbox} from './components/ame-rev-toggle-checkbox/ame-rev-toggle-checkbox.js';
import {AmeRevStaticHtml} from './components/ame-rev-static-html/ame-rev-static-html.js';
//ame:component-imports

export function registerRevisedUiComponents(ko: KnockoutStatic): void {
	registerKoComponent(DebugControl, ko);
	registerKoComponent(DebugContainer, ko);

	registerKoComponent(AmeRevControlGroup, ko);
	registerKoComponent(AmeForeachBlock, ko);
	registerKoComponent(AmeRevPostboxSection, ko);

	registerKoComponent(AmeRevTextInput, ko);
	registerKoComponent(ActorFeatureCheckbox, ko);
	registerKoComponent(AmeRevCodeEditor, ko);
	registerKoComponent(AmeRevColorPicker, ko);

	registerKoComponent(AmeRevSiblingDescription, ko);
	registerKoComponent(AmeRevGeneralStructure, ko);
	registerKoComponent(AmeRevEventButton, ko);
	registerKoComponent(AmeRevNestedDescription, ko);
	registerKoComponent(AmeRevDescription, ko);
	registerKoComponent(AmeRevTooltipTrigger, ko);
	registerKoComponent(AmeRevSelectBox, ko);
	registerKoComponent(AmeRevTextArea, ko);
	registerKoComponent(AmeRevWith, ko);
	registerKoComponent(AmeRevToggleCheckbox, ko);
	registerKoComponent(AmeRevStaticHtml, ko);
	//ame:component-registrations
}