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
   * Array key to store the contents of $this->cart into the $_SESSION variable.
   *
   * @var string
   */
  protected $session_key = 'tmgmt_cart';

  /**
   * An array to hold and manipulate the contents of the job item cart.
   *
   * Keys are translation job item IDs, values are arrays of target langcodes.
   *
   * @var array
   */
  protected $cart;

  /**
   * Set up a new TMGMTJobItemUICart instance.
   *
   * Will load the cart from the session or initialize a new one if nothing has
   * been stored yet.
   */
  protected function __construct() {
    if (
      !isset($_SESSION[$this->session_key])
      || !is_array($_SESSION[$this->session_key])
      || !is_array(reset($_SESSION[$this->session_key])) // Detects old format.
    ) {
      $_SESSION[$this->session_key] = array();
    }
    $this->cart = &$_SESSION[$this->session_key];
  }

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
      $this->cart[$tjiid] = $target_langcodes;
      return tmgmt_job_item_load($tjiid);
    }

    $job_item = tmgmt_job_item_create($plugin, $item_type, $item_id);
    $job_item->save();
    $this->cart[$job_item->tjiid] = $target_langcodes;
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
    $this->cart = array_diff_key($this->cart, array_flip($job_item_ids));
  }

  /**
   * Gets job items in the cart.
   *
   * @return TMGMTJobItem[] $items
   *   Job items in the cart.
   */
  public function getJobItemsFromCart() {
    return entity_load('tmgmt_job_item', array_keys($this->cart));
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
    return isset($this->cart[$tjiid]) ? $this->cart[$tjiid] : array();
  }

  /**
   * Returns united array of target langcodes for all cart items.
   *
   * @return array
   *   An array of target langcodes.
   */
  public function getTargetLangcodesFromCart() {
    $langcodes = array();
    foreach ($this->cart as $target_langcodes) {
      $langcodes = array_unique(array_merge($langcodes, $target_langcodes));
    }
    return array_values($langcodes);
  }

  /**
   * Gets count of items in the cart.
   *
   * @return int
   *   Number of items in the cart.
   */
  public function count() {
    return count($this->cart);
  }

  /**
   * Remove all contents from the cart.
   */
  public function emptyCart() {
    $this->cart = array();
  }
}
