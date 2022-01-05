<?php

namespace Drupal\coyote_img_desc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CoyoteImgDescForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'coyote_img_desc_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('coyote_img_desc.settings');

    $form['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Coyote API token:'),
      '#default_value' => $config->get('api_token'),
      '#description' => $this->t('The API token associated with your account.'),
    ];

    $form['api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The endpoint for the Coyote API:'),
      '#default_value' => $config->get('api_endpoint'),
      '#description' => $this->t('For example, "https://live.coyote.pics/api/v1/"'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'coyote_img_desc.settings',
    ];
  }
}