<?php

namespace Drupal\entity_import\Entity;

/**
 * Define the entity importer interface.
 */
interface EntityImporterInterface {

  /**
   * On change to the importer.
   */
  public function onChange();

  /**
   * Get entity importer description.
   *
   * @return mixed
   *   The entity importer description.
   */
  public function getDescription();

  /**
   * Get entity importer display page.
   *
   * @return bool
   *   The entity importer display page boolean.
   */
  public function getDisplayPage();

  /**
   * Get entity importer source information.
   *
   * @return array
   *   An array of source information.
   */
  public function getSourceInfo();

  /**
   * Get entity importer entity information.
   *
   * @return array
   *   An array of entity information.
   */
  public function getEntityInfo();

  /**
   * Get the migration plugin id.
   *
   * @param $bundle
   *   The bundle on which to retrieve.
   *
   * @return string
   *   The migration plugin ID.
   */
  public function getMigrationPluginId($bundle);

  /**
   * Get importer source plugin id.
   *
   * @return mixed|NULL
   *   The importer source plugin id.
   */
  public function getImporterSourcePluginId();

  /**
   * Get importer source configuration.
   *
   * @return array
   *   The importer source plugin configuration.
   */
  public function getImporterSourceConfiguration();

  /**
   * Get importer entity bundles.
   *
   * @return array
   *   An array of allowed entity bundles.
   */
  public function getImporterBundles();

  /**
   * Get importer entity type.
   *
   * @return mixed|NULL
   *   The importer entity type.
   */
  public function getImporterEntityType();

  /**
   * Has importer page display changed.
   *
   * @return boolean
   *   Determine if the page display has changed.
   */
  public function hasPageDisplayChanged();

  /**
   * Create migration instance.
   *
   * @param $bundle
   *   The entity bundle name.
   * @param array $definition
   *   Additional definitions that should be merged.
   *
   * @return \Drupal\migrate\Plugin\Migration|\Drupal\migrate\Plugin\MigrationPluginManager
   */
  public function createMigrationInstance($bundle, array $definition = []);

  /**
   * Get entity importer first bundle.
   *
   * @return string
   */
  public function getFirstBundle();

  /**
   * Entity importer has multiple bundles.
   *
   * @return bool
   */
  public function hasMultipleBundles();

  /**
   * Get entity importer field mapping.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFieldMapping();

  /**
   * Has field mappings.
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasFieldMappings();

  /**
   * Get field mapping options.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFieldMappingOptions();

  /**
   * Get field mapping unique identifiers.
   *
   * @return array
   *   An array of field mapping unique identifiers.
   */
  public function getFieldMappingUniqueIdentifiers();
}
