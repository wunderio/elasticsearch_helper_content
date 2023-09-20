<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * The date field normalizer plugin class.
 *
 * @ElasticsearchFieldNormalizer(
 *   id = "date",
 *   label = @Translation("Date"),
 *   field_types = {
 *     "datetime",
 *     "timestamp",
 *     "created",
 *     "changed"
 *   }
 * )
 */
class Date extends FieldNormalizerBase {

  use StringTranslationTrait;

  /**
   * Defines available date formats.
   *
   * Use non-translated labels for formats.
   *
   * @var array
   */
  protected $formats = [
    'epoch_second' => 'Seconds since epoch (timestamp)',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $value = NULL;

    if ($item instanceof DateTimeItemInterface) {
      /** @var \DateTime $date */
      if ($date = $item->date) {
        $value = $date->getTimestamp();
      }
    }
    else {
      $value = $item->value;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition() {
    $definition = FieldDefinition::create('date');
    // Add format.
    $definition->addOption('format', $this->configuration['format']);

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'format' => 'epoch_second',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['format'] = [
      '#type' => 'select',
      '#title' => t('Format'),
      '#options' => $this->getFormatOptions(),
      '#default_value' => $this->configuration['format'],
    ];

    return $form;
  }

  /**
   * Returns a list of format options.
   *
   * @return array
   *   A list of formats.
   */
  protected function getFormatOptions() {
    return array_map(function ($format) {
      return (string) $this->t($format);
    }, $this->formats);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['format'] = $form_state->getValue('format');
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary() {
    $summary = parent::configurationSummary();

    $summary[] = $this->t('@format', [
      '@format' => $this->formats[$this->configuration['format']],
    ]);

    return $summary;
  }

}
