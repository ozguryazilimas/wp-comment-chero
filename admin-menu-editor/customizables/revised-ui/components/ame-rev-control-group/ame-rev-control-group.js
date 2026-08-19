import { Container, UiElement } from '../../controls.js';
export class AmeRevControlGroup extends Container {
    constructor(params, $element) {
        super(params, $element);
        this.elementClasses.push('ame-general-control-group', 'ame-rev-control-group');
    }
    get classString() {
        const classes = super.classString;
        if (!this.isTitleVisible) {
            return classes + ' ame-rev-cg-no-title';
        }
        return classes;
    }
}
AmeRevControlGroup.componentName = 'ame-rev-control-group';
AmeRevControlGroup.template = `
		<div data-bind="class: classString">
			<!-- ko if: isTitleVisible -->
				<h4 class="ame-gcg-title ame-rev-cg-title">
					<span data-bind="text: title"></span>
				</h4>
			<!-- /ko -->
			<div class="ame-gcg-children ame-rev-cg-children">
				${UiElement.childrenLoopTemplate}
			</div>
		</div>`;
//# sourceMappingURL=ame-rev-control-group.js.map