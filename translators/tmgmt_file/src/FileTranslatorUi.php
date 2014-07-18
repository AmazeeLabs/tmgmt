<?php

/**
 * @file
 * Contains \Drupal\tmgmt_file\FileTranslatorUi:
 */

namespace Drupal\tmgmt_file;

use Drupal\Component\Utility\String;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\Entity\Job;

/**
 * File translator UI.
 */
class FileTranslatorUi extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function pluginSettingsForm($form, &$form_state, Translator $translator, $busy = FALSE) {
    $form['export_format'] = array(
      '#type' => 'radios',
      '#title' => t('Export to'),
      '#options' => tmgmt_file_format_plugin_labels(),
      '#default_value' => $translator->getSetting('export_format'),
      '#description' => t('Please select the format you want to export data.'),
    );

    $form['xliff_processing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Extended XLIFF processing'),
      '#description' => t('Check to further process content semantics and mask HTML tags instead just escaping it.'),
      '#default_value' => $translator->getSetting('xliff_processing'),
    );

    $form['allow_override'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow to override the format per job'),
      '#default_value' => $translator->getSetting('allow_override'),
    );

    // Any visible, writeable wrapper can potentially be used for the files
    // directory, including a remote file system that integrates with a CDN.
    foreach (file_get_stream_wrappers(STREAM_WRAPPERS_WRITE_VISIBLE) as $scheme => $info) {
      $options[$scheme] = String::checkPlain($info['description']);
    }

    if (!empty($options)) {
      $form['scheme'] = array(
        '#type' => 'radios',
        '#title' => t('Download method'),
        '#default_value' => $translator->getSetting('scheme'),
        '#options' => $options,
        '#description' => t('Choose the location where exported files should be stored. The usage of a protected location (e.g. private://) is recommended to prevent unauthorized access.'),
      );
    }

    return parent::pluginSettingsForm($form, $form_state, $translator);
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm($form, &$form_state, Job $job) {
    if ($job->getTranslator()->getSetting('allow_override')) {
      $form['export_format'] = array(
        '#type' => 'radios',
        '#title' => t('Export to'),
        '#options' => tmgmt_file_format_plugin_labels(),
        '#default_value' => $job->getTranslator()->getSetting('export_format'),
        '#description' => t('Please select the format you want to export data.'),
      );
    }
    return parent::checkoutSettingsForm($form, $form_state, $job);
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(Job $job) {
    // If the job is finished, it's not possible to import translations anymore.
    if ($job->isFinished()) {
      return parent::checkoutInfo($job);
    }
    $form = array(
      '#type' => 'fieldset',
      '#title' => t('Import translated file'),
    );

    $supported_formats = array_keys(tmgmt_file_format_plugin_info());
    $form['file'] = array(
      '#type' => 'file',
      '#title' => t('File file'),
      '#size' => 50,
      '#description' => t('Supported formats: @formats.', array('@formats' => implode(', ', $supported_formats))),
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Import'),
      '#submit' => array('tmgmt_file_import_form_submit'),
    );
    return $this->checkoutInfoWrapper($job, $form);
  }

}
