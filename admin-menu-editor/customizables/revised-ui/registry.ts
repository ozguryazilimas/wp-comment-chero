
//This is unfortunately a duplicate of the old implementation in customizable.ts. Consider refactoring
//that to use this implementation once the revised UI is more stable.

export class ServiceRegistry<T extends object> {
	private constructor(private registry: T) {
	}

	register<K extends string, S>(key: K, service: S): ServiceRegistry<Record<K, S> & T> {
		//Add service to registry and return the same object with a narrowed type.
		(this.registry as any)[key] = service;
		return this as any as ServiceRegistry<Record<K, S> & T>;
	}

	get<K extends keyof T>(key: K): T[K] {
		if (!(key in this.registry)) {
			throw new Error('Invalid service key: ' + String(key));
		}
		return this.registry[key];
	}

	has<K extends keyof T>(key: K): boolean {
		return key in this.registry;
	}

	static init(): ServiceRegistry<{}> {
		return new ServiceRegistry({});
	}
}