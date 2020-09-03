<?php

namespace Drupal\elasticsearch_helper_content;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\elasticsearch_helper_content\Plugin\ElasticsearchIndex\ContentIndex;

/**
 * Class ElasticsearchContentIndexFormHandler
 */
class ElasticsearchContentIndexFormHandler {

  /**
   * Adds edit and delete buttons to Elasticsearch index plugin view form.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
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
   *
   * @return \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexInterface|null
   */
  protected function getIndexPlugin(FormStateInterface $form_state) {
    if (isset($form_state->getBuildInfo()['args'][0])) {
      return $form_state->getBuildInfo()['args'][0];
    }

    return NULL;
  }

  /**
   * Edit button submit handler.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
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
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function delete(array $form, FormStateInterface $form_state) {
    // Get Elasticsearch index plugin.
    $plugin = $this->getIndexPlugin($form_state);

    // Redirect to Elasticsearch content index entity delete form.
    $url = Url::fromRoute('entity.elasticsearch_content_index.delete_form', ['elasticsearch_content_index' => $plugin->getPluginId()]);
    $form_state->setRedirectUrl($url);
  }

}
