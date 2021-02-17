# Elasticsearch Helper Content

`elasticsearch_helper_content` is a module that provides versatile generic elasticsearch
indexing for typical content entities with [Elasticsearch Helper][elasticsearch_helper].

## Requirements

* Drupal 8 or Drupal 9
* [Elasticsearch Helper][elasticsearch_helper] module

## Recommended modules

`elasticsearch_helper_instant` is a module that provides instant search functionality
based on [Elasticsearch Helper Content][elasticsearch_helper_content] module.

## Installation

Elasticsearch Helper Content can be installed via the
[standard Drupal installation process](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

* Install and enable [Elasticsearch Helper][elasticsearch_helper] module.
* Install and enable [Elasticsearch Helper Content][elasticsearch_helper_content] module.

## Usage

### Generic

1. Install `Elasticsearch` search engine ([how-to][elasticsearch_download]).
2. Install [Elasticsearch Helper][elasticsearch_helper] module and configure it.
3. Install [Elasticsearch Helper Content][elasticsearch_helper_content] module
4. (Optional) Install [Elasticsearch Helper Instant][elasticsearch_helper_instant] module.
5. Create indices using `drush` commands as follows:
    ```
    drush elasticsearch-helper-setup
    drush elasticsearch-helper-reindex
    drush queue-run elasticsearch_helper_indexing
    ```
6. Configure the following display view modes of your relevant entities (a.ka.
   content types) to contain sensible data (or have the default view mode handle it):
   - `Search index` - used by default for search result output of an entity
   - `Search result highlighting input` - used by default for search result output of an entity

### Advanced

#### Decide whether an entity is indexed

You can decide whether an entity is indexed via custom hook implementation:

```
function HOOK_elasticsearch_helper_content_source_alter(&$source) {
  // Only index nodes of bundle article or event.
  if ($source instanceof \Drupal\node\Entity\Node) {
    if (!in_array($source->bundle(), ['article', 'event'])) {
      $source = FALSE;
    }
  }
}
```

#### Manually set a render theme

When indexing an entity, the entity's render output for viewmodes search_index and search_result are also stored in ES.
By default, the default frontend theme is used to do that. But you can specify a different theme in settings.php:

```
$settings['elasticsearch_helper_content'] = [
  'render_theme' => 'my_awesome_theme_name',
];
```

#### Skip default normalize

When indexing an entity, the provided ElasticsearchContentNormalizer provides some metadata across entity types as well as it's parent classContentEntityNormalizer's default normalization, which is quite verbose and might not be intended in some cases. To skip providing this default normalization, set the following in your settings.php:
```
$settings['elasticsearch_helper_content'] = [
  'skip_default_normalize' => TRUE, // or _any_ other value.
];
```

[elasticsearch_download]: https://www.elastic.co/downloads/elasticsearch
[elasticsearch_helper]: https://www.drupal.org/project/elasticsearch_helper
[elasticsearch_helper_content]: https://www.drupal.org/project/elasticsearch_helper_content
[elasticsearch_helper_instant]: https://www.drupal.org/project/elasticsearch_helper_instant
[elasticsearch_client]: https://github.com/elastic/elasticsearch-php
