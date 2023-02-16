<?php

namespace Drupal\coyote_img_desc\Form;

require_once( __DIR__ . '/../../vendor/autoload.php');

use Coyote\CoyoteApiClientHelperFunctions;
use Coyote\Model\OrganizationModel;
use Coyote\Model\ProfileModel;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\coyote_img_desc\Constants;
use Drupal\coyote_img_desc\Helper\CoyoteMembershipHelper;
use Drupal\coyote_img_desc\Util;
use Drupal\coyote_img_desc\DB;
use Drupal\node\Entity\Node;
use Coyote\ContentHelper;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

class CoyoteImgBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */

  private ?ProfileModel $profile;
  private ?string $role;

  public function getFormId() {
    return 'coyote_img_batch_form';
  }

  private static function isDefined(?string $var): bool
  {
    return !is_null($var) && strlen($var) > 0;
  }

  private function getProfileRole(): ?string
  {
    if (is_null($this->profile)) {
      return null;
    }

    $highestRole = CoyoteMembershipHelper::getHighestMembershipRole($this->profile->getMemberships());

    if (is_null($highestRole)) {
      return null;
    }

    return $this->t("Owner: {$this->profile->getName()} ({$highestRole})");
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');
    $resourceGroup = $config->get('api_resource_group');
    $organizationId = $config->get('api_organization');
    if (self::isDefined($token) && self::isDefined($endpoint)) {
      $this->profile = CoyoteApiClientHelperFunctions::getProfile($endpoint, $token);
      $this->role = $this->getProfileRole();
    }
     
    $validConfig = $this->hasValidBatchProcessingConfig();

    $form['coyote_message'] = [
       '#type' => 'item',
        '#markup' => "<strong>".$this->t('Values used'). "</strong><br />".$this->t('Coyote API token:')." ".$token."<br />". $this->t('Coyote API endpoint:') ." ".$endpoint. "<br />" . $this->t('Organization ID:'). " ".$organizationId . "<br />". $this->t('Resource Group:'). " ".$resourceGroup."<br />".$this->t("Permissions:")." ".$this->role,
    ];

    if (!$validConfig) {
       $form['coyote_warning'] = [
          '#type' => 'item',
          '#markup' => "<strong>".  $this->t("Invalid data, correct in Settings")."</strong>",
       ];
          
    }

    $form['batch_processing'] = [
      '#type' => 'submit',
      '#disabled' => !$validConfig,
      '#value' => $this->t('Batch processing of all images?'),
    ];

    return $form;
  }

  private function hasValidBatchProcessingConfig(): bool {
    $config = $this->config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = $config->get('api_endpoint');
    $resourceGroup = $config->get('api_resource_group');
    $organizationId = $config->get('api_organization');
    if (!self::isDefined($token)) { 
         return false;
    }
    if (!self::isDefined($endpoint)) {
         return false;
    }
    if (!self::isDefined($organizationId)) {
         return false;
    }
    if (!self::isDefined($resourceGroup)) {
         return false;
    }
    if (is_null($this->profile)){
         return false;
    }
    if (is_null($this->role)) {
         return false;
    }
     return true;
  } 

  public function submitForm(array &$form, FormStateInterface $form_state): void {
     $entities = array_keys(\Drupal::entityTypeManager()->getDefinitions());
     $operations = [];

     foreach ($entities as $entity){  
        $ids = \Drupal::entityQuery($entity)->execute();
        foreach ($ids as $id){     
          $operations[] = [['\Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batchProcessingEntities'], [$id, $entity]];
        }
     }
     $batch = [
          'title' => $this->t('Batch processing All Images ...'),
          'operations' => $operations,
          'finished' => ['Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batchProcessingEntitiesFinished'],
     ];

     batch_set($batch);
  }
  
  public static function batchProcessingEntities($id, $entity, &$context) {
     $message = 'Proccessing ALL entities to coyote';
     $results = $context['results'];
   
     $config = \Drupal::config('coyote_img_desc.settings');

     $entity_type = $entity;
     $view_mode = 'default';
     $display = true;

     try {

        $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        $s = $storage->load($id);

        $build = $viewBuilder->view($s, $view_mode);

        $hostUri = $s->toUrl('canonical', ['absolute' => true])->toString();
    
        $vm = "";
        if (isset($build["#view_mode"])) { $vm .= $build["#view_mode"]; }
           \Drupal::logger('coyote')->notice("4".print_r($hostUri,true). " ".print_r(array_keys($build),true). " vm:".$vm);
          $output = render($build);
     }
     catch (UndefinedLinkTemplateException $e) {
        $display = false;
     }
     catch (InvalidPluginDefinitionException $e) {
        $display = false;
     }
     catch (Exception $e) {
        $display = false;
     }

     if (!$display) return;

     $isPublished = true;
 
     if (method_exists($s, "isPublished")) {
        $isPublished = $s->isPublished();
     }

     $coyoteProcessUnpublishedNodes = $config->get('coyote_process_unpublished_nodes');

     if (!$coyoteProcessUnpublishedNodes && !$isPublished) return false;
     $contentHelper = new ContentHelper($output);

     $images = $contentHelper->getImages();
     foreach($images as $image) {
         $resource = Util::getImageResource($image, $hostUri);
         if ($resource == null) {
             \Drupal::logger('coyote')->warning(t('@image not processed', ['@image' => $image]));
         }
     }

     $results[] = $id;

     $context['message'] = $message;
     $context['results'] = $results;
  }

  public static function batchProcessingEntitiesFinished($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
        $message = \Drupal::translation()->formatPlural(
            count($results),
            'One entity processed.', '@count entities processed.'
        );
    }
    else {
        $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addStatus($message);
  }

}
