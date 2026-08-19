<?php

namespace YahnisElsts\AdminMenuEditor\WireDSL;

interface Resolvable {
	public function getInternalStringId(): string;

	public function getResolutionStrategyKey(): string;

	//todo: Maybe a method for getting a serializable representation, for settings that are themselves
	// not directly JSON-serializable.
}