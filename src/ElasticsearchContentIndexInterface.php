<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Elasticsearch content index interface.
 */
interface ElasticsearchContentIndexInterface extends ConfigEntityInterface {

  /**
   * Returns target entity type.
   *
   * @return string
   *   The target entity type ID.
   */
  public function getTargetEntityType();

  /**
   * Sets target entity type.
   *
   * @param string $entity_type
   *   The target entity type ID.
   */
  public function setTargetEntityType($entity_type);

  /**
   * Returns target bundle.
   *
   * @return string
   *   The bundle name.
   */
  public function getTargetBundle();

  /**
   * Sets target bundle.
   *
   * @param string $bundle
   *   The bundle name.
   */
  public function setTargetBundle($bundle);

  /**
   * Returns index name.
   *
   * @return string
   *   The index name.
   */
  public function getIndexName();

  /**
   * Returns TRUE if index supports multiple languages.
   *
   * @return bool
   *   TRUE if index is multilingual.
   */
  public function isMultilingual();

  /**
   * Returns flag which indicates if unpublished content should be indexed.
   *
   * @return int
   *   TRUE if unpublished content needs to be indexed.
   *
   * @see \Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex::INDEX_UNPUBLISHED
   * @see \Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex::INDEX_UNPUBLISHED_IGNORE
   * @see \Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex::INDEX_UNPUBLISHED_NA
   */
  public function indexUnpublishedContent();

  /**
   * Returns normalizer.
   *
   * @return string
   *   The entity normalizer plugin ID.
   */
  public function getNormalizer();

  /**
   * Sets normalizer.
   *
   * @param string $normalizer
   *   The entity normalizer plugin ID.
   */
  public function setNormalizer($normalizer);

  /**
   * Returns normalizer instance.
   *
   * @return \Drupal\elasticsearch_helper_content\ElasticsearchEntityNormalizerInterface
   *   The entity normalizer instance.
   */
  public function getNormalizerInstance();

  /**
   * Returns normalizer configuration.
   *
   * @return array
   *   The entity normalizer configuration.
   */
  public function getNormalizerConfiguration();

  /**
   * Sets normalizer configuration.
   *
   * @param array $configuration
   *   The entity normalizer configuration.
   */
  public function setNormalizerConfiguration(array $configuration = []);

}
