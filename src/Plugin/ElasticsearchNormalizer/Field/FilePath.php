<?php

namespace Drupal\elasticsearch_helper_content\Plugin\ElasticsearchNormalizer\Field;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper_content\ElasticsearchFieldNormalizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The file path field normalizer plugin class.
 *
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
   * File URL generator instance.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * File path normalizer constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldItemValue(EntityInterface $entity, FieldItemInterface $item, array $context = []) {
    $path = NULL;

    if ($file = $item->entity) {
      $uri = $file->getFileUri();

      if (!empty($this->configuration['absolute_url'])) {
        $path = $this->fileUrlGenerator->generateAbsoluteString($uri);
      }
      else {
        $path = $this->fileUrlGenerator->generateString($uri);
      }
    }

    return $path;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldDefinition() {
    return FieldDefinition::create('keyword');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'absolute_url' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => t('Use absolute URL'),
      '#default_value' => $this->configuration['absolute_url'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['absolute_url'] = (bool) $form_state->getValue('absolute_url');
  }

}
