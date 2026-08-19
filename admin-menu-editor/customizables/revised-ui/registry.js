//This is unfortunately a duplicate of the old implementation in customizable.ts. Consider refactoring
//that to use this implementation once the revised UI is more stable.
export class ServiceRegistry {
    constructor(registry) {
        this.registry = registry;
    }
    register(key, service) {
        //Add service to registry and return the same object with a narrowed type.
        this.registry[key] = service;
        return this;
    }
    get(key) {
        if (!(key in this.registry)) {
            throw new Error('Invalid service key: ' + String(key));
        }
        return this.registry[key];
    }
    has(key) {
        return key in this.registry;
    }
    static init() {
        return new ServiceRegistry({});
    }
}
//# sourceMappingURL=registry.js.map