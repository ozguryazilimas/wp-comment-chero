'use strict';
var AmeMiniFunc;
(function (AmeMiniFunc) {
    class Some {
        constructor(value) {
            this.value = value;
        }
        get() {
            return this.value;
        }
        isDefined() {
            return true;
        }
        isEmpty() {
            return false;
        }
        nonEmpty() {
            return true;
        }
        map(f) {
            return new Some(f(this.value));
        }
        flatMap(f) {
            return f(this.value);
        }
        filter(f) {
            return f(this.value) ? this : AmeMiniFunc.none;
        }
        forEach(f) {
            f(this.value);
        }
        orElse(alternative) {
            return this;
        }
        getOrElse(alternative) {
            return this.value;
        }
        toArray() {
            return [this.value];
        }
        orNull() {
            return this.value;
        }
        getOrThrow() {
            return this.value;
        }
    }
    class None {
        map(f) {
            return this;
        }
        get() {
            throw new Error('Cannot get value from None');
        }
        isDefined() {
            return false;
        }
        isEmpty() {
            return true;
        }
        nonEmpty() {
            return false;
        }
        filter(f) {
            return this;
        }
        forEach(f) {
        }
        orElse(alternative) {
            return alternative();
        }
        getOrElse(alternative) {
            return alternative();
        }
        orNull() {
            return null;
        }
        getOrThrow(message) {
            throw new Error(message ?? 'Cannot get value from None');
        }
        flatMap(f) {
            return this;
        }
        toArray() {
            return [];
        }
    }
    AmeMiniFunc.none = new None();
    function some(value) {
        return new Some(value);
    }
    AmeMiniFunc.some = some;
    function lift(options, f) {
        const areAllDefined = options.every((opt) => opt.isDefined());
        if (areAllDefined) {
            const unwrappedValues = options.map((opt) => opt.get());
            return some(f(...unwrappedValues));
        }
        else {
            return AmeMiniFunc.none;
        }
    }
    AmeMiniFunc.lift = lift;
    //endregion
    //region Either
    class Either {
        map(f) {
            if (this.isRight()) {
                return new Right(f(this.value));
            }
            else {
                return this; //Should be safe.
            }
        }
        flatMap(f) {
            if (this.isRight()) {
                return f(this.value);
            }
            else {
                return this;
            }
        }
        toOption() {
            if (this.isRight()) {
                return some(this.value);
            }
            else {
                return AmeMiniFunc.none;
            }
        }
        static left(value) {
            return new Left(value);
        }
        static right(value) {
            return new Right(value);
        }
    }
    AmeMiniFunc.Either = Either;
    class Left extends Either {
        constructor(value) {
            super();
            this.value = value;
        }
        isLeft() {
            return true;
        }
        isRight() {
            return false;
        }
        getOrElse(defaultValue) {
            return defaultValue();
        }
    }
    AmeMiniFunc.Left = Left;
    class Right extends Either {
        constructor(value) {
            super();
            this.value = value;
        }
        isLeft() {
            return false;
        }
        isRight() {
            return true;
        }
        getOrElse(defaultValue) {
            return this.value;
        }
    }
    AmeMiniFunc.Right = Right;
    function sanitizeNumericString(str) {
        if (str === '') {
            return '';
        }
        let sanitizedString = str
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
    AmeMiniFunc.sanitizeNumericString = sanitizeNumericString;
    function forEachObjectKey(collection, callback) {
        for (const k in collection) {
            if (!collection.hasOwnProperty(k)) {
                continue;
            }
            callback(k, collection[k]);
        }
    }
    AmeMiniFunc.forEachObjectKey = forEachObjectKey;
    /**
     * Creates an object composed of the items in the given array, indexed by the specified property.
     *
     * Like _.keyBy() in lodash, but with stricter types.
     */
    function indexByProperty(items, key) {
        const result = {};
        items.forEach(item => {
            result[item[key]] = item;
        });
        return result;
    }
    AmeMiniFunc.indexByProperty = indexByProperty;
    //endregion
})(AmeMiniFunc || (AmeMiniFunc = {}));
//# sourceMappingURL=mini-func.js.map