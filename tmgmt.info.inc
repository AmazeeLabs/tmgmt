<?php

class TMGMTJobMetadataController extends EntityDefaultMetadataController {

  public function entityPropertyInfo() {
    $info = parent::entityPropertyInfo();
    _tmgmt_override_property_description($info);
    return $info;
  }

}

class TMGMTJobItemMetadataController extends EntityDefaultMetadataController {

  public function entityPropertyInfo() {
    $info = parent::entityPropertyInfo();
    $properties = &$info[$this->type]['properties'];
    _tmgmt_override_property_description($info);

    $properties['tjid'] = array(
      'description' => t('Corresponding job entity.'),
      'type' => 'tmgmt_job',
    ) + $properties['tjid'];

    return $info;
  }

}

class TMGMTTranslatorMetadataController extends EntityDefaultMetadataController {

  public function entityPropertyInfo() {
    $info = parent::entityPropertyInfo();
    _tmgmt_override_property_description($info);
    return $info;
  }

}

function _tmgmt_override_property_description(&$properties) {

  $keys = array_keys($properties);
  $table = $keys[0];
  module_load_install('tmgmt');
  $schema = tmgmt_schema();
  foreach ($properties[$table]['properties'] as $name => $info) {
    if (isset($schema[$table]['fields'][$name]['description'])) {
      $properties[$table]['properties'][$name]['description'] = $schema[$table]['fields'][$name]['description'];
    }
  }

}