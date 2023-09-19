<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;

/**
 * The path field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "path",
 *   label = @Translation("Path"),
 *   field_types = {
 *     "path"
 *   }
 * )
 */
class Path extends PathBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    if ($url = $this->getUrl($entity, $item, $context)) {
      return $url->toString();
    }

    return NULL;
  }

  /**
   * Returns Url object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The index-able entity.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item instance.
   * @param array $context
   *   The context array.
   *
   * @return \Drupal\Core\Url|null
   *   The Url instance or null.
   */
  protected function getUrl(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    try {
      /** @var \Drupal\Core\Url $url */
      $url = $entity->toUrl('canonical');

      if (!empty($this->configuration['absolute_url'])) {
        $url->setAbsolute();
      }

      return $url;
    }
    catch (\Exception $e) {
      watchdog_exception('elasticsearch_helper_content', $e);
    }

    return NULL;
  }

}
