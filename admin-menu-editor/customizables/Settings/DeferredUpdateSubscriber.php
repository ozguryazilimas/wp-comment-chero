<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Settings;

class DeferredUpdateSubscriber implements UpdateNotificationSender {
	/**
	 * @var \SplObjectStorage<AbstractSetting, bool>
	 */
	protected $updatedSettings;

	/**
	 * @var callable
	 */
	protected $callback;

	/**
	 * @var UniqueSettingQueue
	 */
	protected $ownerQueue;

	/**
	 * @param UniqueSettingQueue $ownerQueue
	 * @param AbstractSetting[] $watchedSettings
	 * @param callable $callback
	 */
	public function __construct(UniqueSettingQueue $ownerQueue, $watchedSettings, callable $callback) {
		$this->updatedSettings = new \SplObjectStorage();
		$this->callback = $callback;
		$this->ownerQueue = $ownerQueue;

		$settingUpdateHandler = [$this, 'receiveNotification'];
		foreach ($watchedSettings as $setting) {
			$setting->subscribe($settingUpdateHandler);
		}
	}

	public function receiveNotification(AbstractSetting $setting) {
		if ( !$this->updatedSettings->offsetExists($setting) ) {
			$this->updatedSettings[$setting] = true;
			$this->ownerQueue->enqueueDeferred($this);
		}
	}

	public function notifyUpdated() {
		$settingsAsArray = [];
		foreach ($this->updatedSettings as $setting) {
			$settingsAsArray[] = $setting;
		}
		$this->updatedSettings = new \SplObjectStorage();

		//Remove this object from the queue in case notifyUpdated() was called
		//directly instead of via the queue.
		$this->ownerQueue->remove($this);

		call_user_func($this->callback, $settingsAsArray);
	}
}