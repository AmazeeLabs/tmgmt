<?php
/**
 * @file
 * Field processor for link.module.
 */

/**
 * Implements hook_tmgmt_source_translation_structure().
 *
 * This hook is implemented on behalf of the link.module.
 */
function link_tmgmt_source_translation_structure($entity_type, $entity, $field, $instance, $langcode, $items) {
  $structure = array();
  if (!empty($items)) {
    $structure['#label'] = check_plain($instance['label']);
    foreach ($items as $delta => $value) {
      $structure[$delta]['#label'] = t('Delta #@delta', array('@delta' => $delta));
      // The title, if exists.
      if (isset($value['title'])) {
        $structure[$delta]['title'] = array(
          '#label' => $structure['#label'],
          '#text' => $value['title'],
          '#translate' => TRUE,
        );
      }
      // Non-translatable columns.
      foreach (array('url', 'attributes') as $column) {
        $structure[$delta][$column] = array(
          '#label' => '',
          '#text' => $value[$column],
          '#translate' => FALSE,
        );
      }
    }
  }
  return $structure;
}
