<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Entity;

/**
 * Field type interface.
 *
 * Defines the custom field data types which map the entity/custom fields
 * to respective field normalizers.
 */
interface FieldTypeInterface {

  /**
   * Defines the data type for entities.
   */
  const ENTITY = 'entity';

  /**
   * Defines the data type for custom fields.
   */
  const CUSTOM = 'any';

}
