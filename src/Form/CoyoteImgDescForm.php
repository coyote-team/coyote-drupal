<?php

namespace Drupal\coyote_img_desc\Form;

require_once( __DIR__ . '/../../vendor/autoload.php');

use Coyote\HelperFunctions;
use Coyote\Model\OrganizationModel;
use Coyote\Request\GetMembershipsRequest;
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

    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');

    $suffix = '';

    if (isset($token) && isset($endpoint)) {
      $profile = HelperFunctions::getProfile($endpoint, $token);
      if (is_null($profile)) {
        $suffix = 'Unable to load a valid API profile.';
      } else {
        $suffix = "User: {$profile->getName()}";
      }
    }

    $form['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Coyote API token:'),
      '#default_value' => $config->get('api_token'),
      '#description' => $this->t('The API token associated with your account.'),
      '#field_suffix' => $suffix
    ];

    $form['api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Coyote API endpoint:'),
      '#default_value' => $config->get('api_endpoint'),
      '#description' => $this->t('For example: "https://live.coyote.pics/api/v1/"'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'coyote_img_desc.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $endpoint = $form_state->getValue('api_endpoint');
    $token = $form_state->getValue('api_token');
    $profile = HelperFunctions::getProfile($endpoint, $token);

    if (is_null($profile)) {
      $form_state->setErrorByName('api_token', $this->t('The combination of token and/or endpoint is invalid.'));
    }

    $config = $this->config('coyote_img_desc.settings');
    $organizations = HelperFunctions::getOrganizations($endpoint, $token);
    $options = array_reduce($organizations, function(array $carry, OrganizationModel $item): array {
      $carry[$item->getId()] = $item->getName();
      return $carry;
    }, []);

    $form['api_organization'] = [
      '#type' => 'select',
      '#title' => $this->t('Organization:'),
      '#default_value' => $config->get('api_organization'),
      '#options' => $options
    ];

    $form_state->setErrorByName('api_organization', $this->t('Select an organization.'));

    $ddm = \Drupal::service('devel.dumper');
    $ddm->debug([$profile, $endpoint, $token]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('coyote_img_desc.settings');
    $config->set('api_endpoint', $form_state->getValue('api_endpoint'));
    $config->set('api_token', $form_state->getValue('api_token'));
    $config->set('api_organization', $form_state->getValue('api_organization'));
    $config->save();
    parent::submitForm($form, $form_state);
  }
}