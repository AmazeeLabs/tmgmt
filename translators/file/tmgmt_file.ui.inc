<?php

/**
 * @file
 * Please supply a file description.
 */

/**
 * File translator plugin controller.
 */
class TMGMTFileTranslatorUIController extends TMGMTDefaultTranslatorUIController {

  /**
   * {@inheritdoc}
   */
  public function pluginSettingsForm($form, &$form_state, TMGMTTranslator $translator, $busy = FALSE) {
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
      $options[$scheme] = check_plain($info['description']);
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

    $email_settings = $translator->getSetting('email');
    $form['email'] = array(
      '#type' => 'fieldset',
      '#title' => t('E-mail automation'),
      '#element_validate' => array('_tmgmt_file_validate_email_settings_element'),
    );

    $mail_system = variable_get('mail_system', array());
    $mimemail_ready = module_exists('mimemail') && isset($mail_system['default-system']) && $mail_system['default-system'] == 'MimeMailSystem';
    $form['email']['sending_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable email sending'),
      '#default_value' => $mimemail_ready && $email_settings['sending_enabled'],
      '#disabled' => !$mimemail_ready,
      '#description' => $mimemail_ready ? '' : t('This feature requires mimemail module. The "Site-wide default MailSystemInterface class" should be set to "MimeMailSystem" at the admin/config/system/mailsystem page.'),
    );
    $form['email']['sending'] = array(
      '#type' => 'fieldset',
      '#title' => t('E-mail sending'),
      '#states' => array(
        'visible' => array(
          'input[name="settings[email][sending_enabled]"]' => array('checked' => TRUE),
        ),
      ),
      '#description' => t('When TMGMT job is submitted to translator, TMGMT will immediately send an email with the exported file attached to an email address defined in the Mapping. If there is no Mapping for the target language, no email will be sent.'),
    );
    $form['email']['sending']['mapping'] = array(
      '#type' => 'textarea',
      '#title' => t('Mapping'),
      '#description' => t('This describes which target languages will be sent to which email addresses.
<br>Please use the following format: "{langcode}: {email-address}". Examples:
<pre>
fr: french-translator@example.com
de-CH: swiss-german-translator@example.com
</pre>'),
      '#default_value' => $email_settings['sending']['mapping'],
    );
    $form['email']['sending']['subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Email subject'),
      '#default_value' => $email_settings['sending']['subject'],
    );
    $form['email']['sending']['message'] = array(
      '#type' => 'textarea',
      '#title' => t('Email message'),
      '#default_value' => $email_settings['sending']['message'],
    );
    $form['email']['sending']['forward_copy'] = array(
      '#type' => 'textfield',
      '#title' => t('Forward a copy to'),
      '#description' => t('Comma-separated list of email addresses. Can be used for testing purposes. Leave empty to not forward a copy.'),
      '#default_value' => $email_settings['sending']['forward_copy'],
    );

    $imap_ready = extension_loaded('imap') && class_exists('\PhpImap\Mailbox');
    $form['email']['receiving_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable email receiving'),
      '#default_value' => $imap_ready && $email_settings['receiving_enabled'],
      '#disabled' => !$imap_ready,
      '#description' => $imap_ready ? '' : t('This feature requires both the PHP IMAP extension and the php-imap/php-imap composer library.'),
    );
    $form['email']['receiving'] = array(
      '#type' => 'fieldset',
      '#title' => t('E-mail receiving'),
      '#states' => array(
        'visible' => array(
          'input[name="settings[email][receiving_enabled]"]' => array('checked' => TRUE),
        ),
      ),
      '#description' => t('During the cron run, TMGMT will:
<ul>
  <li>check the configured mailbox folder,</li>
  <li>grab all unread emails,</li>
  <li>mark them as read,</li>
  <li>fetch all attached files,</li>
  <li>filter out all files with inappropriate extensions,</li>
  <li>try to import files with appropriate extensions into corresponding TMGMT jobs (file name does not matter, the job ID is stored in the file contents),</li>
  <li>log the result for each file (and optionally send an email).</li>
</ul>'),
    );
    $form['email']['receiving']['imap_path'] = array(
      '#type' => 'textfield',
      '#title' => t('Mailbox IMAP path'),
      '#default_value' => $email_settings['receiving']['imap_path'],
      '#description' => t('The IMAP path as specified in the <a href="!url" target="_blank">"mailbox" parameter documentation of the imap_open() PHP function</a>. Examples:
<dl>
  <dt>To check Gmail Inbox:</dt>
    <dd>{imap.gmail.com:993/imap/ssl}INBOX</dd>
  <dt>To check the "translations" label of the Gmail:</dt>
    <dd>{imap.gmail.com:993/imap/ssl}translations</dd>
</dl>
Gmail tip: use a filter that assigns a label to specific emails and uses the "Never send it to Spam" option, because it is not possible to disable the spam filtering for the whole Gmail inbox.', array(
        '!url' => 'http://php.net/manual/en/function.imap-open.php#refsect1-function.imap-open-parameters',
      )),
    );
    $form['email']['receiving']['imap_username'] = array(
      '#type' => 'textfield',
      '#title' => t('Mailbox username'),
      '#default_value' => $email_settings['receiving']['imap_username'],
    );
    $form['email']['receiving']['imap_password'] = array(
      '#type' => 'textfield',
      '#title' => t('Mailbox password'),
      '#default_value' => $email_settings['receiving']['imap_password'],
      '#description' => t('Gmail tip: if two-factor authentication is used, an "app password" should be used here. With normal authentication, the "allow insecure apps" setting should be enabled in the Google account.'),
    );
    $form['email']['receiving']['email_on_success'] = array(
      '#type' => 'textfield',
      '#title' => t('Send an email on a successful file import to'),
      '#description' => t('Comma-separated list of email addresses. Leave empty to not send emails. System log records will be created anyway.'),
      '#default_value' => $email_settings['receiving']['email_on_success'],
    );
    $form['email']['receiving']['email_on_failure'] = array(
      '#type' => 'textfield',
      '#title' => t('Send an email on an unsuccessful file import to'),
      '#description' => t('Comma-separated list of email addresses. Leave empty to not send emails. System log records will be created anyway.'),
      '#default_value' => $email_settings['receiving']['email_on_failure'],
    );
    $form['email']['receiving']['email_to_sender_on_failure'] = array(
      '#type' => 'checkbox',
      '#title' => t('Send an email on an unsuccessful file import to sender'),
      '#description' => t('If checked, sender will get failure report.'),
      '#default_value' => $email_settings['receiving']['email_to_sender_on_failure'],
    );

    return parent::pluginSettingsForm($form, $form_state, $translator);
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm($form, &$form_state, TMGMTJob $job) {
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
  public function checkoutInfo(TMGMTJob $job) {
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
