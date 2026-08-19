<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Settings;

use YahnisElsts\AdminMenuEditor\Customizable\Storage;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;

abstract class AbstractStructSetting extends AbstractSetting implements \ArrayAccess, \IteratorAggregate, SettingContainer {
	protected $dataType = 'map';

	/**
	 * @var array<string,AbstractSetting>
	 */
	protected $settings = [];

	/**
	 * @var callable
	 */
	protected $childUpdateCallback;
	protected $childSubscriptionsAdded = false;
	protected $isInUpdateLoop = false;

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		//Minor optimization: Create the callback array once and reuse it for every child.
		$this->childUpdateCallback = [$this, 'notifyChildWasUpdated'];

		if ( $store === null ) {
			$store = new Storage\NullStorage();
		}
		parent::__construct($id, $store, $params);
	}

	/**
	 * @param \WP_Error $errors
	 * @param array<string,mixed> $value
	 * @return \WP_Error|array<string,mixed>
	 */
	public function validate($errors, $value, $stopOnFirstError = false) {
		if ( !is_array($value) ) {
			$errors->add('struct_value_invalid', 'Struct value must be an associative array');
			return $errors;
		}

		$validatedValues = [];
		$foundErrors = false;
		foreach ($this->settings as $key => $setting) {
			if ( array_key_exists($key, $value) ) {
				$validity = $setting->validate($errors, $value[$key], $stopOnFirstError);
				if ( is_wp_error($validity) ) {
					$foundErrors = true;
					if ( $stopOnFirstError ) {
						break;
					}
				} else {
					$validatedValues[$key] = $validity;
				}
			}
		}

		if ( $foundErrors ) {
			return $errors;
		} else {
			return $validatedValues;
		}
	}

	public function update($validValue) {
		$this->isInUpdateLoop = true;

		$isSuccess = true;
		foreach ($this->settings as $key => $setting) {
			if ( array_key_exists($key, $validValue) ) {
				$isSuccess = $isSuccess && $setting->update($validValue[$key]);
			}
		}

		$this->isInUpdateLoop = false;
		$this->notifyUpdated();

		return $isSuccess;
	}

	public function getValue($customDefault = []) {
		$result = is_array($customDefault) ? $customDefault : [];
		foreach ($this->settings as $key => $setting) {
			$result[$key] = $setting->getValue();
		}
		return $result;
	}

	public function preview($unsafeValue, $errors = null) {
		if ( !is_array($unsafeValue) ) {
			return;
		}
		if ( $errors === null ) {
			$errors = new \WP_Error();
		}

		foreach ($this->settings as $key => $setting) {
			if ( array_key_exists($key, $unsafeValue) ) {
				$setting->preview($unsafeValue[$key], $errors);
			}
		}
	}

	/**
	 * @return array
	 */
	public function getDefaultValue() {
		return [];
	}

	public function encodeForForm($value) {
		//In practice, a struct should never be output as a form field value.
		//We just include this to simplify the class hierarchy.
		return wp_json_encode($value);
	}

	public function decodeSubmittedValue($value) {
		$decodedValue = @json_decode($value, true);
		if ( !is_array($decodedValue) ) {
			return [];
		}
		return $decodedValue;
	}

	public function canBeDeleted() {
		if ( $this->deleteWhenBlank ) {
			//In addition to other "blank" states, a struct can also be deleted
			//if it has no children or if all of its children can be deleted.
			$isDeletable = true;
			foreach ($this->settings as $setting) {
				if ( !$setting->canBeDeleted() ) {
					$isDeletable = false;
					break;
				}
			}
			if ( $isDeletable ) {
				return true;
			}
		}
		return parent::canBeDeleted();
	}

	public function subscribe($callback) {
		parent::subscribe($callback);

		//Optimization: Listen for child updates only if we have subscribers.
		if ( !empty($this->updateSubscribers) && !$this->childSubscriptionsAdded ) {
			$this->childSubscriptionsAdded = true;
			foreach ($this->settings as $setting) {
				$setting->subscribe($this->childUpdateCallback);
			}
		}
	}

	/**
	 * @internal     This method needs to be public because it's used as a callback,
	 * but you should not call it directly.
	 *
	 * @noinspection PhpUnusedParameterInspection In the current implementation,
	 * we don't care which specific child was updated, only that one was.
	 *
	 * @param AbstractSetting $childSetting
	 * @return void
	 */
	public function notifyChildWasUpdated(AbstractSetting $childSetting) {
		//If we're inside the foreach loop in update(), we don't need to notify
		//our subscribers here - update() will do it after the loop.
		//Also, we currently don't support recursive notifications, so if a child
		//is updated while we're sending notifications, we'll just ignore it.
		if ( $this->isInUpdateLoop || $this->isNotifyingSubscribers ) {
			return;
		}

		//Optimization: Multiple children can be updated at the same time, and
		//it would be inefficient to notify the parent's subscribers every time.
		//Instead, we'll put the parent in a queue. The code performing the update
		//should send pending notifications when it's done.
		static::getNotificationQueue()->enqueue($this);
	}

	/**
	 * @param string $key
	 * @return AbstractSetting|null
	 */
	public function getChild($key) {
		if ( array_key_exists($key, $this->settings) ) {
			return $this->settings[$key];
		}
		return null;
	}

	public function getChildValue($childSettingKey, $defaultValue = null) {
		if ( array_key_exists($childSettingKey, $this->settings) ) {
			return $this->settings[$childSettingKey]->getValue($defaultValue);
		}
		return $defaultValue;
	}

	function findDirectChildSetting(string $key): ?AbstractSetting {
		return $this->getChild($key);
	}

	function findSetting($settingIdOrPath): ?AbstractSetting {
		$settingIdOrPath = explode('.', $settingIdOrPath);
		if ( empty($settingIdOrPath) ) {
			return null;
		}

		$firstKey = array_shift($settingIdOrPath);
		$current = $this->getChild($firstKey);

		foreach ($settingIdOrPath as $key) {
			if ( $current instanceof SettingContainer ) {
				$current = $current->findDirectChildSetting($key);
			} else {
				return null;
			}
		}

		return $current;
	}

	public function makeChildId($childKey) {
		return $this->id . '.' . $childKey;
	}

	/**
	 * Create a child setting of the specified type.
	 *
	 * @param string $childKey
	 * @param class-string<AbstractSetting> $className
	 * @param ...$constructorParams
	 * @return AbstractSetting
	 */
	protected function createChild($childKey, $className, ...$constructorParams) {
		$child = new $className(
			$this->makeChildId($childKey),
			$this->store->buildSlot($childKey),
			...$constructorParams
		);

		$this->registerChild($childKey, $child);

		return $child;
	}

	protected function registerChild($childKey, AbstractSetting $child) {
		if ( $this->shouldEnablePostMessageForChildren() ) {
			$child->enablePostMessageSupport();
		}

		//Children inherit the parent's tags.
		$child->addTags(...$this->tags);

		$this->settings[$childKey] = $child;

		if ( $this->childSubscriptionsAdded ) {
			$child->subscribe($this->childUpdateCallback);
		}
	}

	public function enablePostMessageSupport() {
		parent::enablePostMessageSupport();

		if ( $this->shouldEnablePostMessageForChildren() ) {
			foreach ($this->settings as $setting) {
				$setting->enablePostMessageSupport();
			}
		}
	}

	protected function shouldEnablePostMessageForChildren() {
		return $this->supportsPostMessage;
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function offsetExists($offset) {
		return array_key_exists($offset, $this->settings);
	}

	/**
	 * @param string $offset
	 * @return AbstractSetting|null
	 * @noinspection PhpLanguageLevelInspection
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		return $this->getChild($offset);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value) {
		throw new \LogicException(
			'Cannot add or replace a child of a struct. The setting list is read-only.'
		);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset) {
		throw new \LogicException(
			'Cannot remove a child of a struct. The setting list is read-only.'
		);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator($this->settings);
	}
}