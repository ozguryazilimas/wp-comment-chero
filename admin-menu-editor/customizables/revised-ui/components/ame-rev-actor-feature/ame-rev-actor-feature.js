import { Control, UiElement } from '../../controls.js';
const REGISTER_CHILD_PARAM = 'cbRegisterCheckedObservable';
const DEREGISTER_CHILD_PARAM = 'cbDeregisterCheckedObservable';
export class ActorFeatureCheckbox extends Control {
    constructor(params, $element) {
        super(params, $element);
        this.childCheckedObservables = ko.observableArray([]);
        this.parentDeregisterCallback = null;
        this.elementClasses.push('ame-actor-feature-checkbox-control');
        this.inputClasses.push('ame-actor-feature-checkbox');
        if (this.id) {
            this.elementId = this.id;
        }
        if (typeof this.bindings['value'] === 'undefined') {
            throw new Error('ActorFeatureCheckbox requires a "value" binding to be defined.');
        }
        const settingValue = this.bindings.value.value;
        const observableMap = new AmeObservableActorFeatureMap(settingValue());
        //Link the observable map and the setting value.
        let isUpdating = false;
        ko.computed(() => observableMap.getAll())
            .extend({ deferred: true })
            .subscribe((newValue) => {
            if (isUpdating) {
                return;
            }
            //Avoid updating the setting if the value hasn't actually changed.
            //This isn't strictly necessary to avoid infinite loops, but it helps prevent some
            //unnecessary updates that isUpdating alone doesn't prevent (likely because of
            //{deferred: true} above).
            const currentExternalValue = settingValue.peek();
            if (wsAmeLodash.isEqual(currentExternalValue, newValue)) {
                return;
            }
            isUpdating = true;
            settingValue(newValue);
            isUpdating = false;
        });
        //Apply changes from the setting to the observable map.
        settingValue.subscribe((externalValue) => {
            if (isUpdating) {
                return;
            }
            isUpdating = true;
            if (externalValue === null) {
                observableMap.resetAll();
            }
            else {
                observableMap.setAll(externalValue);
            }
            isUpdating = false;
        });
        this.featureState = new AmeActorFeatureState(observableMap, this.acquireFeatureStrategy(params));
        this.isChecked = ko.computed({
            read: this.featureState.isEnabled,
            write: (newValue) => {
                this.featureState.isEnabled(newValue);
                //When the user checks or unchecks this checkbox, update all child checkboxes.
                //Note that this only propagates changes from parent to children, not the other way around.
                //The setting represented by this checkbox can be independent of its children, like
                //a parent tweak that hides an entire section + child tweaks that hide individual fields.
                this.childCheckedObservables().forEach((childObservable) => {
                    childObservable(newValue);
                });
            }
        });
        this.isIndeterminate = this.featureState.isIndeterminate;
        //Let children register themselves so that we can update them when the parent checkbox is toggled.
        this.registerChildObservable = (childObservable) => {
            this.childCheckedObservables.push(childObservable);
        };
        this.deregisterChildObservable = (childObservable) => {
            this.childCheckedObservables.remove(childObservable);
        };
        //Register this control with the parent checkbox, if any.
        if (typeof params[REGISTER_CHILD_PARAM] === 'function') {
            params[REGISTER_CHILD_PARAM](this.isChecked);
        }
        if (typeof params[DEREGISTER_CHILD_PARAM] === 'function') {
            this.parentDeregisterCallback = params[DEREGISTER_CHILD_PARAM];
        }
    }
    acquireFeatureStrategy(params) {
        //The strategy can either be passed directly or constructed using an actor selector from
        //the service registry and optional strategy settings.
        if (typeof params['strategy'] !== 'undefined') {
            const strategy = params['strategy'];
            if (!(strategy instanceof AmeActorFeatureStrategy)) {
                throw new Error('AmeActorFeatureCheckbox parameter "strategy" is not a valid AmeActorFeatureStrategy instance.');
            }
            return strategy;
        }
        const actorSelector = this.registry.get('actorSelector');
        if (!(actorSelector instanceof AmeActorSelector)) {
            throw new Error('AmeActorFeatureCheckbox requires a valid AmeActorSelector registered as "actorSelector" in the ServiceRegistry.');
        }
        return new AmeActorFeatureStrategy({
            ...ameUnserializeFeatureStrategySettings(params.strategySettings ?? {}),
            getSelectedActor: actorSelector.getActorObservable(ko),
            getAllActors: () => actorSelector.getVisibleActors()
        });
    }
    generateChildParams(childParams, dataItem) {
        const params = super.generateChildParams(childParams, dataItem);
        params[REGISTER_CHILD_PARAM] = this.registerChildObservable;
        params[DEREGISTER_CHILD_PARAM] = this.deregisterChildObservable;
        return params;
    }
    dispose() {
        //Deregister this control from the parent checkbox, if any.
        if (this.parentDeregisterCallback) {
            this.parentDeregisterCallback(this.isChecked);
        }
        super.dispose();
    }
}
ActorFeatureCheckbox.componentName = 'ame-rev-actor-feature';
ActorFeatureCheckbox.template = `
		<div data-bind="class: classString, attr: elementAttributesMap">
			<label>
				<input type="checkbox" data-bind="checked: isChecked, indeterminate: isIndeterminate, 
					attr: inputAttributesMap, class: inputClassString, enable: isEnabled">
				<span data-bind="text: label"></span>
				${UiElement.tooltipTriggerTemplate}
			</label>
			<!-- ko if: slotHasContent('actions') -->
			<span class="ame-rev-afc-actions">
				${UiElement.slotContentTemplate('actions')}
			</span>
			<!-- /ko -->
			<!-- ko if: (description) -->
				<!-- ko component: {
					name: 'ame-rev-nested-description', 
					params: generateDescriptionParams({includeLineBreak: false})
				} --><!-- /ko -->
			<!-- /ko -->
			${Control.childrenContainerTemplate}
		</div>
	`;
//# sourceMappingURL=ame-rev-actor-feature.js.map