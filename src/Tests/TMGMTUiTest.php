<?php

/**
 * @file
 * Contains Drupal\tmgmt\Tests\TMGMTUiTest.
 */

namespace Drupal\tmgmt\Tests;

use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\Entity\Translator;
use Drupal\filter\Entity\FilterFormat;
use Drupal\tmgmt\Tests\EntityTestBase;

/**
 * Verifies basic functionality of the user interface
 *
 * @group tmgmt
 */
class TMGMTUiTest extends EntityTestBase {

  public static $modules = ['ckeditor', 'tmgmt_content'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $filtered_html_format = FilterFormat::create(array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
    ));
    $filtered_html_format->save();

    // Login as admin to be able to set environment variables.
    $this->loginAsAdmin();
    $this->addLanguage('de');
    $this->addLanguage('es');
    $this->addLanguage('el');

    // Login as translator only with limited permissions to run these tests.
    $this->loginAsTranslator(array(
      'access administration pages',
      'create translation jobs',
      'submit translation jobs',
      $filtered_html_format->getPermissionName(),
    ), TRUE);
    $this->drupalPlaceBlock('system_breadcrumb_block');

    $this->createNodeType('page', 'Page', TRUE);
    $this->createNodeType('article', 'Article', TRUE);
  }

  /**
   * Test the page callbacks to create jobs and check them out.
   *
   * This includes
   * - Varying checkout situations with form detail values.
   * - Unsupported checkout situations where translator is not available.
   * - Exposed filters for job overview
   * - Deleting a job
   *
   * @todo Separate the exposed filter admin overview test.
   */
  function testCheckoutForm() {
    // Add a first item to the job. This will auto-create the job.
    $job = tmgmt_job_match_item('en', '');
    $job->addItem('test_source', 'test', 1);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

    // Test primary buttons.
    $this->assertRaw('Save job" class="button js-form-submit form-submit"');

    // Check checkout form.
    $this->assertText('test_source:test:1');

    // Add two more job items.
    $job->addItem('test_source', 'test', 2);
    $job->addItem('test_source', 'test', 3);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

    // Check checkout form.
    $this->assertText('test_source:test:1');
    $this->assertText('test_source:test:2');
    $this->assertText('test_source:test:3');

    // @todo: Test ajax functionality.

    // Attempt to translate into greek.
    $edit = array(
      'target_language' => 'el',
      'settings[action]' => 'translate',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));
    $this->assertText(t('@translator can not translate from @source to @target.', array('@translator' => 'Test provider', '@source' => 'English', '@target' => 'Greek')));

    // Job still needs to be in state new.
    $job = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job->isUnprocessed());

    $edit = array(
      'target_language' => 'es',
      'settings[action]' => 'translate',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));

    // Job needs to be in state active.
    $job = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job->isActive());
    foreach ($job->getItems() as $job_item) {
      /* @var $job_item JobItemInterface */
      $this->assertTrue($job_item->isNeedsReview());
    }
    $this->assertText(t('Test translation created'));
    $this->assertNoText(t('Test provider called'));

    // Test redirection.
    $this->assertText(t('Job overview'));

    // Another job.
    $previous_tjid = $job->id();
    $job = tmgmt_job_match_item('en', '');
    $job->addItem('test_source', 'test', 1);
    $this->assertNotEqual($job->id(), $previous_tjid);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

     // Check checkout form.
    $this->assertText('You can provide a label for this job in order to identify it easily later on.');
    $this->assertText('test_source:test:1');

    $edit = array(
      'target_language' => 'es',
      'settings[action]' => 'submit',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));
    $this->assertText(t('Test submit'));
    $job = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job->isActive());

    // Another job.
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 1);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

     // Check checkout form.
    $this->assertText('You can provide a label for this job in order to identify it easily later on.');
    $this->assertText('test_source:test:1');

    $edit = array(
      'settings[action]' => 'reject',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));
    $this->assertText(t('This is not supported'));
    $job = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job->isRejected());

    // Check displayed job messages.
    $args = array('@view' => 'view-tmgmt-job-messages');
    $this->assertEqual(2, count($this->xpath('//div[contains(@class, @view)]//tbody/tr', $args)));

    // Check that the author for each is the current user.
    $message_authors = $this->xpath('////div[contains(@class, @view)]//td[contains(@class, @field)]/span', $args + array('@field' => 'views-field-name'));
    $this->assertEqual(2, count($message_authors));
    foreach ($message_authors as $message_author) {
      $this->assertEqual((string)$message_author, $this->translator_user->getUsername());
    }

    // Make sure that rejected jobs can be re-submitted.
    $this->assertTrue($job->isSubmittable());
    $edit = array(
      'settings[action]' => 'translate',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));
    $this->assertText(t('Test translation created'));

    // HTML tags count.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Six dummy HTML tags in the title.</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p>Two dummy HTML tags in the body.</p>',
          '#label' => 'Body',
        )
      ),
    ));
    $item4 = $job->addItem('test_source', 'test', 4);
    $this->drupalGet('admin/tmgmt/items/' . $item4->id());

    // Test primary buttons.
    $this->assertRaw('Save" class="button button--primary js-form-submit form-submit"');
    $this->drupalPostForm(NULL, NULL, t('Save'));
    $this->clickLink('View');
    $this->assertRaw('Save as completed" class="button button--primary js-form-submit form-submit"');
    $this->drupalPostForm(NULL, NULL, t('Save'));
    $this->assertRaw('Save job" class="button button--primary js-form-submit form-submit"');
    $this->drupalPostForm(NULL, NULL, t('Save job'));
    $this->drupalGet('admin/tmgmt/jobs');

    // Total number of tags should be 8.
    $tags = trim((int) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[7]);
    $this->assertEqual($tags, 8);

    // Another job.
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 1);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

     // Check checkout form.
    $this->assertText('You can provide a label for this job in order to identify it easily later on.');
    $this->assertText('test_source:test:1');

    $edit = array(
      'settings[action]' => 'fail',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));
    $this->assertText(t('Service not reachable'));
    $job = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job->isUnprocessed());

    // Verify that we are still on the form.
    $this->assertText('You can provide a label for this job in order to identify it easily later on.');

    // Another job.
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 1);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

    // Check checkout form.
    $this->assertText('You can provide a label for this job in order to identify it easily later on.');
    $this->assertText('test_source:test:1');

    $edit = array(
      'settings[action]' => 'not_translatable',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));
    // @todo Update to correct failure message.
    $this->assertText(t('Fail'));
    $job = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job->isUnprocessed());

    // Test default settings.
    $this->default_translator->setSetting('action', 'reject');
    $this->default_translator->save();
    $job = tmgmt_job_match_item('en', 'es');
    $job->addItem('test_source', 'test', 1);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

     // Check checkout form.
    $this->assertText('You can provide a label for this job in order to identify it easily later on.');
    $this->assertText('test_source:test:1');

    // The action should now default to reject.
    $this->drupalPostForm(NULL, array(), t('Submit to provider'));
    $this->assertText(t('This is not supported.'));
    $job4 = entity_load_unchanged('tmgmt_job', $job->id());
    $this->assertTrue($job4->isRejected());

    // Test for job checkout form, if the target language is supported,
    // the test translator should say it is supported.
    $job = tmgmt_job_create('en', 'de', 0);
    $job->save();
    $edit = array(
      'target_language' => 'de',
    );
    $this->drupalPostAjaxForm('admin/tmgmt/jobs/' . $job->id(), $edit, 'target_language');
    $this->assertFieldByXPath('//select[@id="edit-translator"]/option[1]', 'Test provider');

    $this->drupalGet('admin/tmgmt/jobs');

    // Test if sources languages are correct.
    $sources = $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr/td[@class="views-field views-field-source-language-1"][contains(., "English")]');
    $this->assertEqual(count($sources), 5);

    // Test if targets languages are correct.
    $targets = $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr/td[@class="views-field views-field-target-language"][contains(., "Spanish") or contains(., "German")]');
    $this->assertEqual(count($targets), 5);

    // Check that the first action is 'manage'.
    $first_action = $this->xpath('//tbody/tr[2]/td[10]/div/div/ul/li[1]/a');
    $this->assertEqual($first_action[0][0], 'Manage');

    // Test for Unavailable/Unconfigured Translators.
    $this->default_translator->setSetting('action', 'not_translatable');
    $this->default_translator->save();
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->drupalPostForm(NULL, array(), t('Submit to provider'));
    $this->assertText(t('Test provider can not translate from English to German.'));

    // Test for Unavailable/Unconfigured Translators.
    $this->default_translator->setSetting('action', 'not_available');
    $this->default_translator->save();
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->assertText(t('Test provider is not available. Make sure it is properly configured.'));
    $this->drupalPostForm(NULL, array(), t('Submit to provider'));
    $this->assertText(t('@translator is not available. Make sure it is properly configured.', array('@translator' => 'Test provider')));

    // Login as administrator to delete a job.
    $this->loginAsAdmin();
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => 'All',
    )));

    // Translated languages should now be listed as Needs review.
    $start_rows = $this->xpath('//tbody/tr');
    $this->assertEqual(count($start_rows), 5);
    $this->drupalGet($job4->urlInfo('delete-form'));
    $this->assertText('Are you sure you want to delete the translation job test_source:test:1 and 2 more?');
    $this->drupalPostForm(NULL, array(), t('Delete'));
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => 'All',
    )));
    $end_rows = $this->xpath('//tbody/tr');
    $this->assertEqual(count($end_rows), 4);
  }

  /**
   * Tests the tmgmt_job_checkout() function.
   */
  function testCheckoutFunction() {
    $job = $this->createJob();

    // Check out a job when only the test translator is available. That one has
    // settings, so a checkout is necessary.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->assertEqual($job->urlInfo()->getInternalPath(), $redirects[0]);
    $this->assertTrue($job->isUnprocessed());
    $job->delete();

    // Hide settings on the test translator.
    $default_translator = Translator::load('test_translator');
    $default_translator
      ->setSetting('expose_settings', FALSE)
      ->save();
    $job = $this->createJob();

    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->assertFalse($redirects);
    $this->assertTrue($job->isActive());

    // A job without target language needs to be checked out.
    $job = $this->createJob('en', '');
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->assertEqual($job->urlInfo()->getInternalPath(), $redirects[0]);
    $this->assertTrue($job->isUnprocessed());

    // Create a second file translator. This should check
    // out immediately.
    $job = $this->createJob();

    $second_translator = $this->createTranslator();
    $second_translator
      ->setSetting('expose_settings', FALSE)
      ->save();

    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->assertEqual($job->urlInfo()->getInternalPath(), $redirects[0]);
    $this->assertTrue($job->isUnprocessed());
  }

  /**
   * Tests of the job item review process.
   */
  public function testReview() {
    $job = $this->createJob();
    $job->translator = $this->default_translator->id();
    $job->settings = array();
    $job->save();
    $item = $job->addItem('test_source', 'test', 1);

    $data = \Drupal::service('tmgmt.data')->flatten($item->getData());
    $keys = array_keys($data);
    $key = $keys[0];

    $this->drupalGet('admin/tmgmt/items/' . $item->id());

    // Testing the title of the preview page.
    $this->assertText(t('Job item @source_label', array('@source_label' => $job->label())));

    // Testing the result of the
    // TMGMTTranslatorUIControllerInterface::reviewDataItemElement()
    $this->assertText(t('Testing output of review data item element @key from the testing provider.', array('@key' => $key)));

    // Test the review tool source textarea.
    $this->assertFieldByName('dummy|deep_nesting[source]', $data[$key]['#text']);

    // Save translation.
    $this->drupalPostForm(NULL, array('dummy|deep_nesting[translation]' => $data[$key]['#text'] . 'translated'), t('Save'));

    // Test review data item.
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->drupalPostAjaxForm(NULL, [], 'reviewed-dummy|deep_nesting');
    $this->assertRaw('icons/73b355/check.svg" alt="Reviewed"');

    \Drupal::entityTypeManager()->getStorage('tmgmt_job')->resetCache();
    \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->resetCache();
    /** @var JobItem $item */
    $item = JobItem::load($item->id());
    $this->assertEqual($item->getCountReviewed(), 1, 'Item reviewed correctly.');

    // Check if translation has been saved.
    $this->assertFieldByName('dummy|deep_nesting[translation]', $data[$key]['#text'] . 'translated');

    // Tests for the minimum height of the textareas.
    $rows = $this->xpath('//textarea[@name="dummy|deep_nesting[source]"]');
    $this->assertEqual((string) $rows[0]['rows'], 3);

    $rows2 = $this->xpath('//textarea[@name="dummy|deep_nesting[translation]"]');
    $this->assertEqual((string) $rows2[0]['rows'], 3);

    // Test for the dynamical height of the source textarea.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => str_repeat('Text for job item', 20),
          '#label' => 'Label',
        ),
      ),
    ));
    $item2 = $job->addItem('test_source', 'test', 2);
    $this->drupalGet('admin/tmgmt/items/' . $item2->id());

    $rows3 = $this->xpath('//textarea[@name="dummy|deep_nesting[source]"]');
    $this->assertEqual((string) $rows3[0]['rows'], 4);

    // Test for the maximum height of the source textarea.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => str_repeat('Text for job item', 100),
          '#label' => 'Label',
        ),
      ),
    ));
    $item3 = $job->addItem('test_source', 'test', 3);
    $this->drupalGet('admin/tmgmt/items/' . $item3->id());

    $rows4 = $this->xpath('//textarea[@name="dummy|deep_nesting[source]"]');
    $this->assertEqual((string) $rows4[0]['rows'], 15);

    // Tests the HTML tags validation.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Source text bold and Italic</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Source body bold and Italic</strong></em></p>',
          '#label' => 'Body',
        )
      ),
    ));
    $item4 = $job->addItem('test_source', 'test', 4);
    $this->drupalGet('admin/tmgmt/items/' . $item4->id());

    // Drop <strong> tag in translated text.
    $edit = array(
      'title|deep_nesting[translation]' => '<em>Translated italic text missing paragraph</em>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('Expected tags @tags not found.', array('@tags' => '<p>,<strong>,</strong>,</p>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '<p>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '<strong>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '</strong>')));
    $this->assertText(t('@tag expected 1, found 0.', array('@tag' => '</p>')));
    $this->assertText(t('HTML tag validation failed for 1 field(s).'));

    // Change the order of HTML tags.
    $edit = array(
      'title|deep_nesting[translation]' => '<p><strong><em>Translated text Italic and bold</em></strong></p>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('Order of the HTML tags are incorrect.'));
    $this->assertText(t('HTML tag validation failed for 1 field(s).'));

    // Add multiple tags incorrectly.
    $edit = array(
      'title|deep_nesting[translation]' => '<p><p><p><p><strong><em><em>Translated text Italic and bold, many tags</em></strong></strong></strong></p>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('@tag expected 1, found 4.', array('@tag' => '<p>')));
    $this->assertText(t('@tag expected 1, found 2.', array('@tag' => '<em>')));
    $this->assertText(t('@tag expected 1, found 3.', array('@tag' => '</strong>')));
    $this->assertText(t('HTML tag validation failed for 1 field(s).'));

    // Check validation errors for two fields.
    $edit = array(
      'title|deep_nesting[translation]' => '<p><p><p><p><strong><em><em>Translated text Italic and bold, many tags</em></strong></strong></strong></p>',
      'body|deep_nesting[translation]' => '<p>Source body bold and Italic</strong></em></p>',
    );
    $this->drupalPostForm(NULL, $edit, t('Validate HTML tags'));
    $this->assertText(t('HTML tag validation failed for 2 field(s).'));

    // Tests that there is always a title.
    $text = '<p><em><strong>Source text bold and Italic</strong></em></p>';
    \Drupal::state()->set('tmgmt.test_source_data', [
      'title' => [
        [
          'value' => [
            '#text' => $text,
            '#label' => 'Title',
            '#translate' => TRUE,
            '#format' => 'filtered_html',
          ],
        ],
      ],
      'body' => [
        'deep_nesting' => [
          '#text' => $text,
          '#label' => 'Body',
          '#translate' => TRUE,
          '#format' => 'filtered_html',
        ],
      ],
    ]);
    $item5 = $job->addItem('test_source', 'test', 4);

    $this->drupalPostForm('admin/tmgmt/items/' . $item5->id(), [], t('Validate'));
    $this->assertText(t('The field is empty.'));

    // Test review just one data item.
    $edit = [
      'title|0|value[translation][value]' => $text . 'translated',
      'body|deep_nesting[translation][value]' => $text . 'no save',
    ];
    $this->drupalPostAjaxForm('admin/tmgmt/items/' . $item5->id(), $edit, 'reviewed-title|0|value');

    // Check if translation has been saved.
    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $this->assertFieldByName('title|0|value[translation][value]', $text . 'translated');
    $this->assertNoFieldByName('body|deep_nesting[translation][value]', $text . 'no save');

    // Tests field is less than max_length.
    \Drupal::state()->set('tmgmt.test_source_data', [
      'title' => [
        [
          'value' => [
            '#text' => $text,
            '#label' => 'Title',
            '#translate' => TRUE,
            '#max_length' => 10,
          ],
        ],
      ],
      'body' => [
        'deep_nesting' => [
          '#text' => $text,
          '#label' => 'Body',
          '#translate' => TRUE,
          '#max_length' => 20,
        ],
      ],
    ]);
    $item5 = $job->addItem('test_source', 'test', 4);

    $this->drupalPostForm('admin/tmgmt/items/' . $item5->id(), [
      'title|0|value[translation]' => $text,
      'body|deep_nesting[translation]' => $text,
    ], t('Save'));
    $this->assertText(t('The field has @size characters while the limit is @limit.', [
      '@size' => strlen($text),
      '@limit' => 10,
    ]));
    $this->assertText(t('The field has @size characters while the limit is @limit.', [
      '@size' => strlen($text),
      '@limit' => 20,
    ]));

    // Test if the validation is properly done.
    $this->drupalPostAjaxForm(NULL, [], 'reviewed-body|deep_nesting');
    $this->assertUniqueText(t('The field has @size characters while the limit is @limit.', [
      '@size' => strlen($text),
      '@limit' => 10,
    ]));

    // Test for the text with format set.
    \Drupal::state()->set('tmgmt.test_source_data', array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => 'Text for job item',
          '#label' => 'Label',
          '#format' => 'filtered_html',
        ),
      ),
    ));
    $item5 = $job->addItem('test_source', 'test', 5);

    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());
    $this->assertText('The translation of test_source:test:1 to German is finished and can now be reviewed.');
    $this->clickLink(t('reviewed'));
    $this->assertText('Needs review');
    $this->assertText('Job item test_source:test:1');

    $edit = array(
      'target_language' => 'de',
      'settings[action]' => 'submit',
    );
    $this->drupalPostForm('admin/tmgmt/jobs/' . $job->id(), $edit, t('Submit to provider'));

    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $xpath = $this->xpath('//*[@id="edit-dummydeep-nesting-translation-format-guidelines"]/div')[0];
    $this->assertEqual($xpath[0]->h4[0], t('Filtered HTML'));
    $rows5 = $this->xpath('//textarea[@name="dummy|deep_nesting[source][value]"]');
    $this->assertEqual((string) $rows5[0]['rows'], 3);

    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertNoText('has been saved successfully.');
    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $this->assertText('In progress');
    $edit = array(
      'dummy|deep_nesting[translation][value]' => 'Translated text for job item',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText('The translation for ' . trim($item5->label()) . ' has been saved successfully.');
    $this->drupalGet('admin/tmgmt/items/' . $item5->id());
    $this->assertText('Translated text for job item');
    $this->drupalPostForm(NULL, $edit, t('Save as completed'));
    $this->assertEqual(\Drupal::state()->get('tmgmt_test_saved_translation_' . $item5->getItemType() . '_' . $item5->getItemId())['dummy']['deep_nesting']['#translation']['#text'], 'Translated text for job item');

    // Test if the icons are displayed.
    $this->assertRaw('icons/73b355/check.svg" title="Accepted"');
    $this->assertRaw('icons/ready.svg" title="Needs review"');
    $this->loginAsAdmin();

    // Create two translators.
    $translator1 = $this->createTranslator();
    $translator2 = $this->createTranslator();
    $this->drupalGet('admin/tmgmt/jobs');

    // Assert that translators are in dropdown list.
    $this->assertOption('edit-translator', $translator1->id());
    $this->assertOption('edit-translator', $translator2->id());

    // Assign each job to a translator.
    $job1 = $this->createJob();
    $this->drupalGet('admin/tmgmt/jobs');
    $label = trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[0]);

    $job2 = $this->createJob();
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertTrue($label, trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[0]));
    $job1->set('translator', $translator1->id())->save();
    $job2->set('translator', $translator2->id())->save();

    // Filter jobs by translator and assert values.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('translator' => $translator1->id())));
    $label = trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[4]);
    $this->assertEqual($label, $translator1->label(), 'Found provider label in table');
    $this->assertNotEqual($label, $translator2->label(), "Providers filtered in table");
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('translator' => $translator2->id())));
    $label = trim((string) $this->xpath('//table[@class="views-table views-view-table cols-10"]/tbody/tr')[0]->td[4]);
    $this->assertEqual($label, $translator2->label(), 'Found provider label in table');
    $this->assertNotEqual($label, $translator1->label(), "Providers filtered in table");

    $edit = array(
      'dummy|deep_nesting[translation]' => '',
    );
    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->drupalPostForm(NULL, $edit, t('Validate'));
    $this->assertText(t('The field is empty.'));

    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertNoText(t('The field is empty.'));

    $this->drupalGet('admin/tmgmt/items/' . $item->id());
    $this->drupalPostForm(NULL, [], t('Save as completed'));
    $this->assertText(t('The field is empty.'));

    // Test that continuous jobs are not shown in the job overview.
    $this->container->get('module_installer')->install(['tmgmt_file'], TRUE);
    $non_continuous_translator = Translator::create([
      'name' => strtolower($this->randomMachineName()),
      'label' => $this->randomMachineName(),
      'plugin' => 'file',
      'remote_languages_mappings' => [],
      'settings' => [],
    ]);
    $non_continuous_translator->save();

    $continuous_job = $this->createJob('en', 'de', 0, [
      'label' => 'Continuous job',
      'job_type' => 'continuous',
    ]);

    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertNoText($continuous_job->label(), 'Continuous job is not displayed on job overview page.');

    // Test that continuous jobs are shown in the continuous job overview.
    $this->drupalGet('admin/tmgmt/continuous_jobs');
    $this->assertText($continuous_job->label(), 'Continuous job is displayed on continuous job overview page.');

    // Test that normal jobs are not shown in the continuous job overview.
    $this->assertNoText($job1->label(), 'Normal job is not displayed on continuous job overview page.');

    // Test that there are source items checkboxes on a continuous job form.
    $this->clickLink('Manage');
    $this->assertText($continuous_job->label());
    $this->assertNoFieldChecked('edit-continuous-settings-content-node-enabled', 'There is content checkbox and it is not checked yet.');
    $this->assertNoFieldChecked('edit-continuous-settings-content-node-bundles-article', 'There is article checkbox and it is not checked yet.');
    $this->assertNoFieldChecked('edit-continuous-settings-content-node-bundles-page', 'There is page checkbox and it is not checked yet.');

    // Enable Article source item for continuous job.
    $edit_continuous_job = [
      'continuous_settings[content][node][enabled]' => TRUE,
      'continuous_settings[content][node][bundles][article]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit_continuous_job, t('Save job'));

    // Test that continuous settings configuration is saved correctly.
    $updated_continuous_job = Job::load($continuous_job->id());
    $job_continuous_settings = $updated_continuous_job->getContinuousSettings();
    $this->assertEqual($job_continuous_settings['content']['node']['enabled'], 1, 'Continuous settings configuration for node is saved correctly.');
    $this->assertEqual($job_continuous_settings['content']['node']['bundles']['article'], 1, 'Continuous settings configuration for article is saved correctly.');
    $this->assertEqual($job_continuous_settings['content']['node']['bundles']['page'], 0, 'Continuous settings configuration for page is saved correctly.');

    // Test that continuous settings checkboxes are checked correctly.
    $this->clickLink('Manage');
    $this->assertText($continuous_job->label());
    $this->assertFieldChecked('edit-continuous-settings-content-node-enabled', 'Content checkbox is now checked.');
    $this->assertFieldChecked('edit-continuous-settings-content-node-bundles-article', 'Article checkbox now checked.');
    $this->assertNoFieldChecked('edit-continuous-settings-content-node-bundles-page', 'Page checkbox is not checked.');

    // Create continuous job through the form.
    $this->drupalGet('admin/tmgmt/continuous_jobs/continuous_add');
    $this->assertNoText($non_continuous_translator->label());
    $continuous_job_label = strtolower($this->randomMachineName());
    $edit_job = [
      'label[0][value]' => $continuous_job_label,
      'target_language' => 'de',
      'translator' => $this->default_translator->id(),
    ];
    $this->drupalPostForm(NULL, $edit_job, t('Save job'));
    $this->assertText($continuous_job_label, 'Continuous job was created.');

    // Test that previous created job is continuous job.
    $this->drupalGet('admin/tmgmt/continuous_jobs');
    $this->assertText($continuous_job_label, 'Created continuous job is displayed on continuous job overview page.');

    // Test that continuous job overview page does not have Submit link.
    $this->assertNoLink('Submit', 'There is no Submit link on continuous job overview.');

    // Test that all unnecessary fields and buttons do not exist on continuous
    // job edit form.
    $this->clickLink('Manage', 1);
    $this->assertNoRaw('<label for="edit-translator">Provider</label>', 'There is no Provider info field on continuous job edit form.');
    $this->assertNoRaw('<label for="edit-word-count">Total word count</label>', 'There is no Total word count info field on continuous job edit form.');
    $this->assertNoRaw('<label for="edit-tags-count">Total HTML tags count</label>', 'There is no Total HTML tags count info field on continuous job edit form.');
    $this->assertNoRaw('<label for="edit-created">Created</label>', 'There is no Created info field on continuous job edit form.');
    $this->assertNoRaw('id="edit-job-items-wrapper"', 'There is no Job items field on continuous job edit form.');
    $this->assertNoRaw('<div class="tmgmt-color-legend clearfix">', 'There is no Item state legend on continuous job edit form.');
    $this->assertNoRaw('id="edit-messages"', 'There is no Translation Job Messages field on continuous job edit form.');
    $this->assertNoFieldById('edit-abort-job', NULL, 'There is no Abort job button.');
    $this->assertNoFieldById('edit-submit', NULL, 'There is no Submit button.');
    $this->assertNoFieldById('edit-resubmit-job', NULL, 'There is Resubmit job button.');

    // Test that normal job item are shown in job items overview.
    $this->drupalGet('admin/tmgmt/job_items');
    $this->assertNoText($job1->label(), 'Normal job item is displayed on job items overview.');

    // Test that the legend is being displayed.
    $this->assertRaw('class="tmgmt-color-legend clearfix"');

    // Test that progress bar is being displayed.
    $this->assertRaw('class="tmgmt-progress-pending" style="width: 50%"');
    $this->assertRaw('class="tmgmt-progress-translated" style="width: 100%"');
  }

  /**
   * Tests the UI of suggestions.
   */
  public function testSuggestions() {
    // Prepare a job and a node for testing.
    $job = $this->createJob();
    $job->addItem('test_source', 'test', 1);
    $job->addItem('test_source', 'test', 7);

    // Go to checkout form.
    $redirects = tmgmt_job_checkout_multiple(array($job));
    $this->drupalGet(reset($redirects));

    $this->assertRaw('20');

    // Load all suggestions.
    $commands = $this->drupalPostAjaxForm(NULL, array(), array('op' => t('Load suggestions')));
    $this->assertEqual(count($commands), 5, 'Found 5 commands in AJAX-Request.');

    // Check each command for success.
    foreach ($commands as $command) {
      // Ignore irrelevant commands.
      if ($command['command'] == 'settings' || $command['command'] == 'update_build_id' || $command['data'] == "\n") {
      }
      // Other commands must be from type "insert".
      elseif ($command['command'] == 'insert') {
        // This should be the tableselect javascript file for the header.
        if (($command['method'] == 'append') && ($command['selector'] == 'body')) {
          $this->assertTrue(substr_count($command['data'], 'misc/tableselect.js'), 'Javascript for Tableselect found.');
        }
        // Check for the main content, the tableselect with the suggestions.
        elseif (($command['method'] == NULL) && ($command['selector'] == NULL)) {
          $this->assertTrue(substr_count($command['data'], '</th>') == 5, 'Found five table header.');
          $this->assertTrue(substr_count($command['data'], '</tr>') == 3, 'Found two suggestion and one table header.');
          $this->assertTrue(substr_count($command['data'], '<td>11</td>') == 2, 'Found 10 words to translate per suggestion.');
          $this->assertTrue(substr_count($command['data'], 'value="Add suggestions"'), 'Found add button.');
        }
        // Nothing to prepend...
        elseif (($command['method'] == 'prepend') && ($command['selector'] == NULL)) {
          $this->assertTrue(empty($command['data']), 'No content will be prepended.');
        }
        else {
          $this->fail('Unknown method/selector combination.');
        }
      }
      else {
        $this->fail('Unknown command.');
      }
    }

    $this->assertText('test_source:test_suggestion:1');
    $this->assertText('test_source:test_suggestion:7');
    $this->assertText('Test suggestion for test source 1');
    $this->assertText('Test suggestion for test source 7');

    // Add the second suggestion.
    $edit = array('suggestions_table[2]' => TRUE);
    $this->drupalPostForm(NULL, $edit, t('Add suggestions'));

    // Total word count should now include the added job.
    $this->assertRaw('31');
    // The suggestion for 7 was added, so there should now be a suggestion
    // or the suggestion instead.
    $this->assertNoText('Test suggestion for test source 7');
    $this->assertText('test_source:test_suggestion_suggestion:7');

  }

  /**
   * Test the process of aborting and resubmitting the job.
   */
  function testAbortJob() {
    $job = $this->createJob();
    $job->addItem('test_source', 'test', 1);
    $job->addItem('test_source', 'test', 2);
    $job->addItem('test_source', 'test', 3);

    $edit = array(
      'target_language' => 'es',
      'settings[action]' => 'translate',
    );
    $this->drupalPostForm('admin/tmgmt/jobs/' . $job->id(), $edit, t('Submit to provider'));

    // Abort job.
    $this->drupalPostForm('admin/tmgmt/jobs/' . $job->id(), array(), t('Abort job'));
    $this->drupalPostForm(NULL, array(), t('Confirm'));
    // Reload job and check its state.
    \Drupal::entityManager()->getStorage('tmgmt_job')->resetCache();
    $job = Job::load($job->id());
    $this->assertTrue($job->isAborted());
    foreach ($job->getItems() as $item) {
      $this->assertTrue($item->isAborted());
    }

    // Resubmit the job.
    $this->drupalPostForm('admin/tmgmt/jobs/' . $job->id(), array(), t('Resubmit'));
    $this->drupalPostForm(NULL, array(), t('Confirm'));
    // Test for the log message.
    $this->assertRaw(t('This job is a duplicate of the previously aborted job <a href=":url">#@id</a>',
      array(':url' => $job->url(), '@id' => $job->id())));

    // Load the resubmitted job and check for its status and values.
    $url_parts = explode('/', $this->getUrl());
    $resubmitted_job = Job::load(array_pop($url_parts));

    $this->assertTrue($resubmitted_job->isUnprocessed());
    $this->assertEqual($job->getTranslator()->id(), $resubmitted_job->getTranslator()->id());
    $this->assertEqual($job->getSourceLangcode(), $resubmitted_job->getSourceLangcode());
    $this->assertEqual($job->getTargetLangcode(), $resubmitted_job->getTargetLangcode());
    $this->assertEqual($job->get('settings')->getValue(), $resubmitted_job->get('settings')->getValue());

    // Test if job items were duplicated correctly.
    foreach ($job->getItems() as $item) {
      // We match job items based on "id #" string. This is not that straight
      // forward, but it works as the test source text is generated as follows:
      // Text for job item with type #type and id #id.
      $_items = $resubmitted_job->getItems(array('data' => array('value' => 'id ' . $item->getItemId(), 'operator' => 'CONTAINS')));
      $_item = reset($_items);
      $this->assertNotEqual($_item->getJobId(), $item->getJobId());
      $this->assertEqual($_item->getPlugin(), $item->getPlugin());
      $this->assertEqual($_item->getItemId(), $item->getItemId());
      $this->assertEqual($_item->getItemType(), $item->getItemType());
      // Make sure counts have been recalculated.
      $this->assertTrue($_item->getWordCount() > 0);
      $this->assertTrue($_item->getCountPending() > 0);
      $this->assertEqual($_item->getCountTranslated(), 0);
      $this->assertEqual($_item->getCountAccepted(), 0);
      $this->assertEqual($_item->getCountReviewed(), 0);
    }

    $this->drupalLogin($this->admin_user);
    // Navigate back to the aborted job and check for the log message.
    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());

    // Assert that the progress is N/A since the job was aborted.
    $element = (array) $this->xpath('//div[@class="view-content"]/table[@class="views-table views-view-table cols-8"]/tbody//tr[1]')[0];
    $this->assertEqual(trim((string) $element['td'][3]), t('N/A'));
    $this->assertRaw(t('Job has been duplicated as a new job <a href=":url">#@id</a>.',
      array(':url' => $resubmitted_job->url(), '@id' => $resubmitted_job->id())));
    $this->drupalPostForm(NULL, array(), t('Delete'));
    $this->drupalPostForm(NULL, array(), t('Delete'));
    $this->assertText('The translation job From English to Spanish has been deleted.');
    $this->drupalGet('admin/tmgmt/jobs/2/delete');
    $this->drupalPostForm(NULL, array(), t('Delete'));
    $this->drupalGet('admin/tmgmt/jobs/');
    $this->assertText('No jobs available.');

    // Create a translator.
    $translator = $this->createTranslator();

    // Create a job and attach to the translator.
    $job = $this->createJob();
    $job->translator = $translator;
    $job->save();
    $job->setState(Job::STATE_ACTIVE);

    // Add item to the job.
    $job->addItem('test_source', 'test', 1);
    $this->drupalGet('admin/tmgmt/jobs');

    // Try to abort the job and save.
    $this->clickLink(t('Manage'));
    $this->drupalPostForm(NULL, [], t('Abort job'));
    $this->drupalPostForm(NULL, [], t('Confirm'));

    // Go back to the job page.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => '6',
    )));

    // Check that job is aborted now.
    $this->assertRaw('title="Aborted"');
  }

  /**
   * Test the cart functionality.
   */
  function testCart() {

    $this->addLanguage('fr');
    $job_items = array();
    // Create a few job items and add them to the cart.
    for ($i = 1; $i < 6; $i++) {
      $job_item = tmgmt_job_item_create('test_source', 'test', $i);
      $job_item->save();
      $job_items[$i] = $job_item;
    }

    $this->loginAsTranslator();
    foreach ($job_items as $job_item) {
      $this->drupalGet('tmgmt-add-to-cart/' . $job_item->id());
    }

    // Check if the items are displayed in the cart.
    $this->drupalGet('admin/tmgmt/cart');
    foreach ($job_items as $job_item) {
      $this->assertText($job_item->label());
    }

    // Test the remove items from cart functionality.
    $this->drupalPostForm(NULL, [
      'items[1]' => TRUE,
      'items[2]' => FALSE,
      'items[3]' => FALSE,
      'items[4]' => TRUE,
      'items[5]' => FALSE,
    ], t('Remove selected'));
    $this->assertText($job_items[2]->label());
    $this->assertText($job_items[3]->label());
    $this->assertText($job_items[5]->label());
    $this->assertNoText($job_items[1]->label());
    $this->assertNoText($job_items[4]->label());
    $this->assertText(t('Job items were removed from the cart.'));

    // Test that removed job items from cart were deleted as well.
    $existing_items = JobItem::loadMultiple();
    $this->assertTrue(!isset($existing_items[$job_items[1]->id()]));
    $this->assertTrue(!isset($existing_items[$job_items[4]->id()]));


    $this->drupalPostForm(NULL, array(), t('Empty cart'));
    $this->assertNoText($job_items[2]->label());
    $this->assertNoText($job_items[3]->label());
    $this->assertNoText($job_items[5]->label());
    $this->assertText(t('All job items were removed from the cart.'));

    // No remaining job items.
    $existing_items = JobItem::loadMultiple();
    $this->assertTrue(empty($existing_items));

    $language_sequence = array('en', 'en', 'fr', 'fr', 'de', 'de');
    for ($i = 1; $i < 7; $i++) {
      $job_item = tmgmt_job_item_create('test_source', 'test', $i);
      $job_item->save();
      $job_items[$i] = $job_item;
      $languages[$job_items[$i]->id()] = $language_sequence[$i - 1];
    }
    \Drupal::state()->set('tmgmt.test_source_languages', $languages);
    foreach ($job_items as $job_item) {
      $this->drupalGet('tmgmt-add-to-cart/' . $job_item->id());
    }

    $this->drupalPostForm('admin/tmgmt/cart', array(
      'items[' . $job_items[1]->id() . ']' => TRUE,
      'items[' . $job_items[2]->id() . ']' => TRUE,
      'items[' . $job_items[3]->id() . ']' => TRUE,
      'items[' . $job_items[4]->id() . ']' => TRUE,
      'items[' . $job_items[5]->id() . ']' => TRUE,
      'items[' . $job_items[6]->id() . ']' => FALSE,
      'target_language[]' => array('en', 'de'),
    ), t('Request translation'));

    $this->assertText(t('@count jobs need to be checked out.', array('@count' => 4)));

    // We should have four jobs with following language combinations:
    // [fr, fr] => [en]
    // [de] => [en]
    // [en, en] => [de]
    // [fr, fr] => [de]

    $jobs = entity_load_multiple_by_properties('tmgmt_job', array('source_language' => 'fr', 'target_language' => 'en'));
    $job = reset($jobs);
    $this->assertEqual(count($job->getItems()), 2);

    $jobs = entity_load_multiple_by_properties('tmgmt_job', array('source_language' => 'de', 'target_language' => 'en'));
    $job = reset($jobs);
    $this->assertEqual(count($job->getItems()), 1);

    $jobs = entity_load_multiple_by_properties('tmgmt_job', array('source_language' => 'en', 'target_language' => 'de'));
    $job = reset($jobs);
    $this->assertEqual(count($job->getItems()), 2);

    $jobs = entity_load_multiple_by_properties('tmgmt_job', array('source_language' => 'fr', 'target_language' => 'de'));
    $job = reset($jobs);
    $this->assertEqual(count($job->getItems()), 2);

    $this->drupalGet('admin/tmgmt/cart');
    // Both fr and one de items must be gone.
    $this->assertNoText($job_items[1]->label());
    $this->assertNoText($job_items[2]->label());
    $this->assertNoText($job_items[3]->label());
    $this->assertNoText($job_items[4]->label());
    $this->assertNoText($job_items[5]->label());
    // One de item is in the cart as it was not selected for checkout.
    $this->assertText($job_items[6]->label());

    // Check to see if no items are selected and the error message pops up.
    $this->drupalPostForm('admin/tmgmt/cart', ['items[' . $job_items[6]->id() . ']' => FALSE], t('Request translation'));
    $this->assertUniqueText(t("You didn't select any source items."));
  }

  /**
   * Test titles of various TMGMT pages.
   *
   * @todo Miro wants to split this test to specific tests (check)
   */
  function testPageTitles() {
    $this->loginAsAdmin();
    $translator = $this->createTranslator();
    $job = $this->createJob();
    $job->translator = $translator;
    $job->settings = array();
    $job->save();
    $item = $job->addItem('test_source', 'test', 1);

    // Tmgtm settings.
    $this->drupalGet('/admin/tmgmt/settings');
    $this->assertTitle(t('Settings | Drupal'));
    // Manage translators.
    $this->drupalGet('/admin/tmgmt/translators');
    $this->assertTitle(t('Providers | Drupal'));
    // Add Translator.
    $this->drupalGet('/admin/tmgmt/translators/add');
    $this->assertTitle(t('Add Provider | Drupal'));
    // Delete Translators.
    $this->drupalGet('/admin/tmgmt/translators/manage/' . $translator->id() . '/delete');
    $this->assertTitle(t('Are you sure you want to delete the provider @label? | Drupal', ['@label' => $translator->label()]));
    // Edit Translators.
    $this->drupalGet('/admin/tmgmt/translators/manage/' . $translator->id());
    $this->assertTitle(t('Edit provider | Drupal'));
    // Delete Job.
    $this->drupalGet('/admin/tmgmt/jobs/' . $job->id() . '/delete');
    $this->assertTitle(t('Are you sure you want to delete the translation job @label? | Drupal', ['@label' => $job->label()]));
    // Resubmit Job.
    $this->drupalGet('/admin/tmgmt/jobs/' . $job->id() . '/resubmit');
    $this->assertTitle(t('Resubmit as a new job? | Drupal'));
    // Abort Job.
    $this->drupalGet('/admin/tmgmt/jobs/' . $job->id() . '/abort');
    $this->assertTitle(t('Abort this job? | Drupal'));
    // Edit Job Item.
    $this->drupalGet('/admin/tmgmt/items/' . $job->id());
    $this->assertTitle(t('Job item @label | Drupal', ['@label' => $item->label()]));
    // Assert the breadcrumb.
    $this->assertLink(t('Home'));
    $this->assertLink(t('Administration'));
    $this->assertLink(t('Job overview'));
    $this->assertLink($job->label());
    // Translation Sources.
    $this->drupalGet('admin');
    $this->clickLink(t('Translation'));
    $this->assertTitle(t('Translation | Drupal'));
    $this->clickLink(t('Cart'));
    $this->assertTitle(t('Cart | Drupal'));
    $this->clickLink(t('Jobs'));
    $this->assertTitle(t('Job overview | Drupal'));
    $this->clickLink(t('Sources'));
    $this->assertTitle(t('Content overview (Content Entity) | Drupal'));
  }

  /**
   * Test the deletion of job item.
   *
   * @todo This will in future check that delete is not allowed.
   * @todo There will be some overlap with Aborting items & testAbortJob.
   */
  function testJobItemDelete() {
    $this->loginAsAdmin();

    // Create a translator.
    $translator = $this->createTranslator();
    // Create a job and attach to the translator.
    $job = $this->createJob();
    $job->translator = $translator;
    $job->settings = array();
    $job->save();
    $job->setState(Job::STATE_ACTIVE);

    // Add item to the job.
    $item = $job->addItem('test_source', 'test', 1);

    $this->drupalGet('admin/tmgmt/jobs/' . $job->id());

    // Check for delete link.
    $this->assertLink('Delete');

    $this->clickLink('Delete');
    $this->assertText(t('Are you sure you want to delete the translation job item @label?', ['@label' => $item->getSourceLabel()]));

    // Check if cancel button is present or not.
    $this->assertLink('Cancel');

    // Delete the job item.
    $this->drupalPostForm(NULL, [], t('Delete'));
    $this->assertText(t('The translation job item @label has been deleted', ['@label' => $item->getSourceLabel()]));
  }

  /**
   * Test the Revisions of a job item.
   *
   * @todo Will be extended with the diff support.
   * @todo There will be another test that checks for changes and merges with diffs.
   */
  function testItemRevision() {
    $this->loginAsAdmin();

    // Create a translator.
    $translator = $this->createTranslator();
    // Create a job and attach to the translator.
    $job = $this->createJob();
    $job->translator = $translator;
    $job->settings = array();
    $job->save();
    $job->setState(Job::STATE_ACTIVE);
    // Add item to the job.
    $item = $job->addItem('test_source', 'test', 1);
    $this->drupalGet('admin/tmgmt/jobs/');
    $this->clickLink(t('Manage'));
    $this->clickLink(t('View'));
    $edit = [
      'dummy|deep_nesting[translation]' => 'any_value',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->clickLink(t('Review'));
    $edit = [
      'dummy|deep_nesting[translation]' => 'any_different_value',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->clickLink(t('Review'));
    $this->drupalPostAjaxForm(NULL, [], 'revert-dummy|deep_nesting');
    $this->assertText('Translation for dummy reverted to the latest version.');
    $this->assertFieldByName('dummy|deep_nesting[translation]', 'any_value');
  }

  /**
   * Test the settings of TMGMT.
   *
   * @todo some settings have no test coverage in their effect.
   * @todo we will need to switch them in context of the other lifecycle tests.
   */
  public function testSettings() {
    $this->loginAsAdmin();

    $settings = \Drupal::config('tmgmt.settings');
    $this->assertTrue($settings->get('quick_checkout'));
    $this->assertEqual('_never', $settings->get('purge_finished'));
    $this->assertTrue($settings->get('word_count_exclude_tags'));
    $this->assertEqual(20, $settings->get('source_list_limit'));
    $this->assertTrue($settings->get('respect_text_format'));

    $this->drupalGet('admin/tmgmt/settings');
    $edit = [
      'tmgmt_quick_checkout' => FALSE,
      'tmgmt_purge_finished' => 0,
      'respect_text_format' => FALSE,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    $settings = \Drupal::config('tmgmt.settings');
    $this->assertFalse($settings->get('quick_checkout'));
    $this->assertEqual(0, $settings->get('purge_finished'));
    $this->assertFalse($settings->get('respect_text_format'));
  }

  /**
   * Tests of the job item review process.
   */
  public function testProgress() {
    // Test that there are no jobs at the beginning.
    $this->drupalGet('admin/tmgmt/jobs');
    $this->assertText('No jobs available.');

    // Create Jobs.
    $job1 = $this->createJob();
    $job1->save();
    $job1->setState(Job::STATE_UNPROCESSED);

    $job2 = $this->createJob();
    $job2->save();
    $job2->setState(Job::STATE_ACTIVE);

    $job3 = $this->createJob();
    $job3->save();
    $job3->setState(Job::STATE_REJECTED);

    $job4 = $this->createJob();
    $job4->save();
    $job4->setState(Job::STATE_ABORTED);

    $job5 = $this->createJob();
    $job5->save();
    $job5->setState(Job::STATE_FINISHED);

    // Test their icons.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array(
      'state' => 'All',
    )));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 5);
    $this->assertJobStateIcon(1, 'Unprocessed');
    $this->assertJobStateIcon(2, 'In progress');
    $this->assertJobStateIcon(3, 'Rejected');
    $this->assertJobStateIcon(4, 'Aborted');
    $this->assertJobStateIcon(5, 'Finished');

    // Test the row amount for each state selected.
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '1')));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 3);

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '2')));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 1);

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '3')));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 1);

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '4')));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 1);

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '6')));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 1);

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '7')));
    $this->assertEqual(count($this->xpath('//tbody/tr')), 1);

    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '2')));

    \Drupal::state()->set('tmgmt.test_source_data', array(
      'title' => array(
        'deep_nesting' => array(
          '#text' => '<p><em><strong>Six dummy HTML tags in the title.</strong></em></p>',
          '#label' => 'Title',
        ),
      ),
      'body' => array(
        'deep_nesting' => array(
          '#text' => '<p>Two dummy HTML tags in the body.</p>',
          '#label' => 'Body',
        )
      ),
    ));

    // Add 2 items to job1 and submit it to provider.
    $item1 = $job1->addItem('test_source', 'test', 1);
    $job1->addItem('test_source', 'test', 2);
    $redirects = tmgmt_job_checkout_multiple(array($job1));
    $this->drupalGet(reset($redirects));
    $edit = array(
      'target_language' => 'de',
      'settings[action]' => 'submit',
    );
    $this->drupalPostForm(NULL, $edit, t('Submit to provider'));

    // Translate body of one item.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->drupalPostForm(NULL, array('body|deep_nesting[translation]' => 'translation'), t('Save'));
    // Check job item state is still in progress.
    $this->assertJobItemStateIcon(1, 'In progress');
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '1')));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 3, 1, 0, 0);
    $this->assertJobStateIcon(1, 'In progress');

    // Translate title of one item.
    $this->drupalGet('admin/tmgmt/items/' . $item1->id());
    $this->drupalPostForm(NULL, array('title|deep_nesting[translation]' => 'translation'), t('Save'));
    // Check job item state changed to needs review.
    $this->assertJobItemStateIcon(1, 'Needs review');
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '1')));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 2, 2, 0, 0);
    $this->assertJobStateIcon(1, 'Needs review');

    // Review the translation one by one.
    $this->drupalPostAjaxForm('admin/tmgmt/items/' . $item1->id(), NULL, 'reviewed-body|deep_nesting');
    $this->drupalGet('admin/tmgmt/jobs/' . $job1->id());
    // Check the icon of the job item.
    $this->assertJobItemStateIcon(1, 'Needs review');
    $this->drupalPostAjaxForm('admin/tmgmt/items/' . $item1->id(), NULL, 'reviewed-title|deep_nesting');
    $this->drupalGet('admin/tmgmt/jobs/' . $job1->id());
    // Check the icon of the job item.
    $this->assertJobItemStateIcon(1, 'Needs review');
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '1')));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 2, 0, 2, 0);
    $this->assertJobStateIcon(1, 'Needs review');

    // Save one job item as completed.
    $this->drupalPostForm('admin/tmgmt/items/' . $item1->id(), NULL, t('Save as completed'));
    // Check job item state changed to accepted.
    $this->assertJobItemStateIcon(1, 'Accepted');
    $this->drupalGet('admin/tmgmt/jobs', array('query' => array('state' => '1')));
    // Check progress bar and icon.
    $this->assertJobProgress(1, 2, 0, 0, 2);
    $this->assertJobStateIcon(1, 'In progress');
  }

  /**
   * Asserts task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $state
   *   The expected state.
   *
   */
  private function assertJobStateIcon($row, $state) {
    $result = $this->xpath('/html/body/div/main/div/div/div/div/div[2]/table/tbody/tr[' . $row . ']/td[3]/img')[0];
    $this->assertEqual($result['title'], $state);
  }

  /**
   * Asserts task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $state
   *   The expected state.
   *
   */
  private function assertJobItemStateIcon($row, $state) {
    $result = $this->xpath('//*[@id="edit-job-items-wrapper"]/div/div/div/div/table/tbody/tr[' . $row . ']/td[3]/img')[0];
    $this->assertEqual($result['title'], $state);
  }


  /**
   * Asserts task item progress bar.
   *
   * @param int $row
   *   The row of the item you want to check.
   * @param int $pending
   *   The amount of pending items.
   * @param int $reviewed
   *   The amount of reviewed items.
   * @param int $translated
   *   The amount of translated items.
   * @param int $accepted
   *   The amount of accepted items.
   *
   */
  private function assertJobProgress($row, $pending, $translated, $reviewed, $accepted) {
    $result = $this->xpath('/html/body/div/main/div/div/div/div/div[2]/table/tbody/tr[' . $row . ']/td[6]')[0];
    $div_number = 0;
    if ($pending > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-pending');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-pending');
    }
    if ($translated > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-translated');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-translated');
    }
    if ($reviewed > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-reviewed');
      $div_number++;
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-reviewed');
    }
    if ($accepted > 0) {
      $this->assertEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-accepted');
    }
    else {
      $this->assertNotEqual($result->div->div[$div_number]['class'], 'tmgmt-progress-accepted');
    }
    $title = t('Pending: @pending, translated: @translated, reviewed: @reviewed, accepted: @accepted.', array(
      '@pending' => $pending,
      '@translated' => $translated,
      '@reviewed' => $reviewed,
      '@accepted' => $accepted,
    ));
    $this->assertEqual($result->div['title'], $title);
  }
}
