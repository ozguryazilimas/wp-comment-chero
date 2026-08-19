import { deserializeSettingsPackToStruct } from './settings.js';
import { ServiceRegistry } from './registry.js';
import { Context } from '../../shared-dsl/client/wire-dsl.js';
export class UiPack {
    constructor(ui, registry = null, customizer = null) {
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
//# sourceMappingURL=ui-pack.js.map