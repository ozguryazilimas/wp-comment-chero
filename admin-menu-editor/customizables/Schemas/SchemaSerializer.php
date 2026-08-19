<?php

namespace YahnisElsts\AdminMenuEditor\Customizable\Schemas;

class SchemaSerializer {
	/**
	 * @var array<int, array> Serialized schemas that have already been seen, by PHP object ID.
	 */
	protected array $seenSerializedSchemas = [];
	/**
	 * @var array<int, string> Map from schema object IDs to shorter IDs.
	 */
	protected array $shortIds = [];
	/**
	 * @var array<string, array> Shared serialized schemas, by short ID.
	 */
	protected array $sharedSchemas = [];
	protected bool $schemaSharingEnabled;

	public function __construct(bool $schemaSharingEnabled = true) {
		$this->schemaSharingEnabled = $schemaSharingEnabled;
	}

	public function serialize(Schema $schema): array {
		$schemaId = spl_object_id($schema);
		if ( $this->schemaSharingEnabled ) {
			if ( isset($this->seenSerializedSchemas[$schemaId]) ) {
				if ( !isset($this->shortIds[$schemaId]) ) {
					//Generate a short ID. The object ID or hash would be unique, but it's too long.
					$shortId = 's' . count($this->shortIds);
					$this->shortIds[$schemaId] = $shortId;
					$this->sharedSchemas[$shortId] = $this->seenSerializedSchemas[$schemaId];
				}
				//If we've already seen this schema, return a reference to it.
				return ['_ref' => $this->shortIds[$schemaId]];
			}
		}

		$this->seenSerializedSchemas[$schemaId] = $schema->serialize($this);
		return $this->seenSerializedSchemas[$schemaId];
	}

	public function getSharedSerializedSchemas(): array {
		return $this->sharedSchemas;
	}
}