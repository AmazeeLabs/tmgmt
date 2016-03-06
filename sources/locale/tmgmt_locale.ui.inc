<?php

/**
 * @file
 * Provides the I18nString source controller.
 */

/**
 * Class TMGMTI18nStringDefaultSourceUIController
 *
 * UI Controller fo i18n strings translation jobs.
 */
class TMGMTLocaleSourceUIController extends TMGMTDefaultSourceUIController {

  /**
   * Gets locale strings.
   *
   * @param string $textgroup
   *   The locale textgroup.
   * @param string $search_label
   *   Label to search for.
   * @param string $missing_target_language
   *   Missing translation language.
   *
   * @return array
   *   List of i18n strings data.
   */
  function getStrings($textgroup = NULL, $search_label = NULL, $missing_target_language = NULL, $location = NULL, $use_pager = FALSE) {
    $languages = drupal_map_assoc(array_keys(language_list()));
    $select = db_select('locales_source', 'ls')
      ->fields('ls', array('lid', 'source'));

    if (!empty($textgroup)) {
      $select->condition('ls.textgroup', $textgroup);
    }
    if (!empty($search_label)) {
      $select->condition('ls.source', "%$search_label%", 'LIKE');
    }
    if (!empty($location)) {
      $select->condition('ls.location', "%$location%", 'LIKE');
    }
    if (!empty($missing_target_language) && in_array($missing_target_language, $languages)) {
      $select->isNull("lt_$missing_target_language.language");
    }

    // Join locale targets for each language.
    // We want all joined fields to be named as langcodes, but langcodes could
    // contain hyphens in their names, which is not allowed by the most database
    // engines. So we create a langcode-to-filed_alias map, and rename fields
    // later.
    $langcode_to_filed_alias_map = array();
    foreach ($languages as $langcode) {
      $table_alias = $select->leftJoin('locales_target', db_escape_field("lt_$langcode"), "ls.lid = %alias.lid AND %alias.language = '$langcode'");
      $langcode_to_filed_alias_map[$langcode] = $select->addField($table_alias, 'language');
    }

    if ($use_pager) {
      $select = $select->extend('PagerDefault')->limit(variable_get('tmgmt_source_list_limit', 20));
    }
    $rows = $select->execute()->fetchAll();
    foreach ($rows as $row) {
      foreach ($langcode_to_filed_alias_map as $langcode => $field_alias) {
        $row->{$langcode} = $row->{$field_alias};
        unset($row->{$field_alias});
      }
    }

    return $rows;
  }

  /**
   * Gets overview form header.
   *
   * @return array
   *   Header array definition as expected by theme_tablesort().
   */
  public function overviewFormHeader() {
    $languages = array();
    foreach (language_list() as $langcode => $language) {
      $languages['langcode-' . $langcode] = array(
        'data' => check_plain($language->name),
      );
    }

    $header = array(
      'source' => array('data' => t('Source text')),
    ) + $languages;

    return $header;
  }

  /**
   * Implements TMGMTSourceUIControllerInterface::overviewForm().
   */
  public function overviewForm($form, &$form_state, $type) {
    $form += $this->overviewSearchFormPart($form, $form_state, $type);

    $form['items'] = array(
      '#type' => 'tableselect',
      '#header' => $this->overviewFormHeader($type),
      '#empty' => t('No strings matching given criteria have been found.')
    );
    $langs = array();
    foreach (language_list() as $langcode => $language) {
      $langs[$langcode] = $language->name;
    }
    $form['actions']['from_translated_language'] = array(
      '#type' => 'select',
      '#title' => t('Generate from language'),
      '#options' => $langs,
    );
    $form['actions']['generate_from_translated'] = array(
      '#type' => 'submit',
      '#value' => t('Generate'),
    );
    $form['actions']['generate_description'] = array(
      '#type' => 'item',
      '#markup' => t('You can choose to create a job with all the items that are already translated into a certain language. All the items in the locales table will be used, not only the ones which are part of the default group (the ones you see on this page), so for example the taxonomy labels, panels configuration, views configuration, field labels, etc., will be added to the job.'),
    );

    $search_data = $this->getSearchFormSubmittedParams();

    $strings = $this->getStrings($type, $search_data['label'], $search_data['missing_target_language'], $search_data['location']);

    foreach ($this->getTranslationData($strings, $type) as $id => $data) {
      $form['items']['#options'][$id] = $this->overviewRow($type, $data);
    }

    $form['pager'] = array('#markup' => theme('pager', array('tags' => NULL)));

    return $form;
  }

  /**
   * Helper function to create translation data list for the sources page list.
   *
   * @param array $strings
   *   Result of the search query returned by tmgmt_i18n_string_get_strings().
   * @param string $type
   *   I18n object type.
   *
   * @return array
   *   Structured array with translation data.
   */
  protected function getTranslationData($strings, $type) {
    $objects = array();
    // Source language of locale strings is always english.
    $source_language = 'en';

    foreach ($strings as $string) {
      $id = $string->lid;

      // Get existing translations and current job items for the entity
      // to determine translation statuses
      $current_job_items = tmgmt_job_item_load_latest('locale', $type, $id, $source_language);

      $objects[$id] = array(
        'id' => $id,
        'object' => $string
      );
      // Load entity translation specific data.
      foreach (language_list() as $langcode => $language) {
        $translation_status = 'current';

        if ($langcode == $source_language) {
          $translation_status = 'original';
        }
        elseif ($string->{'lt_' . str_replace('-', '', $langcode) . '_language'} === NULL) {
          $translation_status = 'missing';
        }

        $objects[$id]['current_job_items'][$langcode] = isset($current_job_items[$langcode]) ? $current_job_items[$langcode] : NULL;
        $objects[$id]['translation_statuses'][$langcode] = $translation_status;
      }
    }

    return $objects;
  }

  /**
   * Builds search form for entity sources overview.
   *
   * @param array $form
   *   Drupal form array.
   * @param $form_state
   *   Drupal form_state array.
   * @param $type
   *   Entity type.
   *
   * @return array
   *   Drupal form array.
   */
  public function overviewSearchFormPart($form, &$form_state, $type) {

    $options = array();
    foreach (language_list() as $langcode => $language) {
      $options[$langcode] = $language->name;
    }

    $default_values = $this->getSearchFormSubmittedParams();

    $form['search_wrapper'] = array(
      '#prefix' => '<div class="tmgmt-sources-wrapper tmgmt-i18n_string-sources-wrapper">',
      '#suffix' => '</div>',
      '#weight' => -15,
    );
    $form['search_wrapper']['search'] = array(
      '#tree' => TRUE,
    );
    $form['search_wrapper']['search']['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Source text'),
      '#default_value' => isset($default_values['label']) ? $default_values['label'] : NULL,
    );
    $form['search_wrapper']['search']['location'] = array(
      '#type' => 'textfield',
      '#title' => t('Location'),
      '#default_value' => isset($default_values['location']) ? $default_values['location'] : NULL,
      '#description' => t('Enter the location (context) of the label, for example: <em>/admin/config/regional/language</em> or <em>node:type:page:name</em>')
    );

    // Unset English as it is the source language for all locale strings.
    unset($options['en']);

    $form['search_wrapper']['search']['missing_target_language'] = array(
      '#type' => 'select',
      '#title' => t('Not translated to'),
      '#options' => $options,
      '#empty_option' => '--',
      '#default_value' => isset($default_values['missing_target_language']) ? $default_values['missing_target_language'] : NULL,
    );
    $form['search_wrapper']['search_submit'] = array(
      '#type' => 'submit',
      '#value' => t('Search'),
    );

    return $form;
  }

  /**
   * Gets submitted search params.
   *
   * @return array
   */
  public function getSearchFormSubmittedParams() {
    $params = array(
      'label' => NULL,
      'missing_target_language' => NULL,
      'location' => NULL,
    );

    if (isset($_GET['label'])) {
      $params['label'] = $_GET['label'];
    }
    if (isset($_GET['location'])) {
      $params['location'] = $_GET['location'];
    }
    if (isset($_GET['missing_target_language'])) {
      $params['missing_target_language'] = $_GET['missing_target_language'];
    }

    return $params;
  }

  /**
   * Builds a table row for overview form.
   *
   * @param string $type
   *   i18n type.
   * @param array $data
   *   Data needed to build the list row.
   *
   * @return array
   */
  public function overviewRow($type, $data) {
    // Set the default item key, assume it's the first.
    $source = $data['object'];

    $row = array(
      'id' => $data['id'],
      'source' => check_plain($source->source),
    );

    foreach (language_list() as $langcode => $language) {
      $row['langcode-' . $langcode] = theme('tmgmt_ui_translation_language_status_single', array(
        'translation_status' => $data['translation_statuses'][$langcode],
        'job_item' => isset($data['current_job_items'][$langcode]) ? $data['current_job_items'][$langcode] : NULL,
      ));
    }

    return $row;
  }

  /**
   * Implements TMGMTSourceUIControllerInterface::overviewFormSubmit().
   */
  public function overviewFormSubmit($form, &$form_state, $type) {
    // Handle search redirect.
    $this->overviewSearchFormRedirect($form, $form_state, $type);
    // If we want to generate the job from an already translated language, we
    // will have to ignore any items which are selected in the form and just
    // search for all the items which are already translated for that language.
    $generate_from_language = $form_state['triggering_element']['#value'] == $form['actions']['generate_from_translated']['#value'];
    if ($generate_from_language) {
      $strings = $this->getStrings(NULL, NULL, NULL, NULL, FALSE);
      $from_lang = 'lt_' . str_replace('-', '', $form_state['values']['from_translated_language']) . '_language';
      foreach ($strings as $item) {
        if (!empty($item->{$from_lang})) {
          $items[$item->lid] = $item->lid;
        }
      }
    }
    else {
      $items = array_filter($form_state['values']['items']);
    }

    $source_lang = 'en';

    $type = $form_state['item_type'];

    // Create only single job for all items as the source language is just
    // the same for all.
    $job = tmgmt_job_create($source_lang, NULL, $GLOBALS['user']->uid);

    // Loop through entities and create individual jobs for each source language.
    foreach ($items as $item) {
      // Not all the items will be able to be translated. For example, if there
      // is a php script, then the tmgmt_word_count() will remove the tags
      // (if set like that), which will generate a TMGMTException exception.
      // We have to catch it and log it at least.
      try {
        // If we generate the strings from a language, then we just add them to
        // the cart so that the user will be able to make some adjustments
        // before submitting the job.
        if ($generate_from_language) {
          tmgmt_ui_cart_get()->addJobItem('locale', $type, $item);
        }
        else {
          $job->addItem('locale', $type, $item);
        }
      }
      catch (TMGMTException $e) {
        watchdog_exception('tmgmt', $e);
        drupal_set_message(t('Unable to add job item of type %type with id %id. Make sure the source content is not empty. For example, if it is a php code to be evaluated and the tags are excluded because of the %var variable, then this might be the issue.',
          array('%type' => $type, '%id' => $item, '%var' => 'tmgmt_word_count_exclude_tags')), 'error');
      }
    }

    if ($generate_from_language) {
      $form_state['redirect'] = 'admin/tmgmt/cart';
      drupal_set_message(t('The items have been added to the cart. You can review them and request a translation afterwards.'));
    }
    else {
      $form_state['redirect'] = array('admin/tmgmt/jobs/' . $job->tjid, array('query' => array('destination' => current_path())));
      drupal_set_message(t('One job needs to be checked out.'));
    }
  }

  /**
   * Performs redirect with search params appended to the uri.
   *
   * In case of triggering element is edit-search-submit it redirects to
   * current location with added query string containing submitted search form
   * values.
   *
   * @param array $form
   *   Drupal form array.
   * @param $form_state
   *   Drupal form_state array.
   * @param $type
   *   Entity type.
   */
  public function overviewSearchFormRedirect($form, &$form_state, $type) {
    if ($form_state['triggering_element']['#id'] == 'edit-search-submit') {

      $query = array();

      foreach ($form_state['values']['search'] as $key => $value) {
        $query[$key] = $value;
      }

      drupal_goto($_GET['q'], array('query' => $query));
    }
  }

}
