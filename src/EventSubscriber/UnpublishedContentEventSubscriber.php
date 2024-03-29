<?php

namespace Drupal\elasticsearch_helper_content\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\elasticsearch_helper\Event\ElasticsearchEvents;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperationEvent;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Drupal\elasticsearch_helper_content\Entity\ElasticsearchContentIndex;
use Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex\ContentIndex;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Unpublished content event subscriber.
 *
 * Prevents unpublished content to be indexed, and deletes the document
 * from indices when document gets unpublished.
 */
class UnpublishedContentEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ElasticsearchEvents::OPERATION][] = ['onOperation'];

    return $events;
  }

  /**
   * Skips indexing and removes existing document for unpublished content.
   *
   * @param \Drupal\elasticsearch_helper\Event\ElasticsearchOperationEvent $event
   *   The Elasticsearch operation event instance.
   */
  public function onOperation(ElasticsearchOperationEvent $event) {
    if ($event->getOperation() == ElasticsearchOperations::DOCUMENT_INDEX) {
      // Get entity.
      $entity = &$event->getObject();

      if ($entity instanceof EntityInterface) {
        // Get plugin.
        $plugin = $event->getPluginInstance();

        if ($plugin instanceof ContentIndex) {
          // Check if entity is index-able.
          $indexable = $this->isIndexable($entity, $plugin);

          if (!$indexable) {
            // Attempt to remove the document from the index.
            $plugin->deleteTranslation($entity);

            // Forbid operation to skip indexing.
            $event->forbidOperation();
          }
        }
      }
    }
  }

  /**
   * Returns TRUE if entity is publishing status aware.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source
   *   The index-able entity.
   *
   * @return bool
   *   Returns TRUE if the entity is published-status aware.
   */
  protected function isPublishAware($source) {
    return $source instanceof EntityPublishedInterface;
  }

  /**
   * Returns TRUE if translation of the entity should be added to the index.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The index-able entity.
   * @param \Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex\ContentIndex $plugin
   *   The Elasticsearch index plugin instance.
   *
   * @return bool
   *   Returns TRUE if entity is index-able.
   */
  protected function isIndexable(EntityInterface $entity, ContentIndex $plugin) {
    $index_unpublished = $plugin->getContentIndexEntity()->indexUnpublishedContent();

    // Return TRUE if entity type does not support publishing status or
    // unpublished content should be indexed.
    $unpublished_statuses = [
      ElasticsearchContentIndex::INDEX_UNPUBLISHED_NA,
      ElasticsearchContentIndex::INDEX_UNPUBLISHED,
    ];

    if (in_array($index_unpublished, $unpublished_statuses, TRUE)) {
      return TRUE;
    }

    if ($index_unpublished == ElasticsearchContentIndex::INDEX_UNPUBLISHED_IGNORE) {
      if ($this->isPublishAware($entity) && $entity->isPublished()) {
        return TRUE;
      }
    }

    // Stay on the safe side and do not index by default.
    return FALSE;
  }

}
