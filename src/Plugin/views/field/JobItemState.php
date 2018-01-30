<?php

namespace Drupal\tmgmt\Plugin\views\field;

use Drupal\tmgmt\Entity\JobItem;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;

/**
 * Field handler which shows the link for translating translation task items.
 *
 * @ViewsField("tmgmt_job_item_state")
 */
class JobItemState extends NumericField {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\tmgmt\JobItemInterface $job_item */
    $job_item = $values->_entity;

    $state_definitions = JobItem::getStateDefinitions();

    $state_definition = NULL;
    $translator_state = $job_item->getTranslatorState();
    if ($translator_state && isset($state_definitions[$translator_state]['icon'])) {
      $state_definition = $state_definitions[$translator_state];
    }
    elseif (isset($state_definitions[$job_item->getState()]['icon'])) {
      $state_definition = $state_definitions[$job_item->getState()];
    }

    if ($state_definition) {
      return [
        '#theme' => 'image',
        '#uri' => $state_definition['icon'],
        '#title' => $state_definition['label'],
        '#width' => 16,
        '#height' => 16,
      ];
    }
  }

}
