<?php

/**
 * @file
 * Contains Drupal\tmgmt_test\Plugin\tmgmt\Source\TestHtmlSource.
 */

namespace Drupal\tmgmt_test\Plugin\tmgmt\Source;

use Drupal\tmgmt\Entity\JobItem;

/**
 * Test source plugin implementation.
 *
 * @SourcePlugin(
 *   id = "test_html_source",
 *   label = @Translation("Test HTML source"),
 *   description = @Translation("HTML source for testing purposes.")
 * )
 */
class TestHtmlSource extends TestSource {

  /**
   * {@inheritdoc}
   */
  public function getData(JobItem $job_item) {
    return array(
      'dummy' => array(
        'deep_nesting' => array(
          '#text' => file_get_contents(drupal_get_path('module', 'tmgmt') . '/tests/testing_html/sample.html'),
          '#label' => 'Label for job item with type ' . $job_item->item_type . ' and id ' . $job_item->item_id . '.',
        ),
      ),
    );
  }
}
