<?php

namespace Drupal\tmgmt\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TMGMTException;

/**
 * Source overview form.
 */
class CartForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tmgmt_cart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin = NULL, $item_type = NULL) {
    $languages = tmgmt_available_languages();
    $options = array();
    $selected = [];

    $header = [t('Type'), t('Label'), t('Source language')];
    foreach (tmgmt_cart_get()->getJobItemsFromCart() as $item) {
      $url = $item->getSourceUrl();
      $selected[$item->id()] = TRUE;
      $options[$item->id()] = array(
        $item->getSourceType(),
        $url ? \Drupal::l($item->label(), $url) : $item->label(),
        isset($languages[$item->getSourceLangCode()]) ? $languages[$item->getSourceLangCode()] : t('Unknown'),
      );
    }

    $form['items'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#empty' => t('There are no items in your cart.'),
      '#options' => $options,
      '#default_value' => $selected,
    );

    $form['enforced_source_language'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enforce source language'),
      '#description' => t('The source language is determined from the item\'s source language. If you wish to enforce a different language you can select one after ticking this checkbox. In such case the translation of the language you selected will be used as the source for the translation job.')
    );

    $form['source_language'] = array(
      '#type' => 'select',
      '#title' => t('Source language'),
      '#description' => t('Select a language that will be enforced as the translation job source language.'),
      '#options' => $languages,
      '#states' => array(
        'visible' => array(
          ':input[name="enforced_source_language"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['target_language'] = array(
      '#type' => 'select',
      '#title' => t('Request translation into language/s'),
      '#multiple' => TRUE,
      '#options' => $languages,
      '#description' => t('If the item\'s source language will be the same as the target language the item will be ignored.'),
    );

    $form['request_translation'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => t('Request translation'),
      '#submit' => array('::submitRequestTranslation'),
      '#validate' => array('tmgmt_cart_source_overview_validate'),
    );

    $form['remove_selected'] = array(
      '#type' => 'submit',
      '#button_type' => 'danger',
      '#value' => t('Remove selected item'),
      '#submit' => array('::submitRemoveSelected'),
      '#validate' => array('tmgmt_cart_source_overview_validate'),
    );

    $form['empty_cart'] = array(
      '#type' => 'submit',
      '#button_type' => 'danger',
      '#value' => t('Empty cart'),
      '#submit' => array('::submitEmptyCart'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Form submit callback to remove the selected items.
   */
  function submitRemoveSelected(array $form, FormStateInterface $form_state) {
    $job_item_ids = array_filter($form_state->getValue('items'));
    tmgmt_cart_get()->removeJobItems($job_item_ids);
    \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->delete(JobItem::loadMultiple($job_item_ids));
    $this->messenger()->addStatus(t('Job items were removed from the cart.'));
  }

  /**
   * Form submit callback to remove the selected items.
   */
  function submitEmptyCart(array $form, FormStateInterface $form_state) {
    \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->delete(tmgmt_cart_get()->getJobItemsFromCart());
    tmgmt_cart_get()->emptyCart();
    $this->messenger()->addStatus(t('All job items were removed from the cart.'));
  }

  /**
   * Custom form submit callback for tmgmt_cart_cart_form().
   */
  function submitRequestTranslation(array $form, FormStateInterface $form_state) {
    $items_ids = array_filter($form_state->getValue('items'));
    $target_languages = array_filter($form_state->getValue('target_language'));
    $enforced_source_language = NULL;
    $uid = $this->currentUser()->id();
    if ($form_state->getValue('enforced_source_language')) {
      $enforced_source_language = $form_state->getValue('source_language');
    }

    $batch =[
      'title' => t('Creating translation jobs'),
      'operations' => [
        [
          [self::class, 'batchGroupItems'],
          [$items_ids, $target_languages, $enforced_source_language],
        ],
        [
          [self::class, 'batchCreateJobs'],
          [$target_languages, $uid],
        ],
        [
          [self::class, 'batchCheckoutJobs'],
          [],
        ],
        [
          [self::class, 'batchProcessJobs'],
          [],
        ],
      ],
      'finished' => [self::class, 'batchFinished'],
    ];
    batch_set($batch);
  }

  /**
   * Groups the submitted cart items by source language.
   *
   * @param integer[] $items_ids
   *   Job items ids.
   * @param string[] $target_languages
   *   An array of target languages.
   * @param boolean $enforced_source_language
   *   Whether the source language should be enforced.
   * @param array $context
   *   The batch context.
   */
  public static function batchGroupItems($items_ids, $target_languages, $enforced_source_language, &$context) {
    $sandbox = &$context['sandbox'];
    $results = &$context['results'];

    if (!isset($sandbox['items_ids'])) {
      $sandbox['items_ids'] = $items_ids;
      $results['item_count'] = 0;
      $results['skipped_ids'] = [];
      $results['job_items_by_source_language'] = [];
    }

    $limit = 5;
    $ids = array_splice($sandbox['items_ids'], 0, $limit);

    foreach (JobItem::loadMultiple($ids) as $job_item) {
      $source_language = $enforced_source_language ?
        $enforced_source_language : $job_item->getSourceLangCode();
      $source_language_version_exists =
        in_array($source_language, $job_item->getExistingLangCodes());

      if ($source_language_version_exists) {
        foreach ($target_languages as $target_language) {
          if ($target_language == $source_language) {
            continue;
          }

          $results['job_items_by_languages'][$target_language][$source_language][$job_item->id()] = [
            'id' => $job_item->id(),
            'plugin' => $job_item->getPlugin(),
            'item_type' => $job_item->getItemType(),
            'item_id' => $job_item->getItemId(),
          ];
          $results['item_count']++;
        }
      }
      else {
        $results['skipped_ids'][] = $job_item->id();
      }
    }

    $total_count = count($items_ids);
    $remaining_count = count($sandbox['items_ids']);
    $processed_count = $total_count - $remaining_count;
    $context['message'] = "Grouping items by source and target language ($processed_count/$total_count).";

    if ($remaining_count > 0) {
      $context['finished'] = $processed_count / $total_count;
    }
  }

  /**
   * Groups the submitted cart items by source language.
   *
   * @param string[] $target_languages
   *   A list of target languages.
   * @param integer $uid
   *   The user id.
   * @param array $context
   *   The batch context.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function batchCreateJobs($target_languages, $uid, &$context) {
    $sandbox = &$context['sandbox'];
    $results = &$context['results'];

    if (!isset($sandbox['target_languages'])) {
      $sandbox['target_languages'] = $target_languages;
      $sandbox['current_target_language'] = array_shift($target_languages);
      $sandbox['source_languages'] = array_keys(
        $results['job_items_by_source_language']);
      $sandbox['current_source_language'] = array_shift($source_language);
      $sandbox['job_items_by_languages'] = $results['job_items_by_languages'];
      $sandbox['items_processed'] = 0;
      $results['jobs'] = [];
      $results['jobs_count'] = 0;
      $results['errors'] = [];
    }

    $limit = 5;
    $items = &$sandbox['job_items_by_languages'];
    $cart = tmgmt_cart_get();

    while (--$limit >= 0) {
      // Determine the source and target languages of the  next item.
      $remaining_target_languages = array_keys($items);
      $current_target_language = $remaining_target_languages[0];
      $remaining_source_languages = array_keys($items[$current_target_language]);
      $current_source_language = $remaining_source_languages[0];

      // Get the item and remove it from the processing queue.
      $item = array_shift(
        $items[$current_target_language][$current_source_language]);

      // Get the job.
      if (!$results['jobs'][$current_target_language][$current_source_language]) {
        $job = $job = tmgmt_job_create($current_source_language,
          $current_target_language, $uid);
        $job->save();
        $results['jobs'][$current_target_language][$current_source_language] = $job;
        $results['jobs_count']++;
      } else {
        $job = $results['jobs'][$current_target_language][$current_source_language];
      }

      try {
        // Create a new item in this job.
        $job->addItem(
          $item['plugin'],
          $item['item_type'],
          $item['item_id']
        );

        // Remove the item from the cart and delete the left-over entity if
        // it hasn't already been done.
        $cart_item_from_db = JobItem::load($item['id']);
        if ($cart_item_from_db) {
          $cart->removeJobItems([$item['id']]);
          $cart_item_from_db->delete();
        }
      } catch (TMGMTException $e) {
        $results['errors'][] = [
          'message' => $e->getMessage(),
          'item' => $item,
          'source_language' => $current_source_language,
          'target_language' => $current_target_language,
        ];
      }

      // If there are no more items in the source language to process then
      // checkout the job and remove its entry in the items array.
      if (empty($items[$current_target_language][$current_source_language])) {
        unset($items[$current_target_language][$current_source_language]);
      }

      // If there are no more source languages to process then move on to the
      // next target language.
      if (empty($items[$current_target_language])) {
        unset($items[$current_target_language]);
      }

      $sandbox['items_processed']++;
    }

    $total_count = $results['item_count'];
    $processed_count = $sandbox['items_processed'];
    $context['message'] = "Adding items to jobs ($processed_count/$total_count).";

    if ($processed_count < $total_count) {
      $context['finished'] = $processed_count / $total_count;
    }
  }

  /**
   * Checks out the jobs.
   *
   * @param array $context
   *   The batch context.
   */
  public static function batchCheckoutJobs(&$context) {
    $results = &$context['results'];
    $jobs = &$results['jobs'];

    if (!$results['jobs_checkout_processed_count']) {
      $results['jobs_checkout_processed_count'] = 0;
    }

    // Determine the source and target languages of the  next item.
    $remaining_target_languages = array_keys($jobs);
    $current_target_language = $remaining_target_languages[0];
    $remaining_source_languages = array_keys($jobs[$current_target_language]);
    $current_source_language = $remaining_source_languages[0];
    $job = $jobs[$current_target_language][$current_source_language];

    /** @var \Drupal\tmgmt\JobCheckoutManager $tmgmt_job_checkout_manager */
    $tmgmt_job_checkout_manager =
      \Drupal::service('tmgmt.job_checkout_manager');
    $remaining = $tmgmt_job_checkout_manager->checkoutMultiple([$job]);
    $results['jobs_checkout_processed_count']++;

    if (empty($remaining)) {
      unset($jobs[$current_target_language][$current_source_language]);

      if (empty($jobs[$current_target_language])) {
        unset($jobs[$current_target_language]);
      }
    }

    $total_count = $results['jobs_count'];
    $processed_count = $results['jobs_checkout_processed_count'];
    $context['message'] = "Checking out jobs ($processed_count/$total_count).";

    if ($processed_count < $total_count) {
      $context['finished'] = $processed_count / $total_count;
    }
  }

  /**
   * Submits the jobs.
   *
   * @param array $context
   *   The batch context.
   */
  public static function batchProcessJobs(&$context) {
    $sandbox = &$context['sandbox'];
    $results = &$context['results'];
    $jobs = &$results['jobs'];

    if (empty($jobs)) {
      return;
    }

    if (!isset($sandbox['remaining_jobs'])) {
      $sandbox['remaining_jobs'] = $results['jobs'];
      $results['jobs_processed'] = 0;
    }

    /** @var \Drupal\tmgmt\JobCheckoutManager $tmgmt_job_checkout_manager */
    $tmgmt_job_checkout_manager = \Drupal::service('tmgmt.job_checkout_manager');

    // Determine the source and target languages of the next job.
    $remaining_target_languages = array_keys(
      $sandbox['remaining_jobs']);
    $current_target_language = $remaining_target_languages[0];
    $remaining_source_languages = array_keys(
      $sandbox['remaining_jobs'][$current_target_language]);
    $current_source_language = $remaining_source_languages[0];

    $job = $sandbox['remaining_jobs'][$current_target_language][$current_source_language];

    unset($sandbox['remaining_jobs'][$current_target_language][$current_source_language]);
    if (empty($sandbox['remaining_jobs'][$current_target_language])) {
      unset($sandbox['remaining_jobs'][$current_target_language]);
    }

    $tmgmt_job_checkout_manager->doBatchSubmit($job->id());
    $results['jobs_processed']++;

    $total_count = $results['jobs_count'];
    $processed_count = $results['jobs_processed'] + $results['jobs_checkout_processed_count'];
    $context['message'] = "Submitting jobs ($processed_count/$total_count).";

    if ($processed_count < $total_count) {
      $context['finished'] = $processed_count / $total_count;
    }
  }

  /**
   * Batch finished callback.
   *
   * @param boolean $success
   *   If the batch was successful.
   * @param array $results
   *   An associative array with results.
   * @param array $operations
   *   The list of performed operations.
   */
  public static function batchFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger
        ->addMessage(t('@count jobs created.', [
          '@count' => $results['jobs_count'],
        ]));
    } else {
      $error_operation = reset($operations);
      $messenger
        ->addMessage(t('An error occurred while processing @operation.', [
          '@operation' => $error_operation[0],
        ]));
    }
  }

}
