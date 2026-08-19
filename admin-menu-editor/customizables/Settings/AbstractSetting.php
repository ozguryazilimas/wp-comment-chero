<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Settings;

use WP_Error;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Customizable;
use YahnisElsts\AdminMenuEditor\Customizable\Schemas\SchemaSerializer;
use YahnisElsts\AdminMenuEditor\Customizable\Storage\StorageInterface;
use YahnisElsts\AdminMenuEditor\WireDSL\EvaluationContext;

abstract class AbstractSetting extends Customizable implements UpdateNotificationSender, Binding {
	const SERIALIZE_INCLUDE_VALUE = 1;
	const SERIALIZE_INCLUDE_DEFAULT = 2;
	const SERIALIZE_INCLUDE_ID = 4;
	const SERIALIZE_INCLUDE_GROUP_TITLE = 8;
	const SERIALIZE_INCLUDE_POST_MESSAGE_SUPPORT = 16;
	const SERIALIZE_INCLUDE_TAGS = 32;
	const SERIALIZE_INCLUDE_VALIDATION = 64;

	const SERIALIZE_INCLUDE_CLASSIC = (self::SERIALIZE_INCLUDE_ID | self::SERIALIZE_INCLUDE_VALUE
		| self::SERIALIZE_INCLUDE_DEFAULT | self::SERIALIZE_INCLUDE_GROUP_TITLE
		| self::SERIALIZE_INCLUDE_POST_MESSAGE_SUPPORT
		| self::SERIALIZE_INCLUDE_TAGS
		| self::SERIALIZE_INCLUDE_VALIDATION
	);

	const SERIALIZE_LEAVES_ONLY = 128;

	const SERIALIZE_INCLUDE_SCHEMA = 1 << 8;

	const TAG_ADMIN_THEME = 'admin_theme';

	/**
	 * The data type could be useful for automatically choosing an appropriate
	 * control, if applicable.
	 *
	 * @var string
	 */
	protected $dataType = 'string';

	/**
	 * @var array
	 */
	protected $recommendedControls = [];

	/**
	 * @var null|callable
	 */
	protected $isEditableCallback = null;

	/**
	 * Whether to delete the setting from storage when its value is blank.
	 *
	 * Blank values are: NULL, '', empty array. Note that this is intentionally
	 * different from the behaviour of the empty() language construct which also
	 * considers 0.0, '0', and false to be empty.
	 *
	 * @var bool
	 */
	protected $deleteWhenBlank = false;

	/**
	 * @var callable[]
	 */
	protected $updateSubscribers = [];
	protected $isNotifyingSubscribers = false;

	/**
	 * The queue should be shared by all settings.
	 *
	 * @var null|UniqueSettingQueue
	 */
	protected static $updateNotificationQueue = null;

	/**
	 * @var bool Whether the setting supports updating its preview via postMessage.
	 */
	protected $supportsPostMessage = false;

	/**
	 * @var array<string> List of tags that can be used to group settings.
	 */
	protected $tags = [];

	public function __construct($id, ?StorageInterface $store = null, $params = []) {
		parent::__construct($id, $store, $params);

		if ( isset($params['isEditable']) && is_callable($params['isEditable']) ) {
			$this->isEditableCallback = $params['isEditable'];
		}
		if ( isset($params['deleteWhenBlank']) ) {
			$this->deleteWhenBlank = (bool)$params['deleteWhenBlank'];
		}
		if ( isset($params['supportsPostMessage']) ) {
			$this->supportsPostMessage = (bool)$params['supportsPostMessage'];
		}
		if ( isset($params['tags']) ) {
			$this->tags = $params['tags'];
		}
	}

	/**
	 * Validate and sanitize a setting value.
	 *
	 * On success, this method returns the sanitized value. If there is
	 * a validation error, it returns a WP_Error instance instead.
	 *
	 * @param \WP_Error $errors
	 * @param array<string,mixed>|mixed $value
	 * @param bool $stopOnFirstError Only applies to settings that have children. Other settings may ignore this parameter.
	 * @return \WP_Error|mixed
	 */
	abstract public function validate($errors, $value, $stopOnFirstError = false);

	/**
	 * Update the value of this setting.
	 *
	 * This method assumes that the value has already been validated and sanitized,
	 * and that any applicable permissions have been checked.
	 *
	 * This may not immediately write the new value to the database. Call the save()
	 * method on the underlying storage to ensure that the value is saved.
	 *
	 * @param $validValue
	 * @return boolean
	 */
	abstract public function update($validValue);

	/**
	 * @param mixed $customDefault
	 * @return mixed
	 */
	abstract public function getValue($customDefault = null);

	/**
	 * @return mixed
	 */
	abstract public function getDefaultValue();

	/**
	 * Enable preview mode for the current request. This will make the setting
	 * return the specified value instead of its actual value.
	 *
	 * Note: Usually, the preview value will be validated before it's passed
	 * to this method. However, in some cases, a value can be sent to the preview
	 * frame before it's saved in the changeset, so it will only have undergone
	 * JS-based validation, not full server-side validation. This means it's
	 * a good idea to validate the preview value even if that will sometimes
	 * duplicate work that has already been done.
	 *
	 * Also, even a value that has already been validated and saved in a changeset
	 * can occasionally become invalid later. For example, an image could be deleted
	 * from the media library. In exceptional cases, validation rules themselves
	 * could change as part of a plugin update.
	 *
	 * @param $unsafeValue
	 * @param \WP_Error|null $errors Optional. To avoid the creation of temporary
	 *                               WP_Error instances during value validation, you can provide an existing error
	 *                               object to this method.
	 *
	 * @return void
	 */
	public function preview($unsafeValue, $errors = null) {
		if ( $errors === null ) {
			$errors = new WP_Error();
		}

		$validationResult = $this->validate($errors, $unsafeValue, true);
		if ( is_wp_error($validationResult) ) {
			$previewValue = $this->getDefaultValue();
		} else {
			$previewValue = $validationResult;
		}

		if ( $this->store ) {
			$this->store->setPreviewValue($previewValue);
		}
	}

	/**
	 * Validate a value that has been submitted via an HTML form.
	 *
	 * For most settings, this is simply an alias for the `validate()` method.
	 * However, some settings may contain values that can't be directly represented
	 * in an HTML form, like `null` or objects. Values like that will need to be
	 * encoded/decoded when used in HTML. This method provides a way to decode
	 * and validate submitted form values.
	 *
	 * When the data comes from a form, you should use this method instead
	 * of `validate()` to ensure that the data is properly decoded.
	 *
	 * @param \WP_Error $errors
	 * @param array<string,mixed>|mixed $value
	 * @param bool $stopOnFirstError
	 * @return \WP_Error|mixed
	 */
	public function validateFormValue($errors, $value, $stopOnFirstError = false) {
		return $this->validate($errors, $value, $stopOnFirstError);
	}

	public function getDataType() {
		return $this->dataType;
	}

	public function getRecommendedControls() {
		return $this->recommendedControls;
	}

	/**
	 * Is the current user allowed to change this setting?
	 *
	 * @param EvaluationContext|null $context
	 * @return bool
	 */
	public function isEditableByUser(?EvaluationContext $context = null): bool {
		if ( isset($this->isEditableCallback) ) {
			return (bool)call_user_func($this->isEditableCallback);
		}
		return true;
	}

	/**
	 * Is it currently OK to delete this setting from storage?
	 *
	 * For example, some settings may choose to be removed when their value
	 * is empty, NULL, or equal to the default value.
	 *
	 * @return bool
	 */
	public function canBeDeleted() {
		if ( $this->deleteWhenBlank ) {
			$value = $this->getValue();
			if ( ($value === null) || ($value === '') ) {
				return true;
			} else if ( is_array($value) && empty($value) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Apply one or more validators to a setting value.
	 *
	 * Each validator should be a callable that takes two arguments:
	 * - The value to validate
	 * - A WP_Error object to which validation errors should be added.
	 *
	 * The callable should return the validated value, or a WP_Error instance
	 * if there was a validation error.
	 *
	 * @param callable[] $validators
	 * @param mixed $value
	 * @param \WP_Error $errors
	 * @param boolean $stopOnFirstError
	 * @return mixed|\WP_Error
	 */
	protected static function applyValidators($validators, $value, $errors, $stopOnFirstError = false) {
		$convertedValue = $value;
		$hasErrors = false;

		foreach ($validators as $validator) {
			$result = call_user_func($validator, $convertedValue, $errors);
			if ( is_wp_error($result) ) {
				$hasErrors = true;
				if ( $stopOnFirstError ) {
					return $result;
				}
			} else {
				$convertedValue = $result;
			}
		}

		return $hasErrors ? $errors : $convertedValue;
	}

	/**
	 * Notify the setting that its value has been updated.
	 *
	 * Usually, the setting itself will call this method from its update() method.
	 * If you change the value without calling the update() method, such as by
	 * directly updating a child of a struct, you should call this method manually.
	 *
	 * @return void
	 */
	public function notifyUpdated() {
		if ( $this->store && $this->canBeDeleted() ) {
			$this->store->deleteValue();
		}

		//Disallow recursive notifications as it could lead to infinite loops.
		//If necessary, we can change this later.
		if ( !$this->isNotifyingSubscribers ) {
			$this->isNotifyingSubscribers = true;

			//We'll notify subscribers now, so remove the setting from the notification
			//queue. This can be redundant if this method gets called while processing
			//the queue, but it's probably not a big deal.
			if ( static::$updateNotificationQueue ) {
				static::$updateNotificationQueue->remove($this);
			}

			foreach ($this->updateSubscribers as $callback) {
				call_user_func($callback, $this);
			}
			$this->isNotifyingSubscribers = false;
		}
	}

	/**
	 * @param callable $callback
	 * @return void
	 */
	public function subscribe($callback) {
		$this->updateSubscribers[] = $callback;
	}

	/**
	 * @return bool
	 */
	public function supportsPostMessage() {
		return $this->supportsPostMessage;
	}

	public function enablePostMessageSupport() {
		$this->supportsPostMessage = true;
		return $this;
	}

	//region Tags

	/**
	 * @return string[]
	 */
	public function getTags() {
		return $this->tags;
	}

	/**
	 * @param string[] $tags
	 * @return $this
	 */
	public function addTags(...$tags) {
		$this->tags = array_unique(array_merge($this->tags, $tags));
		return $this;
	}

	/**
	 * @param string $tag
	 * @return bool
	 */
	public function hasTag($tag) {
		return in_array($tag, $this->tags);
	}

	//endregion

	/**
	 * @return array|null
	 */
	public function serializeValidationRules() {
		return null; //It's up to subclasses to implement this.
	}

	protected static function getNotificationQueue() {
		if ( !static::$updateNotificationQueue ) {
			static::$updateNotificationQueue = new UniqueSettingQueue();
		}
		return static::$updateNotificationQueue;
	}

	public static function sendPendingNotifications() {
		if ( !static::$updateNotificationQueue ) {
			return;
		}

		$queue = static::$updateNotificationQueue;
		$queue->processAll();
	}

	/**
	 * @param AbstractSetting[] $settings
	 * @return void
	 */
	public static function saveAll($settings) {
		static::sendPendingNotifications();

		//Find and deduplicate the stores that contain these settings.
		$stores = new \SplObjectStorage();
		foreach ($settings as $setting) {
			if ( $setting->store ) {
				$stores[$setting->store->getSmallestSavable()] = null;
			}
		}

		//Tell each store to save its data.
		foreach ($stores as $store) {
			/** @var StorageInterface $store */
			$store->save();
		}
	}

	/**
	 * @param AbstractSetting[] $settingsToWatch
	 * @param callable $callback Expected signature: function($updatedSettings) => void
	 */
	public static function subscribeDeferred($settingsToWatch, $callback) {
		if ( empty($settingsToWatch) ) {
			return;
		}

		$queue = static::getNotificationQueue();
		new DeferredUpdateSubscriber($queue, $settingsToWatch, $callback);
		//The subscriber object doesn't need to be stored anywhere because it will
		//automatically add itself as a subscriber to each setting.
	}

	/**
	 * Recursively iterate over a collection of settings.
	 *
	 * This method will not recurse into composite settings, but it will return
	 * the children of regular structs.
	 *
	 * @param iterable $settings
	 * @param bool $leavesOnly When encountering a struct setting, only return its
	 *                                   children and not the struct itself. Does not apply
	 *                                   to composite settings.
	 * @param string|int|null $parentKey The key of the parent setting. Used to generate
	 *                                   an iterator key for each setting.
	 * @return \Generator
	 */
	public static function recursivelyIterateSettings($settings, $leavesOnly = false, $parentKey = null) {
		foreach ($settings as $key => $setting) {
			if ( $parentKey !== null ) {
				$effectiveKey = ((string)$parentKey) . '.' . $key;
			} else {
				$effectiveKey = $key;
			}

			//Descend into structs and arrays, except composite settings.
			//WP 4.9.6+ includes a polyfill for is_iterable().
			$isContainer = is_iterable($setting) && !($setting instanceof CompositeSetting);

			if ( ($setting instanceof AbstractSetting) && (!$isContainer || !$leavesOnly) ) {
				yield $effectiveKey => $setting;
			}

			if ( $isContainer ) {
				/** @var iterable $setting */
				yield from self::recursivelyIterateSettings($setting, $leavesOnly, $effectiveKey);
			}
		}
	}

	/**
	 * Recursively serialize a collection of settings for use in JavaScript.
	 *
	 * Optionally, you can provide a callback that will be called for each setting.
	 * It can be used to modify the serialized data. The callback will be called
	 * with two arguments:
	 * - The serialized data as an associative array.
	 * - The setting object.
	 *
	 * The callback should return an associative array. Alternatively, it can return
	 * `null` to exclude the setting from the result.
	 *
	 * @param AbstractSetting[] $settings
	 * @param int|null $flags
	 * @param callable|null $customizer Optional. A callback that can be used to modify each
	 *                             setting's serialized data.
	 * @param SchemaSerializer|null $schemaSerializer
	 * @return array<string,array> A map of setting IDs to serialized settings.
	 */
	public static function serializeSettingsForJs(
		array            $settings,
		?int             $flags = self::SERIALIZE_INCLUDE_CLASSIC,
		?callable        $customizer = null,
		?SchemaSerializer $schemaSerializer = null
	) {
		//Right now, the serialization process is fairly straightforward, so we
		//just do it here. If it becomes more complex, we could add a serializeForJs()
		//method to individual settings, or add a separate serializer class.
		if ( $flags === null ) {
			$flags = self::SERIALIZE_INCLUDE_CLASSIC;
		}

		$leavesOnly = (bool)($flags & self::SERIALIZE_LEAVES_ONLY);

		$serialized = [];
		foreach (self::recursivelyIterateSettings($settings, $leavesOnly) as $setting) {
			$emptyArraysAsObjects = ($setting->getDataType() === 'map');

			$data = [];
			if ( $flags & self::SERIALIZE_INCLUDE_ID ) {
				$data['id'] = $setting->id;
			}
			if ( $flags & self::SERIALIZE_INCLUDE_DEFAULT ) {
				$data['default'] = $setting->getDefaultValue();
				if ( $emptyArraysAsObjects && is_array($data['default']) && empty($data['default']) ) {
					$data['default'] = (object)$data['default'];
				}
			}
			if ( $flags & self::SERIALIZE_INCLUDE_VALUE ) {
				//Note: This will use the previewed value if one is available.
				$data['value'] = $setting->getValue();
				if ( $emptyArraysAsObjects && is_array($data['value']) && empty($data['value']) ) {
					$data['value'] = (object)$data['value'];
				}
			}

			if ( $flags & self::SERIALIZE_INCLUDE_GROUP_TITLE ) {
				$groupTitle = $setting->getCustomGroupTitle();
				if ( ($groupTitle !== null) && ($groupTitle !== '') ) {
					$data['groupTitle'] = $groupTitle;
				}
			}

			if ( $flags & self::SERIALIZE_INCLUDE_POST_MESSAGE_SUPPORT ) {
				if ( $setting->supportsPostMessage() ) {
					$data['supportsPostMessage'] = true;
				}
			}

			if ( $flags & self::SERIALIZE_INCLUDE_TAGS ) {
				$tags = $setting->getTags();
				if ( !empty($tags) ) {
					$data['tags'] = $tags;
				}
			}

			if ( $flags & self::SERIALIZE_INCLUDE_VALIDATION ) {
				$validationRules = $setting->serializeValidationRules();
				if ( !empty($validationRules) ) {
					$data['validation'] = $validationRules;
				}
			}

			if ( $flags & self::SERIALIZE_INCLUDE_SCHEMA && $schemaSerializer ) {
				if ( $setting instanceof WithSchema\SettingWithSchema ) {
					$schema = $setting->getSchema();
					if ( $schema ) {
						$data['schema'] = $schemaSerializer->serialize($schema);
					}
				}
			}

			if ( $customizer ) {
				$data = call_user_func($customizer, $data, $setting);
				//Skip settings excluded by the callback.
				if ( $data === null ) {
					continue;
				}
			}

			$serialized[$setting->id] = $data;
		}
		return $serialized;
	}

	/**
	 * Recursively serialize a collection of settings for use in the revised UI JavaScript.
	 *
	 * @param AbstractSetting[] $settings
	 * @return array<string,array>  An associative array with two keys:
	 *                               - 'settings': The serialized settings.
	 *                               - 'sharedSchemas': The shared serialized schemas.
	 */
	public static function serializeSettingsForRevisedJs(array $settings): array {
		$schemaSerializer = new SchemaSerializer();
		$serialized = self::serializeSettingsForJs(
			$settings,
			self::SERIALIZE_INCLUDE_CLASSIC | self::SERIALIZE_INCLUDE_SCHEMA,
			null,
			$schemaSerializer
		);

		//Remove any settings that are children of other settings in the list. This both avoids sending
		//redundant data to the client and prevents the client from trying to create child settings before
		//their parents.
		//(Unlike the old implementation, the revised UI builds a tree of settings, not a flat list.)
		$roots = [];
		foreach ($serialized as $id => $data) {
			$keep = true;

			$idPrefix = $id;
			while (($pos = strrpos($idPrefix, '.')) !== false) {
				$idPrefix = substr($idPrefix, 0, $pos);
				if ( isset($serialized[$idPrefix]) ) {
					$keep = false;
					break;
				}
			}

			if ( $keep ) {
				$roots[$id] = $data;
			}
		}

		return [
			'settings'      => $roots,
			'sharedSchemas' => $schemaSerializer->getSharedSerializedSchemas(),
		];
	}

	/**
	 * Convert a setting value to a string usable in HTML forms.
	 *
	 * This does NOT encode special HTML characters. It is only intended to convert
	 * non-string values - like booleans and NULLs - to a format suitable for form field.
	 *
	 * @param $value
	 * @return string
	 */
	public function encodeForForm($value) {
		//Subclasses that require more advanced encoding should override this method.
		return (string)$value;
	}

	/**
	 * Convert submitted form data to a type suitable for validation.
	 * This is not necessarily the same as the setting data type.
	 *
	 * @param string $value
	 */
	public function decodeSubmittedValue($value) {
		//The default implementation is a no-op. Subclasses can override this.
		return $value;
	}

	public function resolveLabel(?EvaluationContext $context = null): string {
		return $this->getLabel();
	}

	public function resolveDescription(?EvaluationContext $context = null): string {
		return $this->getDescription();
	}

	public function serializeLabelForJs(?EvaluationContext $context = null): string {
		return $this->getLabel();
	}

	public function serializeDescriptionForJs(?EvaluationContext $context = null): string {
		return $this->getDescription();
	}

	public function getBindingString(): string {
		return $this->getId();
	}

	public function getInternalStringId(): string {
		return $this->getId();
	}

	const RESOLUTION_KEY = 'setting';

	public function getResolutionStrategyKey(): string {
		return self::RESOLUTION_KEY;
	}
}