
# Elasticsearch Helper Content

The module provides tools to create Elasticsearch indices for content entities
in Drupal UI.

Features:
* Support for any content entity type.
* Multilingual support.
* Support for adding custom fields to the index.

## Requirements

* Drupal 9 or 10
* [Elasticsearch Helper][elasticsearch_helper] module
* [Elasticsearch Helper Index Management][elasticsearch_helper_index_management] module

## Installation

Elasticsearch Helper Content module can be installed via the
[standard Drupal installation process](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

1. Install `Elasticsearch` search engine ([how-to][elasticsearch_download]).
2. Install and enable [Elasticsearch Helper][elasticsearch_helper] module.
3. Install and enable [Elasticsearch Helper Index management][elasticsearch_helper_index_management]
   module.
4. Install and enable [Elasticsearch Helper Content][elasticsearch_helper_content]
   module.

## Usage

1. Go to the `/admin/config/search/elasticsearch_helper/index`.
2. Click on the `Add content index` button.
2. Fill the form with label, index name information, select the entity
   fields you want to have indexed, and save the form.
3. Click on the `Setup` button to create the index in Elasticsearch.

[elasticsearch_download]: https://www.elastic.co/downloads/elasticsearch
[elasticsearch_helper]: https://www.drupal.org/project/elasticsearch_helper
[elasticsearch_helper_index_management]: https://www.drupal.org/project/elasticsearch_helper_index_management
[elasticsearch_helper_content]: https://www.drupal.org/project/elasticsearch_helper_content
