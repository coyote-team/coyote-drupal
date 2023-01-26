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

class CoyoteImgBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->t('coyote_img_batch_form');
  }

  private static function isDefined(?string $var): bool
  {
    return !is_null($var) && strlen($var) > 0;
  }

  private function getProfileRole(?ProfileModel $profile): ?string
  {
    if (is_null($profile)) {
      return null;
    }

    $highestRole = CoyoteMembershipHelper::getHighestMembershipRole($profile->getMemberships());

    if (is_null($highestRole)) {
      return null;
    }

    return $this->t("Owner: {$profile->getName()} ({$highestRole})");
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
      $profile = CoyoteApiClientHelperFunctions::getProfile($endpoint, $token);
      $role = $this->getProfileRole($profile);
    }
     
    $disabled = false;
    if (!self::isDefined($token) || !self::isDefined($endpoint) || !self::isDefined($organizationId) || !self::isDefined($resourceGroup) || is_null($profile) || is_null($role)) {
         $disabled=true;
    }
 
    $form['coyote_message'] = [
       '#type' => 'item',
        '#markup' => "<strong>".$this->t('Values used'). "</strong><br />".$this->t('Coyote API token:')." ".$token."<br />". $this->t('Coyote API endpoint:') ." ".$endpoint. "<br />" . $this->t('Organization ID:'). " ".$organizationId . "<br />". $this->t('Resource Group:'). " ".$resourceGroup."<br />".$this->t("Permissions:")." ".$role,
    ];

    if ($disabled) {
       $form['coyote_warning'] = [
          '#type' => 'item',
          '#markup' => "<strong>".  $this->t("Invalid data, correct in Settings")."</strong>",
       ];
          
    }

    $form['batch_processing'] = [
      '#type' => 'submit',
      '#disabled' => $disabled,
      '#value' => $this->t('Batch processing of all images?'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
     $nids = \Drupal::entityQuery('node')->execute();
     $operations = [];

     foreach ($nids as $nid){     
       $operations[] = [['\Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batchProcessingNodes'], [$nid]];
     }
     $batch = [
          'title' => $this->t('Batch processing All Nodes ...'),
          'operations' => $operations,
          'finished' => ['Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batchProcessingNodesFinished'],
      ];
      batch_set($batch);
  }
  
  public function batchProcessingNodes($nid, &$context) {
     $message = 'Proccessing ALL nodes to coyote';

     $results = $context['results'];
   
     $config = \Drupal::config('coyote_img_desc.settings');

     $entity_type = 'node';
     $view_mode = 'default';

     $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
     $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
     $node = $storage->load($nid);
     $build = $viewBuilder->view($node, $view_mode);
     $build['_coyote_node_url'] = $node->toUrl('canonical', ['absolute' => true])->toString();
     $hostUri = $node->toUrl('canonical', ['absolute' => true])->toString();
     $output = render($build);

     $isPublished = $node->isPublished();
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

     $results[] = $nid;

     $context['message'] = $message;
     $context['results'] = $results;

  }

  public static function batchProcessingNodesFinished($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
        $message = \Drupal::translation()->formatPlural(
            count($results),
            'One node processed.', '@count nodes processed.'
        );
    }
    else {
        $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addStatus($message);
  }
}

