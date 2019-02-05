<?php

namespace Drupal\entity_import\Plugin\migrate;

/**
 * Define entity import csv object.
 */
class EntityImportCsvObject extends \SplFileObject {

  /**
   * @var array
   */
  protected $headers = [];

  /**
   * @var boolean
   */
  protected $hasHeader;

  /**
   * Entity import CSV file object.
   *
   * @param $file_name
   * @param bool $has_header
   * @param string $delimiter
   * @param string $enclosure
   * @param string $escape
   */
  public function __construct($file_name, $has_header = FALSE,  $delimiter = ",", $enclosure = "\"", $escape = "\\") {
    parent::__construct($file_name, 'r');
    $this->setFlags(
      \SplFileObject::READ_CSV|\SplFileObject::READ_AHEAD|\SplFileObject::SKIP_EMPTY|\SplFileObject::DROP_NEW_LINE
    );
    $this->hasHeader = $has_header;
    $this->setCsvControl($delimiter, $enclosure, $escape);
  }

  /**
   * Get CSV column headers.
   *
   * @return array|string
   */
  public function getHeaders() {
    $this->rewind();
    return array_map('trim', parent::current());
  }

  /**
   * {@inheritdoc}
   */
  public function current() {
    if ($this->hasHeader && $this->key() === 0) {
      $this->headers = $this->getHeaders();
      $this->next();
    }

    return array_combine($this->headers, parent::current());
  }
}
