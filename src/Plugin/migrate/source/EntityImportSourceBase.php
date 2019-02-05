<?php

namespace Drupal\entity_import\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define the entity import source base.
 */
abstract class EntityImportSourceBase extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL
  ) {
    return new static (
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function hasRequiredConfigs() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildImportForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitImportForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = array_merge_recursive(
      $this->configuration, $form_state->cleanValues()->getValues()
    );
  }

  /**
   * Get migrate source default configuration.
   *
   * @return array
   */
  protected function defaultConfiguration() {
    return [];
  }

  /**
   * Get migrate source configuration.
   *
   * @return array
   */
  protected function getConfiguration() {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * Load the entity importer instance.
   */
  protected function loadEntityImporter() {
    $configuration = $this->getConfiguration();

    if (!isset($configuration['importer_id'])) {
      throw new MigrateException(
        'The importer_id directive in the migrate source is required.'
      );
    }
    $importer_id = $configuration['importer_id'];

    return $this->entityTypeManager
      ->getStorage('entity_importer')
      ->load($importer_id);
  }
}
