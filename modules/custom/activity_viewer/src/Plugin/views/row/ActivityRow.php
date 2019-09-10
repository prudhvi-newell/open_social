<?php

namespace Drupal\activity_viewer\Plugin\views\row;

use Drupal\views\Plugin\views\row\EntityRow;
use Drupal\user\Entity\User;

/**
 * Plugin which performs a activity_view on the resulting object.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "entity:activity",
 * )
 */
class ActivityRow extends EntityRow {

  /**
   * {@inheritdoc}
   */
  public function preRender($result) {

    $view_mode = $this->options['view_mode'];

    if ($result) {
      // TODO: Move all this logic to a service.
      // TODO: Change this to use dependency injection.
      /* @var $plugin \Drupal\activity_creator\Plugin\ActivityDestinationManager */
      $destination_plugin_manager = \Drupal::service('plugin.manager.activity_destination.processor');

      foreach ($result as $row) {
        $render_result = [];
        $entity = $row->_entity;
        $target_type = $entity->get('field_activity_entity')->target_type;
        $render_entity = TRUE;

        if (!empty($target_type) && $target_type === "comment") {
          $comment_id = $entity->get('field_activity_entity')->target_id;
          $comment_storage = \Drupal::entityTypeManager()->getStorage('comment');
          $comment = $comment_storage->load($comment_id);
          $parent_id = $comment->get('entity_id')->target_id;
          $parent_type = $comment->get('entity_type')->value;

          if (!empty($parent_id) && !empty($parent_type) && $parent_type === "post") {
            $parent_storage = \Drupal::entityTypeManager()->getStorage($parent_type);
            $parent_entity = $parent_storage->load($parent_id);
            $current_user = \Drupal::currentUser()->getAccount();
            $parent_status = $parent_entity->get('status')->value;
            $current_user_is_admin = $current_user->hasPermission('view unpublished post entities');

            if ((empty($parent_status) || $parent_status === "0") && !$current_user_is_admin) {
              $render_entity = FALSE;
            }
          }
        }

        if ($render_entity) {
          $render_result[] = $row;
        }

        foreach ($entity->field_activity_destinations as $destination) {
          /* @var $plugin \Drupal\activity_creator\Plugin\ActivityDestinationBase */
          $plugin = $destination_plugin_manager->createInstance($destination->value);
          if ($plugin->isActiveInView($this->view)) {
            $this->options['view_mode'] = $plugin->getViewMode($view_mode, $entity);
          }
        }
        $this->getEntityTranslationRenderer()->preRender($render_result);
      }
    }
    $this->options['view_mode'] = $view_mode;

  }

}
