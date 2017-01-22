<?php

namespace Drupal\tmgmt\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\tmgmt\SourceManager;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the job item edit forms.
 *
 * @ingroup tmgmt_job
 */
class TmgmtFormBase extends ContentEntityForm {

  /**
   * Translator plugin manager.
   *
   * @var \Drupal\tmgmt\TranslatorManager
   */
  protected $translatorManager;

  /**
   * Source plugin manager.
   *
   * @var \Drupal\tmgmt\SourceManager
   */
  protected $sourceManager;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an EntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\tmgmt\TranslatorManager $translator_manager
   *   The translator plugin manager.
   * @param \Drupal\tmgmt\SourceManager $source_manager
   *   The translation source manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, TranslatorManager $translator_manager, SourceManager $source_manager, RendererInterface $renderer) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);
    $this->translatorManager = $translator_manager;
    $this->sourceManager = $source_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.tmgmt.translator'),
      $container->get('plugin.manager.tmgmt.source'),
      $container->get('renderer')
    );
  }

}
