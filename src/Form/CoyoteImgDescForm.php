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
use Drupal\coyote_img_desc\DB;
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
    return $this->t('coyote_img_desc_config_form');
  }

  private function getProfileSuffix(?ProfileModel $profile): string
  {
    if (is_null($profile)) {
      return $this->t('Unable to load a valid API profile.');
    }

    $highestRole = CoyoteMembershipHelper::getHighestMembershipRole($profile->getMemberships());

    if (is_null($highestRole)) {
      return $this->t('This account has insufficient Coyote API permissions.');
    }

    return $this->t("Owner: {$profile->getName()} ({$highestRole})");
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('coyote_img_desc.settings');

    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');
    $resource_group = $config->get('api_resource_group');
    $disable_coyote_filtering = $config->get('disable_coyote_filtering');

    $profile = null;
    $suffix = '';
    $log="";

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

    $form['coyote_warning'] = [
       '#type' => 'item',
       '#markup' => '<div class="messages-list messages messages--warning">'.$this->t('Changing the API token or the endpoint or the Organization will clear the local plugin data')."</div>",
       '#states' => array(
         'invisible' => array(
         ':input[name="api_token"]' => array('value' => $token),
         ':input[name="api_endpoint"]' => array('value' => $endpoint),
         'select[name="api_organization"]' => array('value' => $storedOrganizationId),
         ),
       ),
    ];

    $log .= "profile:".print_r($profile, true)." storedOrganizationId:".$storedOrganizationId." resource_group:".$resource_group;
    if (!is_null($profile) && !is_null($storedOrganizationId)) {
      $this->verifyResourceGroup($form, $endpoint, $token, $storedOrganizationId, $config->get('api_resource_group'));
    }
    $form['disable_coyote_filtering'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Coyote filtering during rendering'),
      '#default_value' => $config->get('disable_coyote_filtering'),
    ];

    $form['ignore_coyote_webhook_calls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore incoming Coyote webhook calls'),
      '#default_value' => $config->get('ignore_coyote_webhook_calls'),
    ];

    $form['coyote_process_unpublished_nodes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process unpublished nodes'),
      '#default_value' => $config->get('coyote_process_unpublished_nodes'),
    ];


//    \Drupal::logger('coyote')->notice($log);

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
    $log = "VerifyResourceGroup endpoint: ".$endpoint." token:".$token." organizationId:". $organizationId." ";

    if (is_null($resourceGroupId)) {
      $group = CoyoteApiClientHelperFunctions::getResourceGroupByUri(
        $endpoint, $token, $organizationId, Util::getResourceGroupUri()
      );
      $log .= " group:".print_r($group,true);
    }

    if (is_null($resourceGroupId) && is_null($group)) {
      $group = CoyoteApiClientHelperFunctions::createResourceGroup(
        $endpoint,
        $token,
        $organizationId,
        Constants::RESOURCE_GROUP_NAME,
        Util::getResourceGroupUri()
      );
      $log .=" Group create C:".Constants::RESOURCE_GROUP_NAME." Uri:". Util::getResourceGroupUri(). " Group:". print_r($group,true);
      if (is_null($group)) {
          $form['api_organization']['#field_suffix'] = $this->t("Resource group '@resourceGroup' could not be created", array('@resourceGroup' => Constants::RESOURCE_GROUP_NAME));
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
  //  \Drupal::logger('coyote')->notice($log);

  }
  private function getApiOrganizationIdFieldConfig(ProfileModel $profile, ?int $currentId): array
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
      \Drupal::messenger()->addStatus($this->t("Please select an organization."));
    }
    else { 
        $config = $this->config('coyote_img_desc.settings');
        $storedToken = $config->get('api_token');
        $storedEndpoint = $config->get('api_endpoint');
        $storedOrganizationId = $config->get('api_organization');

        if ($endpoint != $storedEndpoint || $token != $storedToken || $organizationId != $storedOrganizationId) { 
            //reset ResourceGroup
            $this->verifyResourceGroup($form, $endpoint, $token, $organizationId, null);
            DB::truncateResourceTable();
        }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('coyote_img_desc.settings');
    $config->set('api_endpoint', $form_state->getValue('api_endpoint'));
    $config->set('api_token', $form_state->getValue('api_token'));
    $config->set('api_organization', $form_state->getValue('api_organization'));
    $config->set('disable_coyote_filtering', $form_state->getValue('disable_coyote_filtering'));
    $config->set('ignore_coyote_webhook_calls', $form_state->getValue('ignore_coyote_webhook_calls'));
    $config->set('coyote_process_unpublished_nodes', $form_state->getValue('coyote_process_unpublished_nodes'));
    $config->save();
    drupal_flush_all_caches();
    parent::submitForm($form, $form_state);
  }
}
