<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * @ElasticsearchFieldNormalizer(
 *   id = "file_path",
 *   label = @Translation("File path"),
 *   field_types = {
 *     "file"
 *   },
 *   weight = -10
 * )
 */
class FilePath extends ElasticsearchFieldNormalizerBase {

  /**
   * {@inheritDoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $path = NULL;

    if ($file = $item->entity) {
      $uri = $file->getFileUri();
      $path = parse_url(file_create_url($uri), PHP_URL_PATH);
    }

    return $path;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

}
