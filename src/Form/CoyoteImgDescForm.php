<?php

namespace Drupal\coyote_img_desc\Form;

require_once( __DIR__ . '/../../vendor/autoload.php');

use Coyote\CoyoteApiClientHelperFunctions;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ProfileModel;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\coyote_img_desc\Constants;
use Drupal\coyote_img_desc\Helper\CoyoteMembershipHelper;
use Drupal\coyote_img_desc\Util;
use JetBrains\PhpStorm\ArrayShape;

class CoyoteImgDescForm extends ConfigFormBase {

  private static function isDefined(?string $var): bool
  {
    return !is_null($var) && strlen($var) > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'coyote_img_desc_config_form';
  }

  private function getProfileSuffix(?ProfileModel $profile): string
  {
    if (is_null($profile)) {
      return 'Unable to load a valid API profile.';
    }

    $highestRole = CoyoteMembershipHelper::getHighestMembershipRole($profile->getMemberships());

    if (is_null($highestRole)) {
      return 'This account has insufficient Coyote API permissions.';
    }

    return "Owner: {$profile->getName()} ({$highestRole})";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('coyote_img_desc.settings');

    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');

    $profile = null;
    $suffix = '';

    if (self::isDefined($token) && self::isDefined($endpoint)) {
      $profile = CoyoteApiClientHelperFunctions::getProfile($endpoint, $token);
      $suffix = self::getProfileSuffix($profile);
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

    $storedOrganizationId = $config->get('api_organization');

    if (!is_null($profile)) {
      $form['api_organization'] = self::getApiOrganizationIdFieldConfig($profile, $storedOrganizationId);
    }

    if (!is_null($profile) && !is_null($storedOrganizationId)) {
      $this->verifyResourceGroup($form, $endpoint, $token, $storedOrganizationId, $config->get('api_resource_group'));
    }

    return $form;
  }

  private function verifyResourceGroup(
    array &$form,
    string $endpoint,
    string $token,
    int $organizationId,
    ?int $resourceGroupId
  ) {
    $group = null;

    if (is_null($resourceGroupId)) {
      $group = CoyoteApiClientHelperFunctions::getResourceGroupByUri(
        $endpoint, $token, $organizationId, Util::getResourceGroupUri()
      );
    }

    if (is_null($resourceGroupId) && is_null($group)) {
      $group = CoyoteApiClientHelperFunctions::createResourceGroup(
        $endpoint,
        $token,
        $organizationId,
        Constants::RESOURCE_GROUP_NAME,
        Util::getResourceGroupUri()
      );
      if (is_null($group)) {
          $form['api_organization']['#field_suffix'] = $this->t("Resource group '".Constants::RESOURCE_GROUP_NAME."' could not be created");
          $config = $this->config('coyote_img_desc.settings');
          $config->set('api_resource_group', null);
          $config->save();
      }
    }

    if (!is_null($group)) {
      $resourceGroupId ??= $group->getId();
    }

    if (!is_null($resourceGroupId)) {
      $config = $this->config('coyote_img_desc.settings');
      $config->set('api_resource_group', (int) $resourceGroupId);
      $config->save();
      $form['api_organization']['#field_suffix'] = $this->t("Resource group {$resourceGroupId} &#xFE0F;");
    }

    // TODO track that no resource group is available
  }

  #[ArrayShape([
    '#type' => "string",
    '#title' => "\Drupal\Core\StringTranslation\TranslatableMarkup",
    '#name' => "string",
    '#options' => "array|mixed",
    '#default_value' => "int|null|string"
  ])] private function getApiOrganizationIdFieldConfig(ProfileModel $profile, ?int $currentId): array
  {
    $organizations = $profile->getOrganizations();
    $options = array_reduce($organizations, function(array $carry, OrganizationModel $item): array {
      $carry[$item->getId()] = $item->getName();
      return $carry;
    }, []);

    $value = is_null($currentId) ? null : (string) $currentId;

    if (!is_null($organizations)) {
      if (count($organizations) > 1) {
        $options = ['0' => $this->t('--- Select an organization ---')] + $options;
      } else {
        $value = array_keys($options)[0];
      }
    }

    return [
      '#type' => 'select',
      '#title' => $this->t('Organization:'),
      '#name' => 'api_organization',
      '#options' => $options,
      '#default_value' => $value
    ];
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
    $organizationId = $form_state->getValue('api_organization');

    $profile = CoyoteApiClientHelperFunctions::getProfile($endpoint, $token);

    if (is_null($profile)) {
      $form_state->setErrorByName('api_token', $this->t('The combination of token and/or endpoint is invalid.'));
      return;
    }

    $suffix = CoyoteMembershipHelper::getHighestMembershipRole($profile->getMemberships());

    if (is_null($suffix)) {
      $form_state->setErrorByName('api_token', $this->t('This token has insufficient Coyote API permissions.'));
    }

    $form['api_organization'] = self::getApiOrganizationIdFieldConfig($profile, $organizationId);

    if (is_null($organizationId)) {
      \Drupal::messenger()->addStatus("Please select an organization.");
    }
    else { 
        $config = $this->config('coyote_img_desc.settings');
        $storedToken = $config->get('api_token');
        $storedEndpoint = $config->get('api_endpoint');
        $storedOrganizationId = $config->get('api_organization');

        if ($endpoint != $storedEndpoint || $token != $storedToken || $organizationId != $storedOrganizationId) { 
            //reset ResourceGroup
            $this->verifyResourceGroup($form, $endpoint, $token, $organizationId, null);
        }
    }
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
