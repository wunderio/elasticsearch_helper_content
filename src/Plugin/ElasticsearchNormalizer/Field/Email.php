<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

/**
 * The email field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "email",
 *   label = @Translation("Email"),
 *   field_types = {
 *     "email"
 *   }
 * )
 */
class Email extends Keyword {

}
