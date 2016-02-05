<?php

/**
 * @file
 * Contains TMGMTJobItemUICart.
 */

/**
 * Represents a job item cart.
 *
 * @ingroup tmgmt_ui_cart
 */
class TMGMTJobItemUICart {

  /**
   * Singleton instance of cart.
   */
  protected static $instance;

  /**
   * Returns the singleton cart.
   *
   * @return TMGMTJobItemUICart
   *   An instance of the cart.
   */
  public static function getInstance() {
    if (!isset(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Creates a job item and adds it into the cart.
   *
   * @param string $plugin
   *   The source plugin.
   * @param string $item_type
   *   The source item type.
   * @param $item_id
   *   The source item id.
   * @param array $target_langcodes
   *   An array of target langcodes.
   *
   * @return \TMGMTJobItem Added job item.
   * Added job item.
   */
  public function addJobItem($plugin, $item_type, $item_id, $target_langcodes) {
    foreach ($target_langcodes as $key => $langcode) {
      if ($langcode === NULL) {
        unset($target_langcodes[$key]);
      }
    }
    if ($tjiid = $this->isSourceItemAdded($plugin, $item_type, $item_id)) {
      // Update target languages in this case.
      $job_item = tmgmt_job_item_load($tjiid);
    }
    else {
      $job_item = tmgmt_job_item_create($plugin, $item_type, $item_id);
      $job_item->save();
    }
    db_transaction();
    db_delete('tmgmt_ui_cart')
      ->condition('tjiid', $job_item->tjiid)
      ->execute();
    $query = db_insert('tmgmt_ui_cart')
      ->fields(array('tjiid', 'target_langcode'));
    foreach ($target_langcodes as $target_langcode) {
      $query->values(array('tjiid' => $job_item->tjiid, 'target_langcode' => $target_langcode));
    }
    $query->execute();
    return $job_item;
  }

  /**
   * Checks if the source item has been added into the cart.
   *
   * @param string $plugin
   *   The source plugin.
   * @param string $item_type
   *   The source type.
   * @param int $source_id
   *   The source id.
   *
   * @return bool|int
   *   Job item ID, if the source item is in the cart. FALSE otherwise.
   */
  public function isSourceItemAdded($plugin, $item_type, $source_id) {
    foreach ($this->getJobItemsFromCart() as $job_item) {
      if ($job_item->item_id == $source_id && $job_item->item_type == $item_type && $job_item->plugin == $plugin) {
        return $job_item->identifier();
      }
    }
    return FALSE;
  }

  /**
   * Remove job items from the cart.
   *
   * @param array $job_item_ids
   *   Job items to be removed.
   */
  public function removeJobItems(array $job_item_ids) {
    db_delete('tmgmt_ui_cart')
      ->condition('tjiid', $job_item_ids, 'IN')
      ->execute();
  }

  /**
   * Gets job items in the cart.
   *
   * @return TMGMTJobItem[] $items
   *   Job items in the cart.
   */
  public function getJobItemsFromCart() {
    $ids = db_select('tmgmt_ui_cart', 'c')
      ->fields('c', array('tjiid'))
      ->distinct()
      ->execute()
      ->fetchCol();
    return entity_load('tmgmt_job_item', $ids);
  }

  /**
   * Returns array of target langcodes for a cart item.
   *
   * @param int $tjiid
   *   TMGMT job item ID.
   *
   * @return array
   *   An array of target langcodes.
   */
  public function getJobItemTargetLangcodesFromCart($tjiid) {
    return db_select('tmgmt_ui_cart', 'c')
      ->fields('c', array('target_langcode'))
      ->condition('tjiid', $tjiid)
      ->execute()
      ->fetchCol();
  }

  /**
   * Returns united array of target langcodes for all cart items.
   *
   * @return array
   *   An array of target langcodes.
   */
  public function getTargetLangcodesFromCart() {
    return db_select('tmgmt_ui_cart', 'c')
      ->fields('c', array('target_langcode'))
      ->distinct()
      ->execute()
      ->fetchCol();
  }

  /**
   * Gets count of items in the cart.
   *
   * @return int
   *   Number of items in the cart.
   */
  public function count() {
    return db_select('tmgmt_ui_cart', 'c')
      ->fields('c', array('tjiid'))
      ->distinct()
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Remove all contents from the cart.
   */
  public function emptyCart() {
    db_truncate('tmgmt_ui_cart')->execute();
  }
}
