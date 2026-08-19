<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Storage;

use ameMultiDictionary;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\ElementBuilderFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Builders\SettingFactory;
use YahnisElsts\AdminMenuEditor\Customizable\Controls\Binding;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\AbstractSetting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\PredefinedSet;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\Setting;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\SettingContainer;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\SettingGeneratorInterface;
use YahnisElsts\AdminMenuEditor\Customizable\Settings\StringSetting;
use YahnisElsts\AdminMenuEditor\WireDSL\Wire;

/**
 * What's the difference between this and StorageInterface? A StorageInterface just
 * reads and writes data. It doesn't know what the data is, how it's organized, or
 * how to validate it.
 *
 * This class, on the other hand, is dictionary (or associative array) with string keys,
 * potentially multidimensional. It can have predefined defaults, and it can create
 * Setting instances for its keys.
 */
abstract class AbstractSettingsDictionary implements \ArrayAccess, \JsonSerializable, SettingContainer {
	/**
	 * @var array
	 */
	protected $defaults;
	/**
	 * @var StorageInterface
	 */
	protected $store;
	/**
	 * @var null|AbstractSetting[]
	 */
	protected $registeredSettings = null;
	/**
	 * @var null|\YahnisElsts\AdminMenuEditor\Customizable\Settings\PredefinedSet[]
	 */
	protected $registeredSets = null;
	/**
	 * @var string Optional ID prefix that can be added to setting IDs
	 * to make them globally unique.
	 */
	protected $idPrefix;

	/**
	 * @var object
	 */
	protected $undefinedMarker;

	/**
	 * @var bool Whether to automatically track the last modification time.
	 */
	protected $lastModifiedTimeEnabled = false;
	/**
	 * @var null|Setting
	 */
	protected $lastModifiedSetting = null;

	const LAST_MODIFIED_KEY = '_lastModified';

	public function __construct(StorageInterface $store, $idPrefix = '', $lastModifiedTimeEnabled = false) {
		$this->store = $store;
		$this->idPrefix = $idPrefix;
		$this->lastModifiedTimeEnabled = $lastModifiedTimeEnabled;
		$this->defaults = $this->createDefaults();
		$this->undefinedMarker = new \StdClass();
	}

	/**
	 * @return array<string,mixed>
	 */
	abstract protected function createDefaults();

	/**
	 * @return array<string,Setting> Settings indexed by their ID.
	 */
	abstract protected function createSettings();

	/**
	 * Get the value of a setting.
	 *
	 * Note that NULLs are treated as valid values. The fallback value will only
	 * be used if the setting is actually missing, not if it's set to NULL.
	 *
	 * @param string|string[] $path
	 * @param mixed $fallback
	 * @return mixed
	 */
	public function get($path, $fallback = null) {
		//Try the storage.
		$result = $this->store->getPath($path, $this->undefinedMarker);
		if ( $result !== $this->undefinedMarker ) {
			return $result;
		}
		//Try predefined defaults.
		return $this->getDefault($path, $fallback);
	}

	public function set($path, $value) {
		$this->store->setPath($path, $value);
	}

	/**
	 * @param string $path
	 * @param mixed $fallback
	 * @return mixed
	 */
	protected function getDefault($path, $fallback = null) {
		return ameMultiDictionary::get($this->defaults, $path, $fallback);
	}

	/**
	 * @return array<string,AbstractSetting>
	 */
	public function getRegisteredSettings() {
		if ( $this->registeredSettings === null ) {
			$this->populateSettingInstances();
		}
		return $this->registeredSettings;
	}

	/**
	 * @return array<string,PredefinedSet>
	 */
	public function getRegisteredSets() {
		if ( $this->registeredSets === null ) {
			$this->populateSettingInstances();
		}
		return $this->registeredSets;
	}

	private function populateSettingInstances() {
		list($this->registeredSettings, $this->registeredSets)
			= $this->flattenSettingsCollection($this->createSettings());

		if ( $this->lastModifiedTimeEnabled ) {
			$settingsWithoutLastModified = $this->registeredSettings;

			$path = self::LAST_MODIFIED_KEY;
			$this->lastModifiedSetting = new StringSetting(
				$this->idPrefix . $path,
				$this->store->buildSlot($path),
				[]
			);
			$this->registeredSettings[$this->lastModifiedSetting->getId()] = $this->lastModifiedSetting;

			AbstractSetting::subscribeDeferred($settingsWithoutLastModified, function () {
				$this->lastModifiedSetting->update(gmdate('c'));
			});
		}
	}

	/**
	 * Flatten a collection of settings and index it by ID.
	 *
	 * Also detects predefined sets present in the collection and adds them
	 * to a separate array indexed by ID.
	 *
	 * @param array|\Traversable $settings
	 * @return array{0: array<string,AbstractSetting>, 1: array<string,PredefinedSet>}
	 */
	private function flattenSettingsCollection($settings) {
		$foundSettings = [];
		$foundSets = [];
		$this->addSettingsToCollection($foundSettings, $foundSets, $settings);
		return [$foundSettings, $foundSets];
	}

	/**
	 * @param array $outputCollection
	 * @param array $detectedSets
	 * @param array|\Traversable $inputCollection
	 * @return void
	 */
	private function addSettingsToCollection(&$outputCollection, &$detectedSets, $inputCollection) {
		foreach ($inputCollection as $item) {
			if ( empty($item) ) {
				continue;
			}
			if ( $item instanceof PredefinedSet ) {
				$detectedSets[$item->getId()] = $item;
			}

			if ( $item instanceof AbstractSetting ) {
				$outputCollection[$item->getId()] = $item;
			} else if ( is_array($item) || ($item instanceof SettingGeneratorInterface) ) {
				$this->addSettingsToCollection($outputCollection, $detectedSets, $item);
			} else {
				throw new \InvalidArgumentException(
					'Unexpected item type in a setting collection: '
					. is_object($item) ? get_class($item) : gettype($item)
				);
			}
		}
	}

	/**
	 * Like findSetting(), but throws an exception if the setting doesn't exist.
	 *
	 * @param string $settingIdOrPath
	 * @return AbstractSetting
	 */
	public function getSetting($settingIdOrPath) {
		$result = $this->findSetting($settingIdOrPath);
		if ( $result !== null ) {
			return $result;
		}

		throw new \InvalidArgumentException("Unknown setting: $settingIdOrPath");
	}

	/**
	 * Find a setting by ID or path.
	 *
	 * @param $settingIdOrPath
	 * @return AbstractSetting|null
	 */
	public function findSetting($settingIdOrPath): ?AbstractSetting {
		$settings = $this->getRegisteredSettings();

		//Try the plain ID.
		/** @noinspection PhpRedundantOptionalArgumentInspection */
		$result = ameMultiDictionary::get($settings, $settingIdOrPath, null);
		if ( $result !== null ) {
			return $result;
		}

		//Try the ID with the prefix.
		if ( !empty($this->idPrefix) && is_string($settingIdOrPath) ) {
			/** @noinspection PhpRedundantOptionalArgumentInspection */
			$result = ameMultiDictionary::get($settings, $this->idPrefix . $settingIdOrPath, null);
			if ( $result !== null ) {
				return $result;
			}
		}

		return null;
	}

	function findDirectChildSetting(string $key): ?AbstractSetting {
		return $this->findSetting($key);
	}

	/**
	 * @param string $setIdOrPath
	 * @return PredefinedSet
	 */
	public function getPredefinedSet($setIdOrPath) {
		if ( isset($this->registeredSets[$setIdOrPath]) ) {
			return $this->registeredSets[$setIdOrPath];
		}

		if ( !empty($this->idPrefix) ) {
			$idWithPrefix = $this->idPrefix . $setIdOrPath;
			if ( isset($this->registeredSets[$idWithPrefix]) ) {
				return $this->registeredSets[$idWithPrefix];
			}
		}

		$setting = ameMultiDictionary::get($this->getRegisteredSettings(), $setIdOrPath);
		if ( $setting instanceof PredefinedSet ) {
			return $setting;
		}

		throw new \InvalidArgumentException("Unknown set: $setIdOrPath");
	}

	/**
	 * Get the default values of all registered settings (recursive).
	 *
	 * Note: The intent is to return the defaults in a format that can be safely
	 * JSON-encoded and passed to JavaScript. This means that empty associative
	 * arrays and structs are converted to empty objects.
	 *
	 * @return array<string,mixed> A map of setting IDs to their default values.
	 */
	public function getRecursiveDefaultsForJs() {
		//Generate a map of all supported settings and their defaults.
		$settings = $this->getRegisteredSettings();
		$defaults = [];
		foreach (AbstractSetting::recursivelyIterateSettings($settings) as $setting) {
			$defaultValue = $setting->getDefaultValue();

			//wp_json_encode() encodes empty associative arrays as plain JS arrays,
			//but we need empty objects. We can't distinguish between an empty associative
			//array and a normal array, so we also need to check the setting's data type.
			if ( is_array($defaultValue) && empty($defaultValue) && ($setting->getDataType() === 'map') ) {
				$defaultValue = new \stdClass();
			}

			$defaults[$setting->getId()] = $defaultValue;
		}
		return $defaults;
	}

	public function save() {
		$this->store->save();
	}

	/**
	 * @param array<string,string|string[]> $aliases
	 * @return void
	 */
	public function addReadAliases($aliases) {
		$this->store->addReadAliases($aliases);
	}

	/**
	 * Merge the elements of this setting collection and an associative array.
	 *
	 * This is not a recursive merge. The input array will simply overwrite any
	 * settings that have the same keys.
	 *
	 * @param array $newSettings
	 * @return void
	 */
	public function mergeWith($newSettings) {
		$oldSettings = $this->toArray();
		$this->store->setValue(array_merge($oldSettings, $newSettings));
	}

	/**
	 * @noinspection PhpLanguageLevelInspection
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		return $this->get($offset);
	}

	/**
	 * @noinspection PhpLanguageLevelInspection
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value) {
		$this->set($offset, $value);
	}

	/**
	 * @noinspection PhpLanguageLevelInspection
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset) {
		$this->store->deletePath($offset);
	}

	/**
	 * @noinspection PhpLanguageLevelInspection
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists($offset) {
		/*
		 * Caution: This implementation breaks the implied contract for NULL values.
		 * PHP seems to assume that offsetExists() will return false when the offset
		 * exists but the value is NULL. For example, isset() doesn't bother calling
		 * offsetGet() to check the actual value when offsetExists() returns true.
		 *
		 * This version may return true instead (depending on the underlying storage
		 * implementation).
		 *
		 * Unlike isset(), empty() still works correctly.
		 */
		return ($this->get($offset, $this->undefinedMarker) !== $this->undefinedMarker);
	}

	/** @noinspection PhpLanguageLevelInspection */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$data = $this->store->getValue();
		if ( empty($data) ) {
			//Usually, json_encode() will serialize an empty array as "[]", but
			//we want "{}" in case it gets used in JavaScript.
			return new \StdClass();
		}
		return $data;
	}

	public function toArray() {
		$value = $this->store->getValue();
		if ( empty($value) ) {
			return array();
		}
		return (array)$value;
	}

	/**
	 * Does this collection have custom values for any settings?
	 *
	 * A true result does not necessarily mean that the custom values are different
	 * from the defaults, only that some settings have been set/changed.
	 *
	 * @return bool
	 */
	public function hasCustomValues() {
		$data = $this->store->getValue();
		return !empty($data);
	}

	/**
	 * @return int|null
	 */
	public function getLastModifiedTimestamp() {
		if ( !$this->lastModifiedTimeEnabled ) {
			return null;
		}
		$isoTimestamp = $this->get(self::LAST_MODIFIED_KEY);
		if ( empty($isoTimestamp) ) {
			return null;
		}
		return strtotime($isoTimestamp);
	}

	/**
	 * @return string
	 */
	public function getIdPrefix() {
		return $this->idPrefix;
	}


	public function elementBuilder() {
		return new ElementBuilderFactory($this);
	}

	public function settingFactory() {
		return new SettingFactory($this->store, $this->defaults, $this->idPrefix);
	}

	public function ref($settingIdOrPath): Binding {
		return Wire::bind($settingIdOrPath);
	}
}