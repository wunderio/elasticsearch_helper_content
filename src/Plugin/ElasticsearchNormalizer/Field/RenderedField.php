<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

/**
 * The "rendered field" field normalizer plugin class.
 *
 * This field normalizer is able to render any entity field.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "rendered_field",
 *   label = @Translation("Rendered field"),
 *   field_types = {
 *     "list"
 *   },
 *   weight = 100
 * )
 */
class RenderedField extends RenderedContentBase {

  use TextHelper;

  /**
   * {@inheritdoc}
   */
  protected function doNormalize($entity, $field, array $context = []) {
    $result = $this->getEmptyFieldValue($entity, $field, $context);

    if ($field && !$field->isEmpty()) {
      $build = $field->view($this->configuration['view_mode']);
      $result = $this->renderer->renderPlain($build);

      if ($this->configuration['strip_tags']) {
        $result = $this->stripTags($result);
      }
    }

    return $result;
  }

}
