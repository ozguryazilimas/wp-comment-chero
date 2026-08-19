'use strict';

namespace AmeMiniFunc {

	//region Option
	interface OptionOps<T> {
		isDefined(): this is Some<T>;

		isEmpty(): this is None;

		nonEmpty(): this is Some<T>;

		get(): T;

		map<R>(f: (value: T) => R): Option<R>;

		flatMap<R>(f: (value: T) => Option<R>): Option<R>;

		filter(f: (value: T) => boolean): Option<T>;

		forEach(f: (value: T) => void): void;

		orElse(alternative: () => Option<T>): Option<T>;

		getOrElse(defaultValue: () => T): T;

		getOrThrow(message?: string): T;

		orNull<R extends T>(): T | R | null;

		toArray(): T[];
	}

	class Some<T> implements OptionOps<T> {
		constructor(private value: T) {
		}

		get(): T {
			return this.value;
		}

		isDefined(): this is Some<T> {
			return true;
		}

		isEmpty(): this is None {
			return false;
		}

		nonEmpty(): this is Some<T> {
			return true;
		}

		map<R>(f: (value: T) => R): Some<R> {
			return new Some(f(this.value));
		}

		flatMap<R>(f: (value: T) => Option<R>): Option<R> {
			return f(this.value);
		}

		filter(f: (value: T) => boolean): Option<T> {
			return f(this.value) ? this : none;
		}

		forEach(f: (value: T) => void): void {
			f(this.value);
		}

		orElse(alternative: () => Option<T>): Option<T> {
			return this;
		}

		getOrElse(alternative: () => T): T {
			return this.value;
		}

		toArray(): T[] {
			return [this.value];
		}

		orNull(): T | null {
			return this.value;
		}

		getOrThrow(): T {
			return this.value;
		}
	}

	class None implements OptionOps<never> {
		map<R>(f: (value: never) => R): None {
			return this;
		}

		get(): never {
			throw new Error('Cannot get value from None');
		}

		isDefined(): this is Some<never> {
			return false;
		}

		isEmpty(): this is None {
			return true;
		}

		nonEmpty(): this is Some<never> {
			return false;
		}

		filter(f: (value: never) => boolean): None {
			return this;
		}

		forEach(f: (value: never) => void): void {
		}

		orElse<R>(alternative: () => Option<R>): Option<R> {
			return alternative();
		}

		getOrElse<R>(alternative: () => R): R {
			return alternative();
		}

		orNull(): null {
			return null;
		}

		getOrThrow(message?: string): never {
			throw new Error(message ?? 'Cannot get value from None');
		}

		flatMap<R>(f: (value: never) => Option<R>): Option<R> {
			return this;
		}

		toArray(): [] {
			return [];
		}
	}

	export const none = new None();

	export function some<T>(value: T): Some<T> {
		return new Some(value);
	}

	export type Option<T> = Some<T> | None;

	type LiftedFunction<TArgs extends any[], R> = (
		...args: { [K in keyof TArgs]: TArgs[K] extends Option<infer U> ? U : never }
	) => R;

	export function lift<TArgs extends Option<any>[], R>(
		options: [...TArgs],
		f: LiftedFunction<TArgs, R>
	): Option<R> {
		const areAllDefined = options.every((opt) => opt.isDefined());

		if (areAllDefined) {
			const unwrappedValues = options.map((opt) => opt.get()) as unknown as Parameters<LiftedFunction<TArgs, R>>;
			return some(f(...unwrappedValues));
		} else {
			return none;
		}
	}

	//endregion

	//region Either
	export abstract class Either<A, B> {
		abstract isLeft(): this is Left<A, B> ;

		abstract isRight(): this is Right<A, B> ;

		abstract getOrElse(defaultValue: () => B): B;

		map<R>(f: (value: B) => R): Either<A, R> {
			if (this.isRight()) {
				return new Right(f(this.value));
			} else {
				return (this as unknown as Either<A, R>); //Should be safe.
			}
		}

		flatMap<R>(f: (value: B) => Either<A, R>): Either<A, R> {
			if (this.isRight()) {
				return f(this.value);
			} else {
				return (this as unknown as Either<A, R>);
			}
		}

		toOption(): Option<B> {
			if (this.isRight()) {
				return some(this.value);
			} else {
				return none;
			}
		}

		static left<A, B>(value: A): Left<A, B> {
			return new Left(value);
		}

		static right<A, B>(value: B): Right<A, B> {
			return new Right(value);
		}
	}

	export class Left<A, B> extends Either<A, B> {
		constructor(public readonly value: A) {
			super();
		}

		isLeft(): this is Left<A, B> {
			return true;
		}

		isRight(): this is Right<A, B> {
			return false;
		}

		getOrElse(defaultValue: () => B): B {
			return defaultValue();
		}
	}

	export class Right<A, B> extends Either<A, B> {
		constructor(public readonly value: B) {
			super();
		}

		isLeft(): this is Left<A, B> {
			return false;
		}

		isRight(): this is Right<A, B> {
			return true;
		}

		getOrElse(defaultValue: () => B): B {
			return this.value;
		}
	}

	//endregion

	//region Misc
	type SetPropertyType<T, K extends keyof T, NewType> = { [P in K]: NewType } & Omit<T, K>;

	export function sanitizeNumericString(str: string): string {
		if (str === '') {
			return ''
		}

		let sanitizedString: string = str
			//Replace commas with periods.
			.replace(/,/g, '.')
			//Remove all non-numeric characters.
			.replace(/[^0-9.-]/g, '')
			//Remove all but the last period.
			.replace(/\.(?=.*\.)/g, '');

		//Keep a minus sign only if it's the first character. Remove all other occurrences.
		const hasMinusSign = (sanitizedString.charAt(0) === '-');
		sanitizedString = sanitizedString.replace(/-/g, '');
		if (hasMinusSign) {
			sanitizedString = '-' + sanitizedString;
		}

		return sanitizedString;
	}

	export function forEachObjectKey<T extends object>(
		collection: T,
		callback: (key: keyof T, value: T[keyof T]) => void
	) {
		for (const k in collection) {
			if (!collection.hasOwnProperty(k)) {
				continue;
			}
			callback(k, collection[k]);
		}
	}

	/**
	 * Creates an object composed of the items in the given array, indexed by the specified property.
	 *
	 * Like _.keyBy() in lodash, but with stricter types.
	 */
	export function indexByProperty<
		T extends SetPropertyType<Record<PropertyKey, unknown>, K, PropertyKey>,
		K extends keyof T
	>(
		items: T[],
		key: K
	): Record<T[K], T> {
		const result: Record<T[K], T> = {} as Record<T[K], T>;
		items.forEach(item => {
			result[item[key]] = item;
		});
		return result;
	}
	//endregion
}