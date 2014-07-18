<?php

/*
 * @file
 * Contains Drupal\tmgmt\Tests\TMGMTTestBase.
 */

namespace Drupal\tmgmt\Tests;

use Drupal\Core\Language\Language;
use Drupal\simpletest\WebTestBase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\Translator;

/**
 * Base class for tests.
 */
abstract class TMGMTTestBase extends WebTestBase {

  /**
   * A default translator using the test translator.
   *
   * @var Translator
   */
  protected $default_translator;

  /**
   * List of permissions used by loginAsAdmin().
   *
   * @var array
   */
  protected $admin_permissions = array();

  /**
   * Drupal user object created by loginAsAdmin().
   *
   * @var object
   */
  protected $admin_user = NULL;

  /**
   * List of permissions used by loginAsTranslator().
   *
   * @var array
   */
  protected $translator_permissions = array();

  /**
   * Drupal user object created by loginAsTranslator().
   *
   * @var object
   */
  protected $translator_user = NULL;

  /**
   * The language weight for new languages.
   *
   * @var int
   */
  protected $languageWeight = 1;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tmgmt', 'tmgmt_test', 'node');

  /**
   * Overrides DrupalWebTestCase::setUp()
   */
  function setUp() {
    parent::setUp();
    $this->default_translator = tmgmt_translator_load('test_translator');

    // Load default admin permissions.
    $this->admin_permissions = array(
      'administer languages',
      'access administration pages',
      'administer content types',
      'administer tmgmt',
    );

    // Load default translator user permissions.
    $this->translator_permissions = array(
      'create translation jobs',
      'submit translation jobs',
      'accept translation jobs',
    );
  }

  /**
   * Will create a user with admin permissions and log it in.
   *
   * @param array $additional_permissions
   *   Additional permissions that will be granted to admin user.
   * @param boolean $reset_permissions
   *   Flag to determine if default admin permissions will be replaced by
   *   $additional_permissions.
   *
   * @return object
   *   Newly created and logged in user object.
   */
  function loginAsAdmin($additional_permissions = array(), $reset_permissions = FALSE) {
    $permissions = $this->admin_permissions;

    if ($reset_permissions) {
      $permissions = $additional_permissions;
    }
    elseif (!empty($additional_permissions)) {
      $permissions = array_merge($permissions, $additional_permissions);
    }

    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
    return $this->admin_user;
  }

  /**
   * Will create a user with translator permissions and log it in.
   *
   * @param array $additional_permissions
   *   Additional permissions that will be granted to admin user.
   * @param boolean $reset_permissions
   *   Flag to determine if default admin permissions will be replaced by
   *   $additional_permissions.
   *
   * @return object
   *   Newly created and logged in user object.
   */
  function loginAsTranslator($additional_permissions = array(), $reset_permissions = FALSE) {
    $permissions = $this->translator_permissions;

    if ($reset_permissions) {
      $permissions = $additional_permissions;
    }
    elseif (!empty($additional_permissions)) {
      $permissions = array_merge($permissions, $additional_permissions);
    }

    $this->translator_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->translator_user);
    return $this->translator_user;
  }

  /**
   * Creates, saves and returns a translator.
   *
   * @return \Drupal\tmgmt\Entity\Translator
   */
  function createTranslator() {
    $translator = entity_create('tmgmt_translator', array());
    $translator->name = strtolower($this->randomName());
    $translator->label = $this->randomName();
    $translator->plugin = 'test_translator';
    $translator->settings = array(
      'key' => $this->randomName(),
      'another_key' => $this->randomName(),
    );
    $this->assertEqual(SAVED_NEW, $translator->save());
    return $translator;
  }

  /**
   * Creates, saves and returns a translation job.
   *
   * @return \Drupal\tmgmt\Entity\Job
   */
  function createJob($source = 'en', $target = 'de', $uid = 1)  {
    $job = tmgmt_job_create($source, $target, $uid);
    $this->assertEqual(SAVED_NEW, $job->save());

    // Assert that the translator was assigned a tid.
    $this->assertTrue($job->id() > 0);
    return $job;
  }


  /**
   * Sets the proper environment.
   *
   * Currently just adds a new language.
   *
   * @param string $langcode
   *   The language code.
   */
  function addLanguage($langcode) {
    // Add the language.
    $edit = array(
      'id' => $langcode,
      'weight' => $this->languageWeight++,
    );
    $language = new Language($edit);
    language_save($language);
  }

  /**
   * Asserts job item language codes.
   *
   * @param TMGMTJobItem $job_item
   *   Job item to check.
   * @param string $expected_source_lang
   *   Expected source language.
   * @param array $actual_lang_codes
   *   Expected existing language codes (translations).
   */
  function assertJobItemLangCodes(JobItem $job_item, $expected_source_lang, array $actual_lang_codes) {
    $this->assertEqual($job_item->getSourceLangCode(), $expected_source_lang);
    $existing = $job_item->getExistingLangCodes();
    sort($existing);
    sort($actual_lang_codes);
    $this->assertEqual($existing, $actual_lang_codes);
  }

}