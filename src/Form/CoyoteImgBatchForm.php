<?php

namespace Drupal\coyote_img_desc\Form;

require_once( __DIR__ . '/../../vendor/autoload.php');

use Coyote\CoyoteApiClientHelperFunctions;
use Coyote\Model\ProfileModel;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\coyote_img_desc\Constants;
use Drupal\coyote_img_desc\Helper\CoyoteMembershipHelper;
use Drupal\coyote_img_desc\Util;
use Coyote\ContentHelper;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

class CoyoteImgBatchForm extends FormBase {
  private ?ProfileModel $profile;
  private ?string $role;

  public function getFormId(): string
  {
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
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = Util::getSuffixedApiEndpoint();
    $resourceGroup = $config->get('api_resource_group');
    $organizationId = $config->get('api_organization');

    if (self::isDefined($token) && self::isDefined($endpoint)) {
      $this->profile = CoyoteApiClientHelperFunctions::getProfile($endpoint, $token);
      $this->role = $this->getProfileRole();
    }
     
    $validConfig = $this->hasValidBatchProcessingConfig();

    $form['coyote_message'] = [
       '#type' => 'item',
        '#markup' => "<strong>" .
          $this->t('Values used') .
          "</strong><br />" .
          $this->t('Coyote API token:') .
          " " .
          $token .
          "<br />" .
          $this->t('Coyote API endpoint:') .
          " " .
          $endpoint .
          "<br />" .
          $this->t('Organization ID:') .
          " " .
          $organizationId .
          "<br />" .
          $this->t('Resource Group:') .
          " " .
          $resourceGroup .
          "<br />" .
          $this->t("Permissions:") .
          " " .
          $this->role,
    ];

    if (!$validConfig) {
       $form['coyote_warning'] = [
          '#type' => 'item',
          '#markup' => "<strong>".  $this->t("Invalid configuration, please verify the settings are correct.")."</strong>",
       ];
          
    }

    $form['batch_processing'] = [
      '#type' => 'submit',
      '#disabled' => !$validConfig,
      '#value' => $this->t('Batch process all images through Coyote'),
    ];

    return $form;
  }

  private function hasValidBatchProcessingConfig(): bool
  {
    $config = $this->config('coyote_img_desc.settings');
    $token = $config->get('api_token');
    $endpoint = Util::getSuffixedApiEndpoint();
    $resourceGroup = $config->get('api_resource_group');
    $organizationId = $config->get('api_organization');

    if (
      !self::isDefined($token) ||
      !self::isDefined($endpoint) ||
      !self::isDefined($organizationId) ||
      !self::isDefined($resourceGroup) ||
      is_null($this->profile) ||
      is_null($this->role)
    ) {
         return false;
    }

    return true;
  } 

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
     $entities = array_keys(\Drupal::entityTypeManager()->getDefinitions());
     $operations = [];

     foreach ($entities as $entity){  
        $ids = \Drupal::entityQuery($entity)->execute();
        foreach ($ids as $id){     
          $operations[] = [['\Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batchProcessingEntities'], [$id, $entity]];
        }
     }

     $batch = [
          'title' => $this->t('Batch processing all images...'),
          'operations' => $operations,
          'finished' => ['Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batchProcessingEntitiesFinished'],
     ];

     batch_set($batch);
  }
  
  public static function batchProcessingEntities($id, $entity, &$context): bool
  {
     $message = 'Processing all available entities...';
     $results = $context['results'];
   
     $config = \Drupal::config('coyote_img_desc.settings');

     $entity_type = $entity;
     $view_mode = 'default';

     try {
        $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        $s = $storage->load($id);

        $build = $viewBuilder->view($s, $view_mode);
        $hostUri = $s->toUrl('canonical', ['absolute' => true])->toString();
        $output = render($build);
     } catch (UndefinedLinkTemplateException | InvalidPluginDefinitionException | \Exception $e) {
       \Drupal::logger(Constants::MODULE_NAME)->warning(`Exception during entity processing: {$e->getMessage()}`);
        return false;
     }

     $isPublished = true;
 
     if (method_exists($s, "isPublished")) {
        $isPublished = $s->isPublished();
     }

     $coyoteProcessUnpublishedNodes = $config->get('coyote_process_unpublished_nodes');

     if (!$coyoteProcessUnpublishedNodes && !$isPublished) {
       return false;
     }

     $contentHelper = new ContentHelper($output);

     $images = $contentHelper->getImages();
     foreach($images as $image) {
         $resource = Util::getImageResource($image, $hostUri);
         if ($resource == null) {
             \Drupal::logger(Constants::MODULE_NAME)->warning(t('Unable to process image @image', ['@image' => $image]));
         }
     }

     $results[] = $id;

     $context['message'] = $message;
     $context['results'] = $results;

     return true;
  }

  public static function batchProcessingEntitiesFinished($success, $results, $operations): void
  {
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
