<?php

/**
 * @file
 * Provides the file translator plugin controller.
 */

/**
 * File translator plugin controller.
 */
class TMGMTFileTranslatorPluginController extends TMGMTDefaultTranslatorPluginController {

  /**
   * {@inheritdoc}
   */
  public function canTranslate(TMGMTTranslator $translator, TMGMTJob $job) {
    // Anything can be exported.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(TMGMTJob $job) {
    $name = "JobID" . $job->tjid . '_' . $job->source_language . '_' . $job->target_language;

    $export = tmgmt_file_format_controller($job->getSetting('export_format'));

    $path = $job->getSetting('scheme') . '://tmgmt_file/' . $name . '.' .  $job->getSetting('export_format');
    $dirname = dirname($path);
    if (file_prepare_directory($dirname, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $file = file_save_data($export->export($job), $path);
      file_usage_add($file, 'tmgmt_file', 'tmgmt_job', $job->tjid);
      $job->submitted('Exported file can be downloaded <a href="!link">here</a>.', array('!link' => file_create_url($path)));

      $email_settings = $job->getTranslator()->getSetting('email');
      if (!empty($email_settings['sending_enabled'])) {
        $mapping = @_tmgmt_file_email_sending_parse_mapping($email_settings['sending']['mapping']);
        if (isset($mapping[$job->target_language])) {
          drupal_mail('tmgmt_file', 'email_sending', $mapping[$job->target_language], language_default(), array(
            'subject' => $email_settings['sending']['subject'],
            'body' => array($email_settings['sending']['message']),
            'attachments' => array($file),
          ));
          if ($email_settings['sending']['forward_copy']) {
            drupal_mail('tmgmt_file', 'email_sending', $email_settings['sending']['forward_copy'], language_default(), array(
              'subject' => 'Forwarded copy: ' . $email_settings['sending']['subject'],
              'body' => array('This email was originally sent to ' . $mapping[$job->target_language], $email_settings['sending']['message']),
              'attachments' => array($file),
            ));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(TMGMTJob $job) {
    return $job->getTranslator()->getSetting('allow_override');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings() {
    return array(
      'export_format' => 'xlf',
      'allow_override' => TRUE,
      'scheme' => 'public',
      // Making this setting TRUE by default is more appropriate, however we
      // need to make it FALSE due to backwards compatibility.
      'xliff_processing' => FALSE,
      'email' => array(
        'sending_enabled' => 0,
        'sending' => array(
          'mapping' => '',
          'subject' => '',
          'message' => '',
          'forward_copy' => '',
        ),
        'receiving_enabled' => 0,
        'receiving' => array(
          'imap_path' => '',
          'imap_username' => '',
          'imap_password' => '',
          'email_on_success' => '',
          'email_on_failure' => '',
        ),
      ),
    );
  }

}
