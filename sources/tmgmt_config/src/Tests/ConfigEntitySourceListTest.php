<?php

/**
 * @file
 * Contains \Drupal\tmgmt_config\Tests\ConfigtEntitySourceListTest.
 */

namespace Drupal\tmgmt_config\Tests;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Tests\EntityTestBase;

/**
 * Tests the user interface for entity translation lists.
 *
 * @group tmgmt
 */
class ConfigEntitySourceListTest extends EntityTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('tmgmt_config', 'config_translation', 'views', 'views_ui');

  protected $nodes = array();

  function setUp() {
    parent::setUp();
    $this->loginAsAdmin();

    $this->loginAsTranslator(array('translate configuration'));

    $this->addLanguage('de');
    $this->addLanguage('it');

    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article',
    ));

    $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Page',
    ));

    $this->drupalCreateContentType(array(
      'type' => 'simplenews_issue',
      'name' => 'Newsletter issue',
    ));
  }

  function testNodeTypeSubmissions() {

    // Simple submission.
    $edit = array(
      'items[article]' => TRUE,
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertText(t('One job needs to be checked out.'));
    $this->assertText(t('Article content type (English to ?, Unprocessed)'));

    // Submit.
    $this->drupalPostForm(NULL, array(), t('Submit to translator'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertUrl('admin/tmgmt/sources/config/node_type');

    $this->assertText(t('Test translation created.'));
    $this->assertText(t('The translation of Article content type to German is finished and can now be reviewed.'));

    // Submission of two different entity types.
    $edit = array(
      'items[article]' => TRUE,
      'items[page]' => TRUE,
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $edit, t('Request translation'));

    // Verify that we are on the translate tab.
    // This is still one job, unlike when selecting more languages.
    $this->assertText(t('One job needs to be checked out.'));
    $this->assertText(t('Article content type and 1 more (English to ?, Unprocessed)'));

    // Submit.
    $this->drupalPostForm(NULL, array(), t('Submit to translator'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertUrl('admin/tmgmt/sources/config/node_type');

    $this->assertText(t('Test translation created.'));
    $this->assertText(t('The translation of Article content type to German is finished and can now be reviewed.'));
    $this->assertText(t('The translation of Page content type to German is finished and can now be reviewed.'));
  }

  function testViewTranslation() {

    // Check if we have appropriate message in case there are no entity
    // translatable content types.
    $this->drupalGet('admin/tmgmt/sources/config/view');
    $this->assertText(t('View overview (Config Entity)'));

    // Request a translation for archive.
    $edit = array(
      'items[archive]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertText(t('One job needs to be checked out.'));
    $this->assertText(t('Archive view (English to ?, Unprocessed)'));

    // Submit.
    $this->drupalPostForm(NULL, array(), t('Submit to translator'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertUrl('admin/tmgmt/sources/config/view');

    $this->assertText(t('Test translation created.'));
    $this->assertText(t('The translation of Archive view to German is finished and can now be reviewed.'));

    // Request a translation for more archive, recent comments, content and job
    // overview.
    $edit = array(
      'items[archive]' => TRUE,
      'items[content_recent]' => TRUE,
      'items[content]' => TRUE,
      'items[tmgmt_job_overview]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertText(t('One job needs to be checked out.'));
    $this->assertText(t('Archive view and 3 more (English to ?, Unprocessed)'));

    // Submit.
    $this->drupalPostForm(NULL, array(), t('Submit to translator'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertUrl('admin/tmgmt/sources/config/view');

    $this->assertText(t('Test translation created.'));
    $this->assertText(t('The translation of Archive view to German is finished and can now be reviewed.'));
    $this->assertText(t('The translation of Recent content view to German is finished and can now be reviewed.'));
    $this->assertText(t('The translation of Content view to German is finished and can now be reviewed.'));
    $this->assertText(t('The translation of Job overview view to German is finished and can now be reviewed.'));
  }

  function testNodeTypeFilter() {

    $this->drupalGet('admin/tmgmt/sources/config/node_type');
    $this->assertText(t('Content type overview (Config Entity)'));

    // Simple filtering.
    $filters = array(
      'search[name]' => '',
      'search[langcode]' => '',
      'search[target_language]' => '',
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $filters, t('Search'));

    // Random text in the name label.
    $filters = array(
      'search[name]' => $this->randomMachineName(5),
      'search[langcode]' => '',
      'search[target_language]' => '',
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $filters, t('Search'));
    $this->assertText(t('No entities matching given criteria have been found.'));

    // Searching for article.
    $filters = array(
      'search[name]' => 'article',
      'search[langcode]' => '',
      'search[target_language]' => '',
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $filters, t('Search'));
    $rows = $this->xpath('//tbody/tr');
    foreach ($rows as $value) {
      $this->assertEqual('Article', (string) $value->td[1]->a);
    }

    // Searching for article, with english source language and italian target language.
    $filters = array(
      'search[name]' => 'article',
      'search[langcode]' => 'en',
      'search[target_language]' => 'it',
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $filters, t('Search'));
    $rows = $this->xpath('//tbody/tr');
    foreach ($rows as $value) {
      $this->assertEqual('Article', (string) $value->td[1]->a);
    }

    // Searching by keywords (shorter terms).
    $filters = array(
      'search[name]' => 'art',
      'search[langcode]' => 'en',
      'search[target_language]' => 'it',
    );
    $this->drupalPostForm('admin/tmgmt/sources/config/node_type', $filters, t('Search'));
    $rows = $this->xpath('//tbody/tr');
    foreach ($rows as $value) {
      $this->assertEqual('Article', (string) $value->td[1]->a);
    }
  }

  /**
   * Test for simple configuration translation.
   */
  function testSimpleConfigTranslation() {
    $this->loginAsTranslator(array('translate configuration'));

    // Go to the translate tab.
    $this->drupalGet('admin/tmgmt/sources/config/_simple_config');

    // Assert some basic strings on that page.
    $this->assertText(t('Simple configuration overview (Config Entity)'));

    // Request a translation for Site information settings.
    $edit = array(
      'items[system.site_information_settings]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Request translation'));

    // Verify that we are on the translate tab.
    $this->assertText(t('One job needs to be checked out.'));
    $this->assertText('System information (English to ?, Unprocessed)');

    // Submit.
    $this->drupalPostForm(NULL, array(), t('Submit to translator'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertUrl('admin/tmgmt/sources/config/_simple_config');

    $overview_url = Url::fromRoute('tmgmt.source_overview', array('plugin' => 'config', 'item_type' => '_simple_config'))->getInternalPath();

    // Translated languages should now be listed as Needs review.
    $this->assertRaw(SafeMarkup::format('href="@url" title="Active job item: Needs review"', array('@url' => JobItem::load(1)->urlInfo()->setOption('query',
      ['destination' => $overview_url])->toString())));

    $this->assertText(t('Test translation created.'));
    $this->assertText('The translation of System information to German is finished and can now be reviewed.');

    // Verify that the pending translation is shown.
    $review = $this->xpath('//table[@id="tmgmt-entities-list"]/tbody/tr[@class="even"][1]/td[@class="langstatus-de"]/a/@href');
    $destination = $this->getAbsoluteUrl($review[0]['href']);
    $this->drupalGet($destination);
    $this->drupalPostForm(NULL, array(), t('Save'));

    // Request a translation for Account settings
    $edit = array(
      'items[entity.user.admin_form]' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Request translation'));

    // Verify that we are on the checkout page.
    $this->assertText(t('One job needs to be checked out.'));
    $this->assertText('Account settings (English to ?, Unprocessed)');
    $this->drupalPostForm(NULL, array(), t('Submit to translator'));

    // Make sure that we're back on the originally defined destination URL.
    $this->assertUrl('admin/tmgmt/sources/config/_simple_config');

    // Translated languages should now be listed as Needs review.
    $links = $this->xpath('//table[@id="tmgmt-entities-list"]/tbody/tr/td/a/@href');
    $counter = 0;
    foreach ($links as $subarray) {
      $counter += count($subarray);
    }
    $this->assertEqual($counter, 2);
  }

}