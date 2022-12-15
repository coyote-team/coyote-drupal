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

class CoyoteImgBatchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->t('coyote_img_batch_form');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('coyote_img_desc.settings');

    $form['batch_processing'] = [
      '#type' => 'submit',
      '#value' => $this->t('Batch processing of all images?'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
     $nids = \Drupal::entityQuery('node')->execute();
     $opeations = [
         [['Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batch_processing_nodes'], [$nids]],
      ];
      $batch = [
          'title' => $this->t('Batch processing All Nodes ...'),
          'operations' => $operations,
          'finished' => ['Drupal\coyote_img_desc\Form\CoyoteImgBatchForm','batch_processing_nodes_finished'],
      ];
      batch_set($batch);
  }
  
  public function batch_processing_nodes($nids, &$context) {
     $message = 'Proccessing ALL nodes to coyote';

     $results = array();
     foreach ($nids as $nid) {
         $node = Node::load($nid);
          
         $config = \Drupal::config('coyote_img_desc.settings');
//         $hostUri = $output['_coyote_node_url'];

         $isPublished = $node->isPublished();
         $coyote_process_unpublished_nodes = $config->get('coyote_process_unpublished_nodes');

         if (!$coyote_process_unpublished_nodes && !$isPublished) continue;

  //       $resource = Util::getImageResource($image, $hostUri);
     }
     $context['message'] = $message;
     $context['results'] = $results;

  }

  public function batch_processing_nodes_finished($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
        $message = \Drupal::translation()->formatPlural(
            count($results),
            'One post processed.', '@count posts processed.'
        );
    }
    else {
        $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addStatus($message);
}
}

