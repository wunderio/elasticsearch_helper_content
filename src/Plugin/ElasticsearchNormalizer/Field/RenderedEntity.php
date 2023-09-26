<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityViewBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rendered entity field normalizer plugin class.
 *
 * This field normalizer is able to render any content entity.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "rendered_entity",
 *   label = @Translation("Rendered entity"),
 *   field_types = {
 *     "entity"
 *   },
 *   weight = 100
 * )
 */
class RenderedEntity extends RenderedContentBase {

  use TextHelper;

  /**
   * The view builder instance.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $entity_type_id = $instance->getTargetEntityType();
    $entity_type_manager = $container->get('entity_type.manager');

    // Set view builder.
    $view_builder = $entity_type_manager->getViewBuilder($entity_type_id);
    $instance->setViewBuilder($view_builder);

    return $instance;
  }

  /**
   * Sets the view build instance.
   *
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder
   *   The view builder instance.
   */
  public function setViewBuilder(EntityViewBuilderInterface $view_builder) {
    $this->viewBuilder = $view_builder;
  }

  /**
   * {@inheritdoc}
   */
  protected function doNormalize($entity, $field, array $context = []) {
    $langcode = $entity->language()->getId();
    $build = $this->viewBuilder->view($entity, $this->configuration['view_mode'], $langcode);
    $result = $this->renderer->renderPlain($build);

    if ($this->configuration['strip_tags']) {
      $result = $this->stripTags($result);
    }

    return $result;
  }

}
