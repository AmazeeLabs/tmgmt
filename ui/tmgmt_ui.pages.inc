<?php

/**
 * @file
 * tmgmt page callbacks.
 */

/**
 * Creates and adds a jobitem to a certain job. If job is not defined, a new job
 * will be created. Destinatoin will be needed to return to the submitters page.
 */
function tmgmt_job_add_item($source_language, $source_plugin, $item_type, $item_ids, TMGMTJob $job = NULL) {
  if (!$job) {
    $job = tmgmt_job_create($source_language, '');
  }
  if ($source_language != $job->source_language) {
    drupal_set_message("The source language of your item doesn't match the source language of your job.", 'error');
  } else {
    $item_ids = explode(',', $item_ids);
    foreach ($item_ids as $item_id) {
      $job->addItem($source_plugin, $item_type, $item_id);
    }
    $job->prepared();
  }
  drupal_goto('admin/tmgmt/checkout/' . $job->tjid);
}

/**
 * The checkout form.
 */
function tmgmt_job_checkout_form($form, &$form_state, TMGMTJob $job) {
  // The Form API needs to know that we are
  $form_state['build_info']['files'][] = drupal_get_path('module', 'tmgmt') . '/includes/tmgmt.ui.inc';
  // Add the job to the form state so we can use it in the submit handler.
  $form_state['job'] = $job;
  // Get all available languages from core translation module.
  $available_languages = entity_metadata_language_list();
  // Keep the label of the source language.
  $source_language = $available_languages[$job->source_language];
  // Remove LANGUAGE_NONE and the source language from the list of available
  // languages and then apply a filter that only leaves the supported target
  // languages on the list.
  unset($available_languages[LANGUAGE_NONE], $available_languages[$job->source_language]);
  // Get the translator plugin that is currently selected.
  $translator_plugins = tmgmt_translator_availability_filter(tmgmt_translator_plugin_labels());
  $selected_translator_plugin = isset($form_state['values']['translator']) ? $form_state['values']['translator'] : key($translator_plugins);
  // Load plugin controller and info for the selected translator plugin.
  $selected_target_language = isset($form_state['values']['target_language']) ? $form_state['values']['target_language'] : key($available_languages);
  $translators = tmgmt_translator_plugin_info();
  $translator_plugins = tmgmt_translator_filtered_by_language($translators, $job->source_language, $selected_target_language);
  // Load the plugin info for the selected translator plugin.
  $plugin_info = tmgmt_translator_plugin_info($selected_translator_plugin);
  $plugin_controller = tmgmt_translator_plugin_controller($selected_translator_plugin);
  // Add a link for each item in this job.
  $form['links'] = array(
    '#theme' => 'links',
    '#title' => t('Items in this job.'),
  );
  foreach ($job->getItems() as $item) {
    $uri = $item->uri();
    $form['links']['#links'][$item->tjiid] = array(
      'title' => $item->label(),
      'href' => $uri['path'],
    );
  }
  $form['source_language'] = array(
    '#type' => 'item',
    '#title' => t('Source language'),
    '#markup' => $source_language,
  );
  $form['target_language'] = array(
    '#type' => 'select',
    '#title' => t('Target language'),
    '#description' => t('The target language'),
    '#options' => $available_languages,
    '#ajax' => array(
      'callback' => 'tmgmt_ajax_callback_get_supported_translators',
      'wrapper' => 'tmgmt-translator-wrapper',
    ),
  );
  $form['translator_wrapper'] = array(
    '#type' => 'container',
    '#prefix' => '<div id="tmgmt-translator-wrapper">',
    '#suffix' => '</div>',
  );
  if (!empty($translator_plugins)) {
    $form['translator_wrapper']['translator'] = array(
      '#type' => 'select',
      '#title' => t('Translator'),
      '#options' => $translator_plugins,
      '#default_value' => $selected_translator_plugin,
      '#ajax' => array(
        'callback' => 'tmgmt_ajax_callback_get_translator_options',
        'wrapper' => 'tmgmt-translator-options',
      ),
    );
    $form['translator_wrapper']['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Translator checkout settings'),
      '#description' => t('The @translator translator does not provide any per job settings.', array('@translator' => $plugin_info['label'])),
      '#prefix' => '<div id="tmgmt-translator-options">',
      '#suffix' => '</div>',
    );
    if (method_exists($plugin_controller, 'jobSettingsForm') && $settings = $plugin_controller->jobSettingsForm(array(), $form_state)) {
      unset($form['translator_wrapper']['options']['#description']);
      $form['translator_wrapper']['options']['settings'] = $settings;
      $form['translator_wrapper']['options']['settings']['#tree'] = TRUE;
    }
  }
  else {
    $form['translator_wrapper']['translator'] = array(
      '#markup' => t('None of the available translators supports the selected source and target language combination.'),
    );
  }
  $form['translator_wrapper']['actions']['#type'] = 'actions';
  $form['translator_wrapper']['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Submit'),
    '#weight' => 50,
  );
  return $form;
}

/**
 * submit function, will create a job.
 */
function tmgmt_job_checkout_form_submit($form, &$form_state) {
  $values = $form_state['values'];
  // Create the job object.
  //  $job_values = array('translator' => $values['translator'], 'uid' => $GLOBALS['user']->uid);
  //  if (isset($values['options']['settings'])) {
  //    $job_values['translator_context'] = $values['options']['settings'];
  //  }
  //  $job = tmgmt_job_create($values['source_language'], $values['target_language'], $job_values);
  //  // Create a job item and add it to the job.
  //  $job->addItem(tmgmt_job_item_create('node', 'node', $values['nid']));

  $job = tmgmt_job_load($values['tjid']);
  $job->target_language = $values['target_language'];
  $job->translator = $values['translator_wrapper']['translator'];
  //  dsm($job);
  //  $job->prepared('Job has been prepared.');
  // Print a message to the screen and redirect the user.
  drupal_set_message(t('Translation job has been created.'));
}

/**
 * Ajax callback to fetch the supported translator services.
 */
function tmgmt_ajax_callback_get_supported_translators($form, &$form_state) {
  return $form['translator_wrapper'];
}

/**
 * Ajax callback to fetch the options provided by a translator.
 */
function tmgmt_ajax_callback_get_translator_options($form, &$form_state) {
  return $form['translator_wrapper']['options'];
}


/**
 * Page callback for the tmgmt content page.
 *
 * Note that we add Views information to the array and render
 * the Views as part of the alter hook provided here.
 *
 * @see hook_tmgmt_ui_content_alter()
 *
 * @return
 *  A Render API array of content creation options.
 */
function tmgmt_ui_content() {
  $output = array();

  // Allow other modules to add content here.
  $output['#attributes'] = array('class' => array('admin', 'my-tmgmt'));
  $output['#attached'] = array(
    // @fixme: I know it's evil and ugly but it works.
    // Embedding views admin css file for 6 lines of css.
    'css' => array(drupal_get_path('module', 'tmgmt_ui') . '/css/tmgmt_ui.admin.css'),
  );

  $view_output = tmgmt_ui_embed_view('tmgmt_ui_reviews_pending');
  if (!empty($view_output)) {
    $output['review_pending'] = array(
      '#type' => 'item',
      '#title' => t('Reviews pending'),
      '#markup' => $view_output,
    );
  }

  $view_output = tmgmt_ui_embed_view('tmgmt_ui_new_jobs');
  if (!empty($view_output)) {
    $output['new_jobs'] = array(
      '#type' => 'item',
      '#title' => t('New jobs'),
      '#markup' => $view_output,
      '#theme_wrappers' => array('form_element', 'container'),
      '#attributes' => array('class' => array('tmgmt_ui_left_48')),
    );
  }

  $view_output = tmgmt_ui_embed_view('tmgmt_ui_recently_published');
  if (!empty($view_output)) {
    $output['recently_published'] = array(
      '#type' => 'item',
      '#title' => t('Recently published'),
      '#markup' => $view_output,
      '#theme_wrappers' => array('form_element', 'container'),
      '#attributes' => array('class' => array('tmgmt_ui_right_48')),
    );
  }

  return $output;
}

/**
 * Embed a view but don't render it if it's empty.
 */
function tmgmt_ui_embed_view($view) {
  $view = views_get_view($view);
  if (!empty($view)) {
    $view->init_display();
    $output = $view->preview();

    if (!empty($view->result)) {
      return $output;
    }
  }
  return '';
}

/**
 * Builds up a list of checkout forms.
 */
function tmgmt_ui_checkout_wizard($jobs = array()) {

//  ctools_include('wizard');
//  $form_info = array(
//    'id' => 'tmgmt_ui_checkout_wizard'
//  );
//  ctools_wizard_multistep_form($form_info, 'select', $form_state);
//  return "hallo";
  $form_state = array();
  // @todo: This doesn't include the file itself.
//  $form_state['build_info']['files'][] = drupal_get_path('module', 'tmgmt') .'/includes/tmgmt.ui.inc';
//  ctools_include('tmgmt.ui', 'tmgmt');

  // @todo: Implement multiple cases via a wizard? See above.
  $job = reset($jobs);
  $output = drupal_get_form('tmgmt_job_checkout_form', $job);

  return $output;
}
