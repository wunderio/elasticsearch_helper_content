<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;

/**
 * The text field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "text",
 *   label = @Translation("Text"),
 *   field_types = {
 *     "string",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "list_string"
 *   }
 * )
 */
class Text extends ElasticsearchFieldNormalizerBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    return $item->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    $definition = FieldDefinition::create('text');

    // Store raw value as keyword field.
    if ($this->configuration['store_raw']) {
      $definition->addMultiField('raw', FieldDefinition::create('keyword'));
    }

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'store_raw' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [
      'store_raw' => [
        '#type' => 'checkbox',
        '#title' => t('Store the raw value as a keyword in a multi-field.'),
        '#weight' => 50,
        '#default_value' => $this->configuration['store_raw'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['store_raw'] = (bool) $form_state->getValue('store_raw');
  }

}
