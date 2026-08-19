import { Control, paramParsers } from '../../controls.js';
import { Literal, looksLikeSerializedExpression, unserializeExpression } from '../../../../shared-dsl/client/wire-dsl.js';
export class AmeRevEventButton extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.eventData = null;
        /**
         * Whether to wrap the button in a <p> tag.
         */
        this.wrap = false;
        this.customLabel = ko.observable('');
        this.customIconClass = ko.observable('');
        this.kind = 'button';
        this.cachedEventTarget = null;
        this.triedToFindEventTarget = false;
        if (typeof params['eventName'] === 'undefined') {
            throw new Error('AmeEventButton requires an "eventName" parameter to be defined.');
        }
        this.eventName = (params['eventName'] + '');
        if (typeof params['wrap'] !== 'undefined') {
            this.wrap = !!(params['wrap']);
        }
        if (typeof params['kind'] !== 'undefined') {
            this.kind = params['kind'] === 'link' ? 'link' : 'button';
        }
        //Not used as of this writing, but could be used in the future for passing
        //additional data to the event handler.
        if (typeof params['eventData'] !== 'undefined') {
            if (looksLikeSerializedExpression(params['eventData'])) {
                this.eventData = unserializeExpression(params['eventData']);
            }
            else {
                this.eventData = new Literal(params['eventData']);
            }
        }
        this.effectiveLabel = ko.pureComputed(() => {
            return this.customLabel() || this.label();
        });
        this.iconClass = paramParsers.string(params, 'iconClass', '', this.context);
        this.effectiveIconClass = ko.pureComputed(() => {
            return this.customIconClass() || this.iconClass();
        });
        this.elementClasses.unshift('ame-event-button-control', 'ame-rev-event-button');
        if (this.kind === 'button') {
            this.elementClasses.unshift('button');
        }
        else if (this.kind === 'link') {
            this.elementClasses.push('ame-rev-event-button-link');
        }
        if (this.id) {
            this.elementId = this.id;
        }
    }
    triggerEvent() {
        const eventData = {
            data: (this.eventData ? this.eventData.evaluate(this.context) : null),
            eventButton: this
        };
        this.findEventTarget()?.dispatchEvent(new CustomEvent(this.eventName, {
            detail: eventData,
            bubbles: true,
        }));
    }
    findEventTarget() {
        if (this.triedToFindEventTarget) {
            return this.cachedEventTarget;
        }
        this.triedToFindEventTarget = true;
        const $child = this.findChild('input, button, a, p');
        if ($child.length === 0) {
            throw new Error('AmeRevEventButton could not find its child element to dispatch the event on.');
        }
        this.cachedEventTarget = $child[0];
        return this.cachedEventTarget;
    }
}
AmeRevEventButton.buttonContentTemplate = `<!-- ko if: effectiveIconClass 
			--><span data-bind="css: effectiveIconClass"></span><!-- 
		/ko --><!-- ko if: effectiveLabel 
			--><span data-bind="text: effectiveLabel" class="ame-rev-event-button-label"></span><!-- 
		/ko -->`;
AmeRevEventButton.buttonTemplate = `
		<!-- ko if: kind === 'link' -->
			<a href="#" data-bind="class: classString, enable: isEnabled, click: triggerEvent, attr: elementAttributesMap">
				${AmeRevEventButton.buttonContentTemplate}
			</a>
		<!-- /ko -->
		<!-- ko if: kind === 'button' -->
			<button data-bind="class: classString, enable: isEnabled, click: triggerEvent, attr: elementAttributesMap">
				${AmeRevEventButton.buttonContentTemplate}
			</button>
		<!-- /ko -->
	`;
AmeRevEventButton.componentName = 'ame-rev-event-button';
AmeRevEventButton.template = `
		<!-- ko if: wrap -->
			<p>${AmeRevEventButton.buttonTemplate}</p>
		<!-- /ko -->
		<!-- ko ifnot: wrap -->
			${AmeRevEventButton.buttonTemplate}
		<!-- /ko -->
	`;
//# sourceMappingURL=ame-rev-event-button.js.map