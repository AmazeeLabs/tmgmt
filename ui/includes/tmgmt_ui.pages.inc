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
  }
  else {
    $item_ids = explode(',', $item_ids);
    foreach ($item_ids as $item_id) {
      $job->addItem($source_plugin, $item_type, $item_id);
    }
  }
  drupal_goto('admin/tmgmt/checkout/' . $job->tjid);
}

/**
 * The checkout form.
 */
function tmgmt_job_checkout_form($form, &$form_state, TMGMTJob $job) {
  // Add the job to the form state so we can use it in the submit handler.
  $form_state['job'] = $job;
  if ($job->isNew()) {
    $job->prepared();
  }
  if ($job->isRejected() || $job->isPrepared()) {
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
        '#prefix' => '<div id="tmgmt-translator-options">',
        '#suffix' => '</div>',
      );
      switch (tmgmt_translator_check($selected_translator_plugin, $job->source_language, $selected_target_language)) {
        case TMTGT_TRANSLATOR_NOT_AVAILABLE:
          $form['translator_wrapper']['options']['settings']['#markup'] = t('Translator is not available: @reason.', array('@reason' => $plugin_controller->getNotAvailableReason()));
          break;
        case TMGMT_TRANSLATOR_NOT_AVAILABLE_FOR_LANGUAGE:
          $form['translator_wrapper']['options']['settings']['#markup'] = t('Test translator is not available for @language.', array('@language' => $available_languages[$selected_target_language]));
          break;
        default:
          $form['translator_wrapper']['options']['settings'] = $plugin_controller->jobSettingsForm(array(), $form_state, $job);
          $form['translator_wrapper']['options']['settings']['#tree'] = TRUE;
          if (empty($form['translator_wrapper']['options']['settings'])) {
            $form['translator_wrapper']['options']['#description'] = t('The @translator translator does not provide any per job settings.', array('@translator' => $plugin_info['label']));
          }
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
      '#value' => t('Order'),
      '#weight' => 50,
    );
    return $form;
  }
  else {
    if ($job->isNew()) {
      drupal_set_message(t('The job is not prepared for checkout yet.'), 'error');
    }
    else {
      drupal_set_message(t('The job has already been processed.'), 'error');
    }
  }
}

/**
 * Submit function, will set a job to request translation status.
 */
function tmgmt_job_checkout_form_submit($form, &$form_state) {
  $values = $form_state['values'];
  // Get job from form_state
  $job = $form_state['job'];
  $job->requestTranslation();

  // Print a message to the screen and redirect the user.
  $messages = $job->getMessagesSince(REQUEST_TIME);
  $had_error = FALSE;
  foreach ($messages as $message) {
    $text = $message->message;
    if (is_array($message->variables)) {
      $text = t($message->message, $message->variables);
    }
    $type = 'status';
    if ($job->isPrepared() || $job->isRejected()) {
      $type = 'error';
      $had_error = TRUE;
    }
    drupal_set_message($text, $type);
  }

  // redirect to job overview page, if no error happened.
  if (!$had_error) {
    drupal_goto('admin/tmgmt');
  }
}

/**
 * Validates the checkout form.
 */
function tmgmt_job_checkout_form_validate($form, &$form_state) {
  $values = $form_state['values'];
  $job = $form_state['job'];
  $plugin = tmgmt_translator_plugin_controller($values['translator']);
  $available_languages = entity_metadata_language_list();
  switch (tmgmt_translator_check($values['translator'], $job->source_language, $values['target_language'])) {
    case TMTGT_TRANSLATOR_NOT_AVAILABLE:
      form_set_error('translator', t('Translator is not available: @reason.', array('@reason' => $plugin->getNotAvailableReason())));
      break;
    case TMGMT_TRANSLATOR_NOT_AVAILABLE_FOR_LANGUAGE:
      form_set_error('translator', t('Test translator is not available for @language.', array('@language' => $available_languages[$values['target_language']])));
      break;
  }
  $job->target_language = $values['target_language'];
  $job->translator = $values['translator'];
  $job->translator_context = $values['settings'];
  if (!$job->isTranslatable()) {
    form_set_error('translator', t('Fail: todo add generic message'));
  }
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

/**
 * Page callback to view a job.
 */
function tmgmt_job_view(TMGMTJob $job) {
  drupal_set_title(entity_label('tmgmt_job', $job));
  return entity_view('tmgmt_job', array($job));
}