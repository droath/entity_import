<?php

namespace Drupal\entity_import\Plugin\migrate\source;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_import\Plugin\migrate\EntityImportCsvObject;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\migrate\Annotation\MigrateSource;

/**
 * @MigrateSource(
 *   id = "entity_import_csv",
 *   label = @Translation("CSV"),
 * )
 */
class EntityImportCSV extends EntityImportSourceBase implements EntityImportSourceInterface, PluginFormInterface {

  /**
   * @var EntityImportCsvObject
   */
  protected $importFile;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return json_encode($this->fields());
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return $this->getImportFileObject()->getHeaders();
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $unique_ids = [];
    /** @var \Drupal\entity_import\Entity\EntityImporter $importer */
    $importer = $this->loadEntityImporter();

    foreach ($importer->getFieldMappingUniqueIdentifiers() as $info) {
      if (!isset($info['identifier_name']) || !isset($info['identifier_type'])) {
        continue;
      }
      $identifier_name = $info['identifier_name'];

      $unique_ids[$identifier_name] = [
        'type' => $info['identifier_type'],
      ];

      if (isset($info['identifier_settings'])
        && !empty($info['identifier_settings'])) {
        $settings = json_decode($info['identifier_settings'], TRUE);

        if (isset($settings)) {
          $unique_ids[$identifier_name] + $settings;
        }
      }
    }

    return $unique_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    return $this->getImportFileObject();
  }

  /**
   * {@inheritdoc}
   */
  public function unlinkImportFile() {
    $import_file = $this->importFile->getRealPath();

    if (file_exists($import_file)) {
      unlink($import_file);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRequiredConfigs() {
    $config = $this->getConfiguration();

    if (isset($config['file_id']) && !empty($config['file_id'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildImportForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildImportForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['file_id'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload File'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#multiple' => $config['upload_multiple'],
      '#default_value' => $config['file_id'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['has_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Includes Header'),
      '#description' => $this->t('The uploaded CSV file will include a header.'),
      '#default_value' => $config['has_header'],
    ];
    $form['upload_multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Support multiple files'),
      '#description' => $this->t('Allow multiple CSV files to be uploaded at once.'),
      '#default_value' => $config['upload_multiple'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultConfiguration() {
    return [
        'file_id' => [],
        'has_header' => FALSE,
        'upload_multiple' => FALSE,
      ] + parent::defaultConfiguration();
  }

  /**
   * Get import file object.
   *
   * @return \Drupal\entity_import\Plugin\migrate\EntityImportCsvObject
   *   The import file object.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getImportFileObject() {
    $config = $this->getConfiguration();

    if (!isset($this->importFile)) {
      $this->importFile = $this->loadFileObject(
        $config['file_id'], $config['has_header']
      );
    }

    return $this->importFile;
  }

  /**
   * Load the CSV file object.
   *
   * @param array $file_ids
   *   An array of file identifiers.
   * @param bool $has_header
   *   A boolean based on if the CSV file has headers.
   *
   * @return \Drupal\entity_import\Plugin\migrate\EntityImportCsvObject
   *   The entity import CSV object.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function loadFileObject(array $file_ids, $has_header) {
    $file_name = $this->loadUploadedFileUri($file_ids, $has_header);

    return new EntityImportCsvObject(
      $file_name, $has_header
    );
  }

  /**
   * Load uploaded files.
   *
   * @param array $file_ids
   *   An array of file identifiers.
   * @param bool $has_header
   *   A boolean based on if the CSV file has headers.
   *
   * @return string
   *   The uploaded file URI.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function loadUploadedFileUri(array $file_ids, $has_header) {
    $files = $this->loadFiles($file_ids);

    return $this->mergeCsvFiles(
      $files, $this->getMergeFilename(), $has_header
    );
  }

  /**
   * Load uploaded files.
   *
   * @param array $file_ids
   *   An array of file identifiers.
   *
   * @return array
   *   An array of instantiated \Drupal\file\Entity\File objects.
   */
  protected function loadFiles(array $file_ids) {
    $files =  [];

    foreach ($file_ids as $fid) {
      /** @var \Drupal\file\Entity\File $file */
      $file = File::load($fid);

      if (!isset($file) || !$file instanceof FileInterface) {
        continue;
      }
      $files[] = $file;
    }

    return $files;
  }

  /**
   * Merge CSV files into a single file.
   *
   * @param array $files
   *   An array \Drupal\file\Entity\File instances.
   * @param $filename
   *   The file name where the content should be merged.
   * @param bool $has_header
   *   A boolean if headers exist in the CSV files.
   *
   * @return string
   *   The output URI.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function mergeCsvFiles(array $files, $filename, $has_header = TRUE) {
    $header = [];
    $handler = fopen($filename, 'w+');

    /** @var \Drupal\file\Entity\File $file */
    foreach($files as $file) {
      $file_handler = fopen($file->getFileUri(), 'r');
      if (!$file_handler) {
        continue;
      }
      $count = 1;

      while(!feof($file_handler)) {
        $line = fgets($file_handler);

        if ($has_header && $count === 1) {
          $array_line = str_getcsv($line);

          if (empty($header)) {
            $header = $array_line;
            fwrite($handler, $line);
          } else {
            // Skip merging if the CSV file headers don't match.
            if (array_diff($array_line, $header)) {
              continue 2;
            }
          }
          ++$count;
          continue;
        }

        fwrite($handler, $line);
        ++$count;
      }

      fclose($file_handler);
      unset($file_handler);

      $file->delete();

      fwrite($handler, "\n");
    }
    fclose($handler);
    unset($handler);

    return $filename;
  }

  /**
   * Get merge filename.
   *
   * @return bool|string
   *   The merge filename.
   */
  protected function getMergeFilename() {
    return $this->fileSystem->tempnam(
      file_directory_temp(), 'ENTITY_IMPORTER_'
    );
  }
}
