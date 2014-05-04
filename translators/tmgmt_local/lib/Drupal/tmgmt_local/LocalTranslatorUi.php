<?php

/**
 * @file
 * Contains \Drupal\tmgmt_local\LocalTranslatorUi.
 */

namespace Drupal\tmgmt_local;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorPluginUiBase;

/**
 * Local translator plugin UI.
 */
class LocalTranslatorUi extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm($form, &$form_state, Job $job) {
    if ($translators = tmgmt_local_translators($job->getSourceLangcode(), array($job->getTargetLangcode()))) {
      $form['translator'] = array(
        '#title' => t('Select translator for this job'),
        '#type' => 'select',
        '#options' => array('' => t('Select user')) + $translators,
        '#default_value' => $job->getSetting('translator'),
      );
    }
    else {
      $form['message'] = array(
        '#markup' => t('There are no translators available.'),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(Job $job) {
    $label = $job->getTranslator()->label();
    $form['#title'] = t('@translator translation job information', array('@translator' => $label));
    $form['#type'] = 'fieldset';

    $tuid = $job->getSetting('translator');
    if ($tuid && $translator = user_load($tuid)) {
      $form['job_status'] = array(
        '#type' => 'item',
        '#title' => t('Job status'),
        '#markup' => t('Translation job is assigned to %name.', array('%name' => $translator->getUsername())),
      );
    }
    else {
      $form['job_status'] = array(
        '#type' => 'item',
        '#title' => t('Job status'),
        '#markup' => t('Translation job is not assigned to any translator.'),
      );
    }

    if ($job->getSetting('job_comment')) {
      $form['job_comment'] = array(
        '#type' => 'item',
        '#title' => t('Job comment'),
        '#markup' => filter_xss($job->getSetting('job_comment')),
      );
    }

    return $form;
  }

  public function pluginSettingsForm($form, &$form_state, Translator $translator, $busy = FALSE) {
    $form['allow_all'] = array(
      '#title' => t('Allow translations for enabled languages even if no translator has the necessary capabilities'),
      '#type' => 'checkbox',
      '#default_value' => $translator->getSetting('allow_all'),
    );
    return $form;
  }

}