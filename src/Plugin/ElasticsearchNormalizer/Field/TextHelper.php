<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

/**
 * Text helper class.
 *
 * Contains methods which allow to pre-process the text.
 */
trait TextHelper {

  /**
   * Strips the tags from the text.
   *
   * @param string $string
   *   The text string.
   *
   * @return string
   *   The text without tags.
   */
  public function stripTags($string) {
    // Prepend each tag bracket with a space so that after the tag removal
    // the text strings do not compound. For example:
    // <p>A text string.</p><p>A text string.</p> would turn into
    // "A text string.A text string." without this trick.
    $result = str_replace('<', ' <', $string);

    return strip_tags($result);
  }

}
