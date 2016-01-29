<?php

/**
 * @file
 * Rules integration.
 */

/**
 * {@inheritdoc}
 */
function tmgmt_ui_rules_action_info() {
  $info['tmgmt_ui_rules_source_add_item_to_cart'] = array(
    'label' => t('Add to cart'),
    'group' => t('Translation Management'),
    'parameter' => array(
      'plugin' => array(
        'type' => 'token',
        'label' => t('Source plugin'),
        'description' => t('The source plugin of this item'),
      ),
      'item_type' => array(
        'type' => 'token',
        'label' => t('Item type'),
        'description' => t('The item type'),
      ),
      'item_id' => array(
        'type' => 'text',
        'label' => t('Item ID'),
        'description' => t('ID of the referenced item'),
      ),
    ),
  );

  $info['tmgmt_ui_rules_source_add_item_to_job'] = array(
    'label' => t('Add to job'),
    'group' => t('Translation Management'),
    'parameter' => array(
      'plugin' => array(
        'type' => 'token',
        'label' => t('Source plugin'),
        'description' => t('The source plugin of this item'),
      ),
      'item_type' => array(
        'type' => 'token',
        'label' => t('Item type'),
        'description' => t('The item type'),
      ),
      'item_id' => array(
        'type' => 'text',
        'label' => t('Item ID'),
        'description' => t('ID of the referenced item'),
      ),
    ),
  );

  $info['tmgmt_ui_rules_source_return_to_job'] = array(
    'label' => t('Return to job'),
    'group' => t('Translation Management'),
  );

  return $info;
}

/**
 * Rules callback to add a job item into the cart.
 */
function tmgmt_ui_rules_source_add_item_to_cart($plugin, $item_type, $item_id) {
  tmgmt_ui_cart_get()->addJobItem($plugin, $item_type, $item_id);
}

/**
 * Rules callback to add a job item to a job.
 */
function tmgmt_ui_rules_source_add_item_to_job($plugin, $item_type, $item_id) {
  $return = tmgmt_ui_add_items_to_job($_SESSION['tmgmt_edit_job_id'], $plugin, $item_type, array($item_id));
  // Show any messages we might have.
  if (!empty($return['messages'])) {
    foreach ($return['messages'] as $message) {
      drupal_set_message($message['message'], $message['type']);
    }
  }
}

/**
 * Rules callback to return to the job in the session.
 */
function tmgmt_ui_rules_source_return_to_job() {
  if (!empty($_SESSION['tmgmt_edit_job_id'])) {
    // @todo: this will work only when using with a form, for example a VBO.
    $_GET['destination'] = 'admin/tmgmt/jobs/' . $_SESSION['tmgmt_edit_job_id'];
    unset($_SESSION['tmgmt_edit_job_id']);
  }
}
