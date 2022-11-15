<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase;

/**
 * @ElasticsearchIndex(
 *   id = "content_index_user",
 *   label = @Translation("User Index"),
 *   indexName = "content-user",
 *   typeName = "user",
 *   entityType = "user"
 * )
 */
class UserIndex extends ElasticsearchIndexBase {

  use AlterableIndexTrait;

  /**
   * {@inheritdoc}
   */
  public function getMappingDefinition(array $context = []) {
    return MappingDefinition::create()
      ->addProperty('id', FieldDefinition::create('keyword'));
  }

}
