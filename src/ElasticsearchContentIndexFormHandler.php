<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex\ContentIndex;

/**
 * Elasticsearch content index form handler class.
 */
class ElasticsearchContentIndexFormHandler {

  /**
   * Adds edit and delete buttons to Elasticsearch index plugin view form.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function alterIndexViewForm(array &$form, FormStateInterface $form_state) {
    if ($plugin = $this->getIndexPlugin($form_state)) {
      if ($plugin instanceof ContentIndex) {
        $form['actions']['edit'] = [
          '#type' => 'submit',
          '#value' => t('Edit'),
          '#op' => 'edit',
          '#submit' => [[$this, 'edit']],
          '#weight' => 25,
        ];

        $form['actions']['delete'] = [
          '#type' => 'submit',
          '#value' => t('Delete'),
          '#op' => 'delete',
          '#button_type' => 'danger',
          '#submit' => [[$this, 'delete']],
          '#weight' => 35,
        ];
      }
    }
  }

  /**
   * Returns Elasticsearch index plugin from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexInterface|null
   *   The Elasticsearch index plugin instance.
   */
  protected function getIndexPlugin(FormStateInterface $form_state) {
    if (isset($form_state->getBuildInfo()['args'][0])) {
      // First argument on $form_state build is a list of index plugin\
      // instances.
      $plugin = reset($form_state->getBuildInfo()['args'][0]);

      return $plugin;
    }

    return NULL;
  }

  /**
   * Edit button submit handler.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function edit(array $form, FormStateInterface $form_state) {
    // Get Elasticsearch index plugin.
    $plugin = $this->getIndexPlugin($form_state);

    // Redirect to Elasticsearch content index entity edit form.
    $url = Url::fromRoute('entity.elasticsearch_content_index.edit_form', ['elasticsearch_content_index' => $plugin->getPluginId()]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Delete button submit handler.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function delete(array $form, FormStateInterface $form_state) {
    // Get Elasticsearch index plugin.
    $plugin = $this->getIndexPlugin($form_state);

    // Redirect to Elasticsearch content index entity delete form.
    $url = Url::fromRoute('entity.elasticsearch_content_index.delete_form', ['elasticsearch_content_index' => $plugin->getPluginId()]);
    $form_state->setRedirectUrl($url);
  }

}
