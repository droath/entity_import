<?php /** @noinspection PhpUndefinedMethodInspection */

namespace Drupal\entity_import\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_import\Entity\EntityImporter;
use Drupal\entity_import\Entity\EntityImporterInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define the entity importer action form.
 */
class EntityImporterPageImportActionForm extends FormBase {

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
    return 'entity_import_importer_action';
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
    return $this->t('@label: Action', [
      '@label' => $entity_importer->label()
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    EntityImporter $entity_importer = NULL
  ) {
    $form['#prefix'] = '<div id="entity-importer-action">';
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
          'wrapper' => "entity-importer-action",
          'callback' => [$this, 'ajaxReplaceCallback'],
        ],
      ];
    }
    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#options' => $this->getMigrationActionOptions(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- None -'),
      '#ajax' => [
        'event' => 'change',
        'method' => 'replace',
        'wrapper' => "entity-importer-action",
        'callback' => [$this, 'ajaxReplaceCallback'],
      ]
    ];
    $migrations = $entity_importer->getDependencyMigrations($bundle);

    $form['migrations'] = [
      '#type' => 'tableselect',
      '#title' => $this->t('Importers'),
      '#header' => [
        'title' => $this->t('Title'),
        'status' => $this->t('Status')
      ],
      '#options' => $this->buildMigrationTableOptions($migrations)
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['execute'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute')
    ];

    return $form;
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
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getValue('action');
    $migrations = array_filter($form_state->getValue('migrations'));

    if (!isset($action) || empty($migrations)) {
      return;
    }
    $action_classes = [
      'rollback' => '\Drupal\entity_import\Form\EntityImporterBatchProcess::rollback'
    ];
    $operations = [];

    foreach ($migrations as $plugin_id) {
      $migration = $this->migrationPluginManager->createInstance($plugin_id);
      $operations[] = [
        $action_classes[$action],
        [$migration, MigrationInterface::STATUS_IDLE]
      ];
    }
    $actions = $this->getMigrationActionOptions();

    $batch = [
      'title' => $this->t('Executing @action', [
        '@action' => $actions[$action]
      ]),
      'init_message' => $this->t('Processing...'),
      'operations' => $operations,
      'finished' => '\Drupal\entity_import\Form\EntityImporterBatchProcess::finished'
    ];

    batch_set($batch);
  }

  /**
   * Get property value from the form state.
   *
   * @param $property
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param null $default
   *
   * @return mixed|null
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
   * Build migrations table options.
   *
   * @param array $migrations
   *   An array of migrations.
   *
   * @return array
   *   An array of migration table options.
   */
  protected function buildMigrationTableOptions(array $migrations) {
    $options = [];

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($migrations as $plugin_id => $migration) {
      $options[$plugin_id] = [
        'title' => $migration->label(),
        'status' => $migration->getStatusLabel()
      ];
    }

    return $options;
  }

  /**
   * Get migration action options.
   *
   * @return array
   */
  protected function getMigrationActionOptions() {
    return [
      'rollback' => $this->t('Rollback'),
    ];
  }
}
