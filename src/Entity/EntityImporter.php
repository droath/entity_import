<?php

namespace Drupal\entity_import\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\Annotation\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Define entity configuration importer.
 *
 * @ConfigEntityType(
 *   id = "entity_importer",
 *   label = @Translation("Entity Importer"),
 *   config_prefix = "type",
 *   admin_permission = "administer entity import",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "form" = {
 *       "add" = "\Drupal\entity_import\Form\EntityImporterForm",
 *       "edit" = "\Drupal\entity_import\Form\EntityImporterForm",
 *       "delete" = "\Drupal\entity_import\Form\EntityImporterDeleteForm",
 *     },
 *     "list_builder" = "\Drupal\entity_import\Controller\EntityImporterList",
 *     "route_provider" = {
 *       "html" = "\Drupal\entity_import\Entity\Routing\EntityImporterRouteDefault"
 *     }
 *   },
 *   links = {
 *     "collection" = "/admin/config/system/entity-importer",
 *     "add-form" = "/admin/config/system/entity-importer/add",
 *     "edit-form" = "/admin/config/system/entity-importer/{entity_importer}/edit",
 *     "delete-form" = "/admin/config/system/entity-importer/{entity_importer}/delete"
 *   }
 * )
 */
class EntityImporter extends EntityImporterConfigEntityBase implements EntityImporterInterface {

  /**
   * @var string
   */
  public $id;

  /**
   * @var string
   */
  public $label;

  /**
   * @var bool
   */
  public $display_page;

  /**
   * @var string
   */
  public $description;

  /**
   * @var array
   */
  public $source = [];

  /**
   * @var array
   */
  public $entity = [];

  /**
   * @var bool
   */
  protected $pageDisplayChanged;

  /**
   * {@inheritdoc}
   */
  public function getDisplayPage() {
    return $this->display_page;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceInfo() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityInfo() {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getMigrationPluginId($bundle) {
    return "entity_import:{$this->id()}:{$bundle}";
  }

  /**
   * {@inheritdoc}
   */
  public function getImporterSourcePluginId() {
    return $this->getSourceInfoValue('plugin_id');
  }

  /**
   * {@inheritdoc}
   */
  public function getImporterSourceConfiguration() {
    return $this->getSourceInfoValue('configuration', []);
  }

  /**
   * {@inheritdoc}
   */
  public function getImporterBundles() {
    return $this->getEntityInfoValue('bundles', []);
  }

  /**
   * {@inheritdoc}
   */
  public function getImporterEntityType() {
    return $this->getEntityInfoValue('type', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPageDisplayChanged() {
    return $this->pageDisplayChanged;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $this->pageDisplayChanged = $this->display_page == TRUE ?: FALSE;

    if (!$this->isNew()) {
      /** @var \Drupal\entity_import\Entity\EntityImporter $original */
      $original = $storage->loadUnchanged($this->getOriginalId());
      $this->pageDisplayChanged = $this->display_page != $original->display_page
        ?: FALSE;
    }
  }
  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $this->onChange();
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    $this
      ->deleteFieldMapping()
      ->deleteFieldMappingOption();

    $this->onChange();
  }

  /**
   * {@inheritdoc}
   */
  public function onChange() {
    $this->clearMigrationPluginDiscovery();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  function calculateDependencies() {
    parent::calculateDependencies();

    if (!empty($this->getFieldMappingOptionConfig()->get())) {
      $this->addDependency('config', $this->getFieldMappingOptionConfigName());
    }

    foreach ($this->getFieldMapping() as $field_mapping) {
      $this->addDependency('config', $field_mapping->getConfigDependencyName());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createMigrationInstance($bundle, array $definition = []) {
    $migration_definition = $this->getMigrationDefinition($bundle, $definition);

    return $this
      ->migrationPluginManager()
      ->createStubMigration($migration_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstBundle() {
    $bundles = $this->getImporterBundles();
    return reset($bundles);
  }

  /**
   * {@inheritdoc}
   */
  public function hasFieldMappings() {
    return !empty($this->getFieldMapping());
  }

  /**
   * {@inheritdoc}
   */
  public function hasMultipleBundles() {
    return count($this->getImporterBundles()) > 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappingOptions() {
    $options = [];

    foreach ($this->getFieldMapping() as $field_mapping) {
      $options[$field_mapping->name] = $field_mapping->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping() {
    return $this->getFieldMappingStorage()
      ->loadMultiple($this->getFieldMappingByImporterType());
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappingUniqueIdentifiers() {
    return $this
      ->getFieldMappingOptionsConfig()
      ->get('unique_identifiers.items');
  }

  /**
   * Get dependency migrations.
   *
   * @param $bundle
   *   The entity bundle.
   * @param bool $order
   *   Order the migrations.
   * @param array $definition
   *   An array of migration definition values.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDependencyMigrations(
    $bundle,
    $order = TRUE,
    $definition = []
  ) {
    $migrations = $this->createDependencyMigrations(
      $this->createMigrationInstance($bundle, $definition)
    );

    return !$order ? $migrations : array_reverse($migrations);
  }

  /**
   * Create dependency migrations.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The required migration instance.
   * @param array $values
   * @param array $parents
   * @param array $migrations
   *
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createDependencyMigrations(
    MigrationInterface $migration,
    $values = [],
    $parents = [],
    &$migrations = []
  ) {
    $migrations[$migration->id()] = $migration;

    foreach ($migration->getMigrationDependencies()['optional'] as $plugin_id) {
      $configuration = NestedArray::getValue($values, $parents);

      /** @var MigrationInterface $instance */
      $instance = $this
        ->migrationPluginManager()
        ->createInstance($plugin_id, $configuration);

      return $this->createDependencyMigrations($instance, $values, $parents, $migrations);
    }

    return $migrations;
  }

  /**
   * Get field mapping option configuration.
   *
   * @param bool $editable
   *   Determine if the field mapping configurations are editable.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getFieldMappingOptionConfig($editable = FALSE) {
    $name = $this->getFieldMappingOptionConfigName();
    $factory = static::getConfigManager()->getConfigFactory();

    return $editable ? $factory->getEditable($name) : $factory->get($name);
  }

  /**
   * Get field mapping option config name.
   *
   * @return string
   *   The field mapping option config name.
   */
  protected function getFieldMappingOptionConfigName() {
    return 'entity_import.field_mapping.options.'. $this->id();
  }

  /**
   * Delete field mapping options.
   *
   * @return $this
   */
  protected function deleteFieldMappingOption() {
    $this->getFieldMappingOptionConfig(TRUE)->delete();

    return $this;
  }

  /**
   * Delete field mappings.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteFieldMapping() {
    foreach ($this->getFieldMapping() as $field_mapping) {
      $field_mapping->delete();
    }

    return $this;
  }

  /**
   * Delete migration plugin discovery cache.
   */
  protected function clearMigrationPluginDiscovery() {
    $this
      ->getMigrationDiscoveryCache()
      ->delete('migration_plugins');

    return $this;
  }

  /**
   * Get field mapping by bundle.
   *
   * @param $bundle
   *   The bundle name.
   *
   * @return array|mixed
   */
  protected function getFieldMappingByBundle($bundle) {
    $field_mapping = $this->getFieldMappingsKeyedByBundle();

    return isset($field_mapping[$bundle])
      ? $field_mapping[$bundle]
      : [];
  }

  /**
   * Get field mapping keyed by bundle.
   */
  protected function getFieldMappingsKeyedByBundle() {
    $field_mappings = [];

    /** @var \Drupal\entity_import\Entity\EntityImporterFieldMapping $field_mapping */
    foreach ($this->getFieldMapping() as $entity_type_id => $field_mapping) {
      $field_mappings[$field_mapping->getImporterBundle()][] = $field_mapping;
    }

    return $field_mappings;
  }

  /**
   * Get the migration definition.
   *
   * @param $bundle
   *   The entity bundle type.
   * @param array $definition
   *
   * @return mixed
   */
  protected function getMigrationDefinition($bundle, array $definition = []) {
    $migration_definition = [
      'id' => $this->getMigrationPluginId($bundle),
      'label' => $this->t('@label: @bundle', [
        '@label' => $this->label(),
        '@bundle' => $bundle,
      ]),
      'source' => $this->getMigrateSourceDefinition(),
      'process' => $this->getMigrateProcessDefinition($bundle),
      'destination' => $this->getMigrateDestinationDefinition($bundle),
      'migration_dependencies' => $this->calculateMigrationDependencies($bundle),
    ];

    return array_merge_recursive($migration_definition, $definition);
  }

  /**
   * Get migrate source definition.
   *
   * @return array
   *   An array of full definition.
   */
  protected function getMigrateSourceDefinition() {
    $plugin_id = $this->getImporterSourcePluginId();

    return [
      'plugin' => $plugin_id,
      'importer_id' => $this->id(),
    ] + $this->getImporterSourceConfiguration();
  }

  /**
   * Get migrate destination definition.
   *
   * @param $bundle
   *   The entity bundle name.
   *
   * @return array
   *   An array of full definition.
   */
  protected function getMigrateDestinationDefinition($bundle) {
    return [
      'plugin' => "entity:{$this->getImporterEntityType()}",
      'default_bundle' => $bundle,
    ];
  }

  /**
   * Get migrate process definition.
   *
   * @param $bundle
   *   The entity bundle name.
   *
   * @return array
   *   An array of full definition.
   */
  protected function getMigrateProcessDefinition($bundle) {
    $definition = [];

    foreach ($this->getFieldMappingByBundle($bundle) as $field_mapping) {
      if (!$field_mapping instanceof EntityImporterFieldMapping) {
        continue;
      }
      $processes = [];

      $source = $field_mapping->name();
      $configuration = $field_mapping->getProcessingConfiguration();

      // Iterate over all processes that were configured in a field mapping.
      if (isset($configuration['plugins'])
        && !empty($configuration['plugins'])) {
        $count = 1;

        foreach ($configuration['plugins'] as $plugin_id => $info) {
          $process = [
            'plugin' => $plugin_id,
          ] + array_filter($info['settings']);

          // Only add the source directive to the first process plugin. The
          // rest of the processes will inherit the value from the pipeline.
          if ($count === 1) {
            $process['source'] = $source;
          }
          $count++;

          $processes[] = $process;
        }
      }

      $definition[$field_mapping->getDestination()] = !empty($processes)
        ? $processes
        : $source;;
    }

    return $definition;
  }

  /**
   * Calculate migration dependencies.
   *
   * @param $bundle
   *   The entity bundle name.
   *
   * @return array
   *   An array of migration dependencies.
   */
  protected function calculateMigrationDependencies($bundle) {
    $dependencies = [
      'optional' => [],
      'required' => []
    ];
    $plugin_id = 'entity_import_migrate_lookup';

    /** @var \Drupal\entity_import\Entity\EntityImporterFieldMapping $field_mapping */
    foreach ($this->getFieldMappingByBundle($bundle) as $name => $field_mapping) {
      if (!$field_mapping->hasProcessingPlugin($plugin_id)) {
        continue;
      }
      $configuration = $field_mapping->getProcessingConfiguration();

      if (!isset($configuration['plugins'])) {
        continue;
      }
      $plugins = $configuration['plugins'];

      if (!isset($plugins[$plugin_id])) {
        continue;
      }
      $plugin = $plugins[$plugin_id];

      $dependencies['optional'] = is_array($plugin['settings']['migration'])
        ? array_values($plugin['settings']['migration'])
        : [$plugin['settings']['migration']];
    }

    return $dependencies;
  }

  /**
   * Get entity source value.
   *
   * @param $key
   *   The key name.
   * @param null $default
   *   The default value if key is not found.
   *
   * @return mixed|NULL
   */
  protected function getSourceInfoValue($key, $default = NULL) {
    $source_info = $this->getSourceInfo();

    return isset($source_info[$key])
      ? $source_info[$key]
      : $default;
  }

  /**
   * Get entity info value.
   *
   * @param $key
   *   The key name.
   * @param null $default
   *   The default value if key is not found.
   *
   * @return mixed|NULL
   */
  protected function getEntityInfoValue($key, $default = NULL) {
    $entity_info = $this->getEntityInfo();

    return isset($entity_info[$key])
      ? $entity_info[$key]
      : $default;
  }

  /**
   * Get field mapping configurations by importer type.
   *
   * @return array|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFieldMappingByImporterType() {
    $query = $this->getFieldMappingStorage()
      ->getQuery()
      ->condition('importer_type', $this->id());

    return $query->execute();
  }

  /**
   * Get field mapping storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getFieldMappingStorage() {
    return $this->entityTypeManager()
      ->getStorage('entity_importer_field_mapping');
  }

  /**
   * Get migration discovery cache backend.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   */
  protected function getMigrationDiscoveryCache() {
    return \Drupal::service('cache.discovery_migration');
  }

  /**
   * Get field mapping options configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  protected function getFieldMappingOptionsConfig() {
    return \Drupal::config("entity_import.field_mapping.options.{$this->id()}");
  }

  /**
   * Entity import source manager.
   *
   * @return \Drupal\entity_import\EntityImportSourceManager
   */
  protected function entityImportSourceManager() {
    return \Drupal::service('entity_import.source.manager');
  }

  /**
   * Migration plugin manager.
   *
   * @return \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected function migrationPluginManager() {
    return \Drupal::service('plugin.manager.migration');
  }
}
