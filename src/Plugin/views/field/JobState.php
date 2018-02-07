<?php

namespace Drupal\tmgmt\Plugin\views\field;

use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;

/**
 * Field handler which shows the state icons for jobs.
 *
 * @ViewsField("tmgmt_job_state")
 */
class JobState extends NumericField {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = parent::render($values);
    switch ($value) {
      case JobInterface::STATE_UNPROCESSED:
        $label = t('Unprocessed');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/rejected.svg';
        break;

      case JobInterface::STATE_ACTIVE:
        /** @var JobItemInterface $item */
        $highest_weight_icon = NULL;
        foreach ($values->_entity->getItems() as $item) {
          $job_item_icon = $item->getStateIcon();
          if ($job_item_icon && (!$highest_weight_icon || $highest_weight_icon['#weight'] < $job_item_icon['#weight'])) {
            $highest_weight_icon = $job_item_icon;
          }
        }
        if ($highest_weight_icon) {
          return $highest_weight_icon;
        }
        $label = t('In progress');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/hourglass.svg';
        break;

      case JobInterface::STATE_CONTINUOUS:
        $label = t('Continuous');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/continuous.svg';
        break;

      case JobInterface::STATE_CONTINUOUS_INACTIVE:
        $label = t('Continuous Inactive');
        $icon = drupal_get_path('module', 'tmgmt') . '/icons/continuous_inactive.svg';
        break;

      default:
        $icon = NULL;
        $label = NULL;
    }

    if ($icon && $label) {
      return [
        '#theme' => 'image',
        '#uri' => file_create_url($icon),
        '#title' => $label,
        '#alt' => $label,
        '#width' => 16,
        '#height' => 16,
      ];
    }
  }

}
