import {
	SerializedSettingsPack,
	StructSetting,
	deserializeSettingsPackToStruct
} from './settings.js';
import {AnySerializedElement, ComponentInitParams, ComponentParamCustomizer} from './controls.js';
import {ServiceRegistry} from './registry.js';
import {Context} from '../../shared-dsl/client/wire-dsl.js';

export interface SerializedUiPack {
	settingsPack: SerializedSettingsPack;
	interfaceStructure: AnySerializedElement;
}

export class UiPack {
	public readonly rootComponent: { name: string, params: ComponentInitParams };
	public readonly settings: StructSetting;
	public readonly hasValidationErrors: KnockoutComputed<boolean>;

	constructor(
		ui: SerializedUiPack,
		registry: ServiceRegistry<object> | null = null,
		customizer: ComponentParamCustomizer | null = null
	) {
		this.settings = deserializeSettingsPackToStruct(ui.settingsPack);

		if (!registry) {
			registry = ServiceRegistry.init();
		}

		this.rootComponent = {
			name: 'ame-rev-general-structure',
			params: {
				...ui.interfaceStructure,
				registry: registry,
				context: new Context(null, null, this.settings),
				customizer: customizer
			}
		};

		this.hasValidationErrors = ko.pureComputed(() => {
			return this.settings.hasValidationErrors();
		});
	}
}