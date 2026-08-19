<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Settings;

class NotificationSenderQueue {
	/**
	 * @var \SplObjectStorage<UpdateNotificationSender, bool>
	 */
	protected $isInQueue;
	/**
	 * @var \SplQueue<UpdateNotificationSender>
	 */
	protected $queue;

	public function __construct() {
		$this->queue = new \SplQueue();
		$this->isInQueue = new \SplObjectStorage();
	}

	/**
	 * Add a sender to the queue.
	 *
	 * If it's already in the queue, it won't be added again, and its position in the queue won't change.
	 *
	 * @param UpdateNotificationSender $setting
	 */
	public function enqueue(UpdateNotificationSender $setting) {
		if ( !$this->isInQueue->offsetExists($setting) ) {
			$this->queue->enqueue($setting);
		}
		$this->isInQueue[$setting] = true;
	}

	public function dequeue() {
		//Find and return the first valid (non-removed) item.
		while (!$this->queue->isEmpty()) {
			$sender = $this->queue->dequeue();
			if ( $this->isInQueue[$sender] ) {
				$this->isInQueue->offsetUnset($sender);
				return $sender;
			}
		}

		return null;
	}

	public function remove(UpdateNotificationSender $setting) {
		if ( $this->isInQueue->offsetExists($setting) ) {
			//There's not a quick way to remove an element from a SplQueue,
			//so we'll just mark the item as invalid. It will be removed
			//in dequeue().
			$this->isInQueue[$setting] = false;
		}
	}

	public function isEmpty() {
		return ($this->queue->isEmpty() || ($this->isInQueue->count() < 1));
	}
}