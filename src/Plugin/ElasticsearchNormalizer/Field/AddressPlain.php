<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;

/**
 * The address field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "address_plain",
 *   label = @Translation("Address (plain text)"),
 *   field_types = {
 *     "address"
 *   }
 * )
 */
class AddressPlain extends Text {

  /**
   * The default address formatter.
   *
   * @var string
   */
  protected $formatter = 'address_plain';

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    // Render using the formatter.
    $build = $item->view(['type' => $this->formatter]);
    $result = \Drupal::service('renderer')->renderRoot($build);
    // Strip the tags.
    $result = trim(strip_tags($result));
    // Remove all extra whitespaces.
    $result = preg_replace('!\s+!', ' ', $result);

    return $result;
  }

}
