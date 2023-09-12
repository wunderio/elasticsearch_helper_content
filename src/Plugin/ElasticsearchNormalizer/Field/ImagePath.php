<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

/**
 * The image path field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "image_path",
 *   label = @Translation("Image path"),
 *   field_types = {
 *     "image"
 *   },
 *   weight = -10
 * )
 */
class ImagePath extends FilePath {
}
