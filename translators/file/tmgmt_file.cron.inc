<?php

/**
 * @file
 * Cron functions.
 */

/**
 * Checks mailboxes and imports XLF files from unread emails.
 *
 * For the overall process description
 * @see \TMGMTFileTranslatorUIController::pluginSettingsForm()
 */
function _tmgmt_file_check_mailboxes() {
  /** @var TMGMTTranslator[] $translators */
  $translators = entity_load('tmgmt_translator', FALSE, array('plugin' => 'file'));
  if (empty($translators)) {
    return;
  }
  foreach ($translators as $translator) {
    $attachments_dir = TMGMT_FILE_EMAIL_ATTACHMENTS_DIR;
    if (!file_prepare_directory($attachments_dir, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $message = array('Cannot prepare attachments directory. Stopping email checking.');
      _tmgmt_file_email_receive_log($message, 'failure', $translator);
      continue;
    }
    $email_settings = $translator->getSetting('email');
    if ($email_settings['receiving_enabled']) {
      try {
        $mailbox = tmgmt_file_mailbox($email_settings['receiving']['imap_path'], $email_settings['receiving']['imap_username'], $email_settings['receiving']['imap_password']);
        $mail_ids = $mailbox->searchMailBox('UNSEEN');
        foreach ($mail_ids as $mail_id) {
          $mail = $mailbox->getMail($mail_id);
          $attachments = $mail->getAttachments();
          foreach ($attachments as $attachment) {
            $file = NULL;
            $job = NULL;
            $extension = pathinfo($attachment->name, PATHINFO_EXTENSION);
            if ($extension == $translator->getSetting('export_format') && strtolower($attachment->disposition) == 'attachment') {
              if ($controller = tmgmt_file_format_controller($extension)) {
                _tmgmt_file_get_new_error_messages();
                $fail_message = NULL;
                $fh = fopen($attachment->filePath, 'r');
                if ($file = file_save_data($fh, $attachments_dir . '/' . $attachment->name)) {
                  if ($job = $controller->validateImport($file->uri)) {
                    try {
                      // Validation successful, start import.
                      $job->addTranslatedData($controller->import($file->uri));
                      $job->addMessage('Successfully imported file.');
                      $message = array("Successfully imported file.");
                      _tmgmt_file_email_receive_log($message, 'success', $translator, $mail, $attachment, $file, $job);
                    } catch (Exception $e) {
                      $job->addMessage('File import failed with the following message: @message', array('@message' => $e->getMessage()), 'error');
                      $fail_message = $e->getMessage();
                    }
                  }
                  else {
                    $job = NULL;
                    $fail_message = 'XLIFF validation failed.';
                  }
                }
                else {
                  $fail_message = 'Cannot save file.';
                }
                fclose($fh);
                if ($fail_message !== NULL) {
                  $message = array("File import failed. Error message: $fail_message");
                  if ($error_messages = _tmgmt_file_get_new_error_messages()) {
                    $message[] = 'The following error messages have been captured during the import:';
                    foreach ($error_messages as $error_message) {
                      $message[] = '  - ' . $error_message;
                    }
                  }
                  _tmgmt_file_email_receive_log($message, 'failure', $translator, $mail, $attachment, $file, $job);
                }
              }
            }
            unlink($attachment->filePath);
          }
        }
      }
      catch (Exception $e) {
        $fail_message = check_plain($e->getMessage());
        $message = array("Cannot check mailbox: $fail_message.");
        _tmgmt_file_email_receive_log($message, 'failure', $translator);
      }
    }
  }
}

/**
 * Saves message into the log and optionally sends an email.
 *
 * @param array $message
 *   A message as an array of strings. Can be used as email body.
 * @param string $type
 *   Message type. Either "success" or "failure".
 * @param TMGMTTranslator $translator
 * @param \PhpImap\IncomingMail|null $mail
 * @param \PhpImap\IncomingMailAttachment|null $attachment
 * @param object|null $file
 * @param TMGMTJob $job
 */
function _tmgmt_file_email_receive_log(array $message, $type, TMGMTTranslator $translator, \PhpImap\IncomingMail $mail = NULL, \PhpImap\IncomingMailAttachment $attachment = NULL, $file = NULL, TMGMTJob $job = NULL) {
  if ($mail) {
    $message[] = '';
    $message[] = "Email info:";
    $message[] = "  - Subject: " . check_plain($mail->subject);
    $message[] = "  - From: " . check_plain("$mail->fromName <$mail->fromAddress>");
    $message[] = "  - Date: " . check_plain($mail->date);
  }
  if ($attachment) {
    $message[] = '';
    $message[] = "Attachment filename: " . check_plain($attachment->name);
  }
  if ($file) {
    $message[] = '';
    $message[] = "Saved file URI: " . check_plain($file->uri);
  }
  if ($job) {
    $message[] = '';
    $message[] = "Job link: " . url('admin/tmgmt/jobs/' . $job->tjid, array('absolute' => TRUE));
  }
  $message[] = '';
  $message[] = "Translator link: " . url('admin/config/regional/tmgmt_translator/manage/' . $translator->name, array('absolute' => TRUE));

  watchdog('tmgmt_file', implode("\n<br>", $message), array(), ($type == 'success' ? WATCHDOG_INFO : WATCHDOG_ERROR));
  $email_settings = $translator->getSetting('email');
  if ($type == 'success' && $email_settings['receiving']['email_on_success']) {
    drupal_mail('tmgmt_file', 'email_receiving', $email_settings['receiving']['email_on_success'], language_default(), array(
      'subject' => 'Successful TMGMT email attachment import on ' . check_plain($GLOBALS["base_url"]),
      'body' => $message,
    ));
  }
  elseif ($type == 'failure') {
    if ($email_settings['receiving']['email_on_failure']) {
      drupal_mail('tmgmt_file', 'email_receiving', $email_settings['receiving']['email_on_failure'], language_default(), array(
        'subject' => 'Failed TMGMT email attachment import on ' . check_plain($GLOBALS["base_url"]),
        'body' => $message,
      ));
    }
    if ($email_settings['receiving']['email_to_sender_on_failure']) {
      drupal_mail('tmgmt_file', 'email_receiving', $mail->fromAddress, language_default(), array(
        'subject' => 'Failed translation import on ' . check_plain($GLOBALS["base_url"]),
        'body' => $message,
      ));
    }
  }
}
