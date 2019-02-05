<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Drupal\entity_import\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\entity_import\Entity\EntityImporter;
use Drupal\entity_import\Entity\EntityImporterInterface;
use Drupal\entity_import\Plugin\migrate\source\EntityImportSourceInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define entity importer content form.
 */
class EntityImporterPageImportForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * Entity importer page import form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MigrationPluginManagerInterface $migration_plugin_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_import.page.content';
  }

  /**
   * Set the form title.
   *
   * @param \Drupal\entity_import\Entity\EntityImporterInterface $entity_importer
   *   The entity importer instance.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function setTitle(EntityImporterInterface $entity_importer = NULL) {
    return $this->t('@label: Import', [
      '@label' => $entity_importer->label()
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityImporter $entity_importer = NULL) {
    $form['#prefix'] = '<div id="entity-importer-page">';
    $form['#suffix'] = '</div>';

    $bundle = $entity_importer->getFirstBundle();

    $form['bundle'] = [
      '#type' => 'value',
      '#value' => $bundle,
    ];

    if ($entity_importer->hasMultipleBundles()) {
      $bundle = $this->getFormStateValue('bundle', $form_state);
      $form['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Import Bundle'),
        '#description' => $this->t('Select the import bundle type.'),
        '#options' => $entity_importer->getImporterBundles(),
        '#required' => TRUE,
        '#default_value' => $bundle,
        '#ajax' => [
          'event' => 'change',
          'method' => 'replace',
          'wrapper' => "entity-importer-page",
          'callback' => [$this, 'ajaxReplaceCallback'],
        ],
      ];
    }

    if (isset($bundle) && !empty($bundle)) {
      $plugin_id = $entity_importer->getMigrationPluginId($bundle);

      /** @var MigrationInterface $migration */
      $migration = $this->migrationPluginManager->createInstance($plugin_id);

      if (!$migration) {
        throw new \RuntimeException(
          'The entity importer page migration is required.'
        );
      }
      $parents = ['migrations'];

      $migrations = $this->buildMigrationExecuteOrder(
        $migration, $form_state, $parents
      );

      $form['migrations'] = [
        '#type' => 'container',
        '#tree' => TRUE,
        '#parents' => $parents
      ];

      $this->buildMigrationForm(
        $migrations,
        $form['migrations'],
        $form_state,
        $parents
      );
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Import')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operations = $this->buildMigrationBatchOperations(
      $form, $form_state, ['migrations']
    );

    if (empty($operations)) {
      return;
    }

    $batch = [
      'title' => $this->t('Entity Importer'),
      'init_message' => $this->t('Processing...'),
      'operations' => $operations,
      'finished' => '\Drupal\entity_import\Form\EntityImporterBatchProcess::finished'
    ];

    batch_set($batch);
  }

  /**
   * Ajax replace callback.
   *
   * @param array $form
   *   The form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   An array of the form elements to return.
   */
  public function ajaxReplaceCallback(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Build migration batch operations.
   *
   * @param array $form
   *   The form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   * @param array $parents
   *   An array of parents.
   *
   * @return array
   *   An array of migration operations.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildMigrationBatchOperations(array $form, FormStateInterface $form_state, array $parents = []) {
    $operations = [];

    if ($migrations = $form_state->getValue('migrations')) {
      foreach ($migrations as $plugin_id => $configuration) {
        $migration = $this->migrationPluginManager->createInstance($plugin_id);

        if (!$migration) {
          continue;
        }
        $source = $migration->getSourcePlugin();

        if ($source instanceof EntityImportSourceInterface) {
          $subform = ['#parents' => array_merge($parents, [$plugin_id, 'configuration'])];
          $source->submitImportForm(
            $subform,
            SubformState::createForSubform($subform, $form, $form_state)
          );

          if (!$source->hasRequiredConfigs()) {
            continue;
          }
        }

        $operations[] = [
          '\Drupal\entity_import\Form\EntityImporterBatchProcess::import',
          [$migration, $configuration['update'], MigrationInterface::STATUS_IDLE]
        ];
      }
    }

    return $operations;
  }

  /**
   * Build migration form elements.
   *
   * @param array $migrations
   *   An array of migrations.
   * @param array $form
   *   The form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   * @param array $parents
   *   An array of parents.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildMigrationForm(array $migrations, array &$form, FormStateInterface $form_state, array $parents = []) {
    /** @var Migration $migration */
    foreach ($migrations as $plugin_id => $migration) {
      $form[$plugin_id] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#tree' => TRUE,
        '#title' => $this->t('@label (@status)', [
          '@label' => $migration->label(),
          '@status' => $migration->getStatusLabel()
        ]),
      ];
      $plugin_parents = array_merge($parents, [$plugin_id]);

      /** @var \Drupal\entity_import\Entity\EntityImporter $entity_importer */
      $entity_importer = $this->loadEntityImporterByMigration($migration);

      if ($entity_importer instanceof EntityImporterInterface) {
        // Display the entity importer description if one is defined.
        if ($description =  $entity_importer->getDescription()) {
          $form[$plugin_id]['#description'] = [
            '#type' => 'processed_text',
            '#text' => $description,
            '#format' => 'basic_html',
          ];
        }
      }

      /** @var \Drupal\migrate\Plugin\MigrateSourceInterface $source */
      $source = $migration->getSourcePlugin();

      if ($source instanceof EntityImportSourceInterface) {
        $subform = ['#parents' => array_merge($plugin_parents, ['configuration'])];
        $form[$plugin_id]['configuration'] = $source
          ->buildImportForm(
            $subform,
            SubformState::createForSubform($subform, $form, $form_state)
          );
      }
      $settings = $this->getFormStateValue($plugin_parents, $form_state);

      $form[$plugin_id]['update'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Update'),
        '#description' => $this->t('Update entity content from source.'),
        '#default_value' => isset($settings['update'])
          ? $settings['update']
          : FALSE,
      ];
    }
  }

  /**
   * Build migration execute order.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The required migration object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   * @param array $parents
   *   An array of parents.
   *
   * @return array
   *   Build migration execute order based on dependencies.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildMigrationExecuteOrder(
    MigrationInterface $migration,
    FormStateInterface $form_state,
    array $parents = []
  ) {
    $migrations = $this->createDependencyMigrations(
      $migration, $form_state, $parents
    );

    return array_reverse($migrations);
  }

  /**
   * Create migration dependency list.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The required migration object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $parents
   *   An array of parents.
   * @param array $list
   *   A list of migrations objects.
   *
   * @return array
   *   An array of optional dependency migrations.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function createDependencyMigrations(
    MigrationInterface $migration,
    FormStateInterface $form_state,
    array $parents = [],
    array &$list = []
  ) {
    $list[$migration->id()] = $migration;

    foreach ($migration->getMigrationDependencies()['optional'] as $plugin_id) {
      $plugin_parents = array_merge($parents, [$plugin_id, 'configuration']);

      $source = $this->getFormStateValue(
        $plugin_parents, $form_state, []
      );

      /** @var MigrationInterface $instance */
      $instance = $this
        ->migrationPluginManager
        ->createInstance($plugin_id, ['source' => $source]);

      return $this->createDependencyMigrations($instance, $form_state, $parents, $list);
    }

    return $list;
  }

  /**
   * Get form state value.
   *
   * @param $property
   *   The property name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   * @param null $default
   *   The default value; if form value is empty.
   *
   * @return mixed|null
   *   The property form value; otherwise the default value.
   */
  protected function getFormStateValue($property, FormStateInterface $form_state, $default = NULL) {
    if (!is_array($property)) {
      $property = [$property];
    }
    $states = [$form_state->getValues(), $form_state->getUserInput()];

    // Try to retrieve the property value from the form state array, otherwise
    // use the user input array.
    foreach ($states as $array) {
      $value = NestedArray::getValue($array, $property);

      if (!isset($value) || empty($value)) {
        continue;
      }

      return $value;
    }

    return $default;
  }

  /**
   * Load entity importer.
   *
   * @param $importer_id
   *   The entity importer identifier.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadEntityImporter($importer_id) {
    return $this
      ->entityTypeManager
      ->getStorage('entity_importer')
      ->load($importer_id);
  }

  /**
   * Load entity importer by migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadEntityImporterByMigration(MigrationInterface $migration) {
    $identifier = $migration->getDerivativeId();

    $importer_id = substr(
      $identifier, 0, strpos($identifier, ':')
    );

    return $this->loadEntityImporter($importer_id);
  }
}
