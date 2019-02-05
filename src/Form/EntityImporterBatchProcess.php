<?php

namespace Drupal\entity_import\Form;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Define the importer batch process.
 */
class EntityImporterBatchProcess {

  /**
   * Batch migration import.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration object.
   * @param $update
   *   A boolean flag if the migration should update.
   * @param $status
   *   The migration status that should be initialized.
   * @param $context
   *   An array of batch contexts.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public static function import(
    MigrationInterface $migration,
    $update,
    $status,
    array &$context
  ) {
    $context['message'] = new TranslatableMarkup(
      'Running @label (@status) migration.',
      [
        '@label' => $migration->label(),
        '@status' => $migration->getStatusLabel()
      ]
    );
    $action = 'import';
    $status = static::executeMigration($migration, $update, $status, $action);

    $context['results']['action'] = $action;
    $context['results']['migrations'][$migration->id()] = $status;
  }

  /**
   * Batch migration rollback.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration object.
   * @param $status
   *   The migration status that should be initialized.
   * @param $context
   *   An array of batch contexts.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public static function rollback(
    MigrationInterface $migration,
    $status,
    array &$context
  ) {
    $context['message'] = new TranslatableMarkup(
      'Rolling back @label (@status) migration.',
      [
        '@label' => $migration->label(),
        '@status' => $migration->getStatusLabel()
      ]
    );
    $action = 'rollback';
    $status = static::executeMigration($migration, FALSE, $status, $action);

    $context['results']['action'] = $action;
    $context['results']['migrations'][$migration->id()] = $status;
  }

  /**
   * Batch finished callback.
   *
   * @param $success
   *   The batch success boolean.
   * @param $results
   *   The batch results.
   * @param $operations
   *   An array of operations that finished.
   */
  public static function finished($success, $results, $operations) {
    $action = $results['action'];
    $message = $success === TRUE 
      ? new TranslatableMarkup(
        'The system successfully executed @action for @count migrations.', [
          '@action' => $action,
          '@count' => count($results['migrations'])
        ]
      )
      : new TranslatableMarkup('The system experienced a problem when executing @action.', [
          '@action' => $action,
        ]
      );

    static::messenger()->addMessage(
      $message,
      $success
        ? MessengerInterface::TYPE_STATUS
        : MessengerInterface::TYPE_WARNING
    );
  }

  /**
   * Execute migration.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   * @param $update
   *   A boolean flag if the migration should update.
   * @param $status
   *   The migration status that should be initialized.
   * @param $action
   *   The migration executable action to perform.
   *
   * @return int
   * @throws \Drupal\migrate\MigrateException
   */
  protected static function executeMigration(
    MigrationInterface $migration,
    $update,
    $status,
    $action
  ) {
    $migration->setStatus($status);

    if ($update == TRUE) {
      $migration->getIdMap()->prepareUpdate();
    }
    $executable = new MigrateExecutable($migration);

    if (!method_exists($executable, $action)) {
      return FALSE;
    }
    
    return call_user_func_array([$executable, $action], []);
  }

  /**
   * Get messenger instance.
   *
   * @return \Drupal\Core\Messenger\Messenger
   */
  protected static function messenger() {
    return \Drupal::service('messenger');;
  }
}
