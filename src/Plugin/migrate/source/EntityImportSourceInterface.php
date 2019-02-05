<?php

namespace Drupal\entity_import\Plugin\migrate\source;

use Drupal\Core\Form\FormStateInterface;

/**
 * Define entity import source interface.
 */
interface EntityImportSourceInterface {

  /**
   * Get the entity import source label.
   *
   * @return string
   */
  public function getLabel();

  /**
   * Unlink import file.
   *
   * @return $this
   */
  public function unlinkImportFile();

  /**
   * Has required configurations.
   *
   * @return bool
   */
  public function hasRequiredConfigs();

  /**
   * Build import form.
   *
   * @param array $form
   *   An array of the form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The form elements.
   */
  public function buildImportForm(array $form, FormStateInterface $form_state);

  /**
   * Submit import form.
   *
   * @param array $form
   *   An array of the form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitImportForm(array &$form, FormStateInterface $form_state);
}
