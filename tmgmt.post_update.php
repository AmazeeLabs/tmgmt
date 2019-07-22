<?php

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

function tmgmt_post_update_01_make_jobs_revisionable($sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('tmgmt_job');
  /** @var \Drupal\Core\Field\BaseFieldDefinition[] $field_storage_definitions */
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('tmgmt_job');

  // Update the entity type definition.
  $entity_keys = $entity_type->getKeys();
  $entity_keys['revision'] = 'revision_id';
  $entity_type->set('entity_keys', $entity_keys);
  $entity_type->set('revision_table', 'tmgmt_job_revision');
  $revision_metadata_keys = [
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
    'revision_default' => 'revision_default',
    'workspace' => 'workspace',
  ];
  $entity_type->set('revision_metadata_keys', $revision_metadata_keys);

  // Update the field storage definitions and add the new ones required by a
  // revisionable entity type.
  $field_storage_definitions['label']->setRevisionable(TRUE);
  $field_storage_definitions['state']->setRevisionable(TRUE);
  $field_storage_definitions['changed']->setRevisionable(TRUE);

  $field_storage_definitions['revision_id'] = BaseFieldDefinition::create('integer')
    ->setName('revision_id')
    ->setTargetEntityTypeId('tmgmt_job')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision ID'))
    ->setReadOnly(TRUE)
    ->setSetting('unsigned', TRUE);

  $field_storage_definitions['revision_user'] = BaseFieldDefinition::create('entity_reference')
    ->setName('revision_user')
    ->setTargetEntityTypeId('tmgmt_job')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision user'))
    ->setDescription(new TranslatableMarkup('The user ID of the author of the current revision.'))
    ->setSetting('target_type', 'user')
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_created'] = BaseFieldDefinition::create('created')
    ->setName('revision_created')
    ->setTargetEntityTypeId('tmgmt_job')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision create time'))
    ->setDescription(new TranslatableMarkup('The time that the current revision was created.'))
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_log_message'] = BaseFieldDefinition::create('string_long')
    ->setName('revision_log_message')
    ->setTargetEntityTypeId('tmgmt_job')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision log message'))
    ->setDescription(new TranslatableMarkup('Briefly describe the changes you have made.'))
    ->setRevisionable(TRUE)
    ->setDefaultValue('');

  $field_storage_definitions['revision_default'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_default')
    ->setTargetEntityTypeId('tmgmt_job')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Default revision'))
    ->setDescription(new TranslatableMarkup('A flag indicating whether this was a default revision when it was saved.'))
    ->setStorageRequired(TRUE)
    ->setInternal(TRUE)
    ->setTranslatable(FALSE)
    ->setRevisionable(TRUE);

  if (\Drupal::moduleHandler()->moduleExists('workspaces')) {
    // This should be created automatically but it isn't for some reason.
    $field_storage_definitions['workspace'] = BaseFieldDefinition::create('entity_reference')
      ->setName('workspace')
      ->setTargetEntityTypeId('tmgmt_job')
      ->setTargetBundle(NULL)
      ->setLabel(new TranslatableMarkup('Workspace'))
      ->setDescription(new TranslatableMarkup('Indicates the workspace that this revision belongs to.'))
      ->setSetting('target_type', 'workspace')
      ->setInternal(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE);
  }

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

  return t('TMGMT jobs have been converted to be revisionable.');
}

function tmgmt_post_update_02_make_job_items_revisionable($sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('tmgmt_job_item');
  /** @var \Drupal\Core\Field\BaseFieldDefinition[] $field_storage_definitions */
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('tmgmt_job_item');

  // Update the entity type definition.
  $entity_keys = $entity_type->getKeys();
  $entity_keys['revision'] = 'revision_id';
  $entity_type->set('entity_keys', $entity_keys);
  $entity_type->set('revision_table', 'tmgmt_job_item_revision');
  $revision_metadata_keys = [
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
    'revision_default' => 'revision_default',
    'workspace' => 'workspace',
  ];
  $entity_type->set('revision_metadata_keys', $revision_metadata_keys);

  // Update the field storage definitions and add the new ones required by a
  // revisionable entity type.
  $field_storage_definitions['state']->setRevisionable(TRUE);
  $field_storage_definitions['translator_state']->setRevisionable(TRUE);
  $field_storage_definitions['count_pending']->setRevisionable(TRUE);
  $field_storage_definitions['count_translated']->setRevisionable(TRUE);
  $field_storage_definitions['count_reviewed']->setRevisionable(TRUE);
  $field_storage_definitions['count_accepted']->setRevisionable(TRUE);
  $field_storage_definitions['changed']->setRevisionable(TRUE);

  $field_storage_definitions['revision_id'] = BaseFieldDefinition::create('integer')
    ->setName('revision_id')
    ->setTargetEntityTypeId('tmgmt_job_item')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision ID'))
    ->setReadOnly(TRUE)
    ->setSetting('unsigned', TRUE);

  $field_storage_definitions['revision_user'] = BaseFieldDefinition::create('entity_reference')
    ->setName('revision_user')
    ->setTargetEntityTypeId('tmgmt_job_item')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision user'))
    ->setDescription(new TranslatableMarkup('The user ID of the author of the current revision.'))
    ->setSetting('target_type', 'user')
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_created'] = BaseFieldDefinition::create('created')
    ->setName('revision_created')
    ->setTargetEntityTypeId('tmgmt_job_item')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision create time'))
    ->setDescription(new TranslatableMarkup('The time that the current revision was created.'))
    ->setRevisionable(TRUE);

  $field_storage_definitions['revision_log_message'] = BaseFieldDefinition::create('string_long')
    ->setName('revision_log_message')
    ->setTargetEntityTypeId('tmgmt_job_item')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Revision log message'))
    ->setDescription(new TranslatableMarkup('Briefly describe the changes you have made.'))
    ->setRevisionable(TRUE)
    ->setDefaultValue('');

  $field_storage_definitions['revision_default'] = BaseFieldDefinition::create('boolean')
    ->setName('revision_default')
    ->setTargetEntityTypeId('tmgmt_job_item')
    ->setTargetBundle(NULL)
    ->setLabel(new TranslatableMarkup('Default revision'))
    ->setDescription(new TranslatableMarkup('A flag indicating whether this was a default revision when it was saved.'))
    ->setStorageRequired(TRUE)
    ->setInternal(TRUE)
    ->setTranslatable(FALSE)
    ->setRevisionable(TRUE);

  if (\Drupal::moduleHandler()->moduleExists('workspaces')) {
    $field_storage_definitions['workspace'] = BaseFieldDefinition::create('entity_reference')
      ->setName('workspace')
      ->setTargetEntityTypeId('tmgmt_job_item')
      ->setTargetBundle(NULL)
      ->setLabel(new TranslatableMarkup('Workspace'))
      ->setDescription(new TranslatableMarkup('Indicates the workspace that this revision belongs to.'))
      ->setSetting('target_type', 'workspace')
      ->setInternal(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(TRUE);
  }

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);
}
