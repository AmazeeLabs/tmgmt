<?php

/**
 * @file
 * Contains the job UI controller.
 */

/**
 * Entity UI controller for the Job Entity.
 */
class TMGMTJobUIController extends EntityDefaultUIController {

  /**
   * Translation job that is a duplicate of an aborted job.
   *
   * @var TMGMTJob
   */
  private $jobToResubmit = NULL;

  /**
   * {@inheritdoc}
   */
  public function hook_menu() {
    $id_count = count(explode('/', $this->path));
    $wildcard = isset($this->entityInfo['admin ui']['menu wildcard']) ? $this->entityInfo['admin ui']['menu wildcard'] : '%entity_object';
    $items[$this->path . '/' . $wildcard] = array(
      'title callback' => 'entity_label',
      'title arguments' => array($this->entityType, $id_count),
      'page callback' => 'tmgmt_ui_job_view',
      'page arguments' => array($id_count),
      'load arguments' => array($this->entityType),
      'access callback' => 'entity_access',
      'access arguments' => array('view', $this->entityType, $id_count),
      'file' => $this->entityInfo['admin ui']['file'],
      'file path' => $this->entityInfo['admin ui']['file path'],
    );
    $items[$this->path . '/' . $wildcard . '/delete'] = array(
      'page callback' => 'drupal_get_form',
      'page arguments' => array($this->entityType . '_operation_form', $this->entityType, $id_count, $id_count + 1),
      'load arguments' => array($this->entityType),
      'access callback' => 'entity_access',
      'access arguments' => array('delete', $this->entityType, $id_count),
      'type' => MENU_CALLBACK,
    );
    $items[$this->path . '/' . $wildcard . '/abort'] = array(
      'page callback' => 'drupal_get_form',
      'page arguments' => array($this->entityType . '_operation_form', $this->entityType, $id_count, $id_count + 1),
      'load arguments' => array($this->entityType),
      'access callback' => 'entity_access',
      'access arguments' => array('abort', $this->entityType, $id_count),
      'type' => MENU_CALLBACK,
    );
    $items[$this->path . '/' . $wildcard . '/resubmit'] = array(
      'page callback' => 'drupal_get_form',
      'page arguments' => array($this->entityType . '_operation_form', $this->entityType, $id_count, $id_count + 1),
      'load arguments' => array($this->entityType),
      'access callback' => 'entity_access',
      'access arguments' => array('resubmit', $this->entityType, $id_count),
      'type' => MENU_CALLBACK,
    );
    $items[$this->path . '/' . $wildcard . '/clone'] = array(
      'page callback' => 'drupal_get_form',
      'page arguments' => array($this->entityType . '_operation_form', $this->entityType, $id_count, $id_count + 1),
      'load arguments' => array($this->entityType),
      'access callback' => 'entity_access',
      // Cloning jobs is the same action as creating them, so basically we use
      // the same permission.
      'access arguments' => array('create', $this->entityType, $id_count),
      'type' => MENU_CALLBACK,
    );
    $items[$this->path . '/' . $wildcard . '/accept-all'] = array(
      'page callback' => 'drupal_get_form',
      'page arguments' => array('tmgmt_job_accept_all_form', $id_count),
      'load arguments' => array($this->entityType),
      'access callback' => 'entity_access',
      'access arguments' => array('accept', $this->entityType, $id_count),
      'type' => MENU_CALLBACK,
      'file' => $this->entityInfo['admin ui']['file'],
      'file path' => $this->entityInfo['admin ui']['file path'],
    );
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function operationForm($form, &$form_state, $entity, $op) {
    switch ($op) {
      case 'delete':
        $confirm_question = t('Are you sure you want to delete the translation job %label?', array('%label' => $entity->label()));
        return confirm_form($form, $confirm_question, $this->path);
      case 'abort':
        return confirm_form($form, t('Abort this job?'), 'admin/tmgmt/jobs/' . $entity->tjid,
          t('This will send a request to the translator to abort the job. After the action the job translation process will be aborted and only remaining action will be resubmitting it.'));
      case 'resubmit':
        return confirm_form($form, t('Resubmit as a new job?'), 'admin/tmgmt/jobs/' . $entity->tjid,
          t('This creates a new job with the same items which can then be submitted again. In case the sources meanwhile changed, the new job will reflect the update.'));
      case 'clone':
        $form['new_target_language'] = array(
          '#type' => 'select',
          '#title' => t('Target language'),
          '#options' => tmgmt_available_languages(),
        );
        return confirm_form($form, t('Clone job?'), 'admin/tmgmt/jobs/' . $entity->tjid,
          t('This creates a new job as a clone of the selected one.'));
    }
    drupal_not_found();
    exit;
  }

  /**
   * {@inheritdoc}
   */
  public function applyOperation($op, $entity) {
    switch ($op) {
      case 'delete':
        $entity->delete();
        return t('Deleted the translation job %label.', array('%label' => $entity->label()));
      case 'abort':
        if (!$entity->abortTranslation()) {
          // This is the case when a translator does not support the abort operation.
          // It would make more sense to not display the button for the action,
          // however we do not know if the translator is able to abort a job until
          // we trigger the action.
          foreach ($entity->getMessagesSince() as $message) {
            if ($message->type == 'debug') {
              continue;
            }
            if ($text = $message->getMessage()) {
              // We want to persist also the type therefore we will set the
              // messages directly and not return them.
              drupal_set_message(filter_xss($text), $message->type);
            }
          }
        }
        break;
      case 'resubmit':
        $this->cloneJob($entity);

        $entity->addMessage('Job has been duplicated as a new job <a href="@url">#@id</a>.',
          array('@url' => url('admin/tmgmt/jobs/' . $this->jobToResubmit->tjid), '@id' => $this->jobToResubmit->tjid));
        $this->jobToResubmit->addMessage('This job is a duplicate of the previously aborted job <a href="@url">#@id</a>',
          array('@url' => url('admin/tmgmt/jobs/' . $entity->tjid), '@id' => $entity->tjid));

        return t('The aborted job has been duplicated. You can resubmit it now.');

      case 'clone':
        $overwrite_values = array();
        // Check if we need to have a new language for the clone.
        if (!empty($entity->new_target_language)) {
          $overwrite_values['target_language'] = $entity->new_target_language;
        }
        $this->cloneJob($entity, $overwrite_values);

        $entity->addMessage('Job has been cloned into a new job <a href="@url">#@id</a>.',
          array('@url' => url('admin/tmgmt/jobs/' . $this->jobToResubmit->tjid), '@id' => $this->jobToResubmit->tjid));
        $this->jobToResubmit->addMessage('This job is a clone of <a href="@url">#@id</a>',
          array('@url' => url('admin/tmgmt/jobs/' . $entity->tjid), '@id' => $entity->tjid));

        return t('The job has been cloned.');
    }
    return FALSE;
  }

  /**
   * Operation form submit callback.
   */
  public function operationFormSubmit($form, &$form_state) {
    // When we clone a job we can also choose a different target language. So
    // in this case we will add this information to the tmgmt job object so that
    // it can be used in the ::applyOperation() method above.
    if ($form_state['op'] == 'clone') {
      $form_state['tmgmt_job']->new_target_language = $form_state['values']['new_target_language'];
    }
    parent::operationFormSubmit($form, $form_state);
    if ($form_state['op'] == 'resubmit') {
      $form_state['redirect'] = $this->path . '/' . $this->jobToResubmit->tjid;
    }
  }

  /**
   * Helper to clone a job. This will also update the $jobToResubmit member of
   * this class.
   *
   * @param TMGMTJob $entity
   *  The job item to clone.
   *
   * @param array $overwrite_values
   *  An array with value to overwrite in the new clone.
   */
  protected function cloneJob($entity, $overwrite_values = array()) {
    $this->jobToResubmit = $entity->cloneAsUnprocessed();
    $this->jobToResubmit->uid = $GLOBALS['user']->uid;
    if (!empty($overwrite_values)) {
      foreach ($overwrite_values as $key => $value) {
        $this->jobToResubmit->{$key} = $value;
      }
    }
    $this->jobToResubmit->save();
    /** @var TMGMTJobItem $item */
    foreach ($entity->getItems() as $item) {
      $item_to_resubmit = $item->cloneAsActive();
      $this->jobToResubmit->addExistingItem($item_to_resubmit);
    }
  }

}
