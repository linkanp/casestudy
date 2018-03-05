<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Form\FormStateInterface;
use \Drupal\Core\Routing\RouteMatchInterface;
use \Drupal\Core\Url;

/**
 * Class RobotAddForm.
 *
 * Provides the add form for our Robot entity.
 *
 * @ingroup config_entity_example
 */
class TabEditForm extends TabFormBase {

  /**
   * Returns the actions provided by this form.
   *
   * For our add form, we only need to change the text of the submit button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    $current_url = Url::fromRoute('<current>');
    $path = $current_url->getInternalPath();
    $path_args = explode('/', $path);

    $question = $this->getEntity();
    $submit_text = ucwords(str_replace('_',' ',$question->type));

    $actions['submit']['#value'] = $this->t('Update Tab');
    $actions['delete']['#url'] = Url::fromRoute('entity.tab.delete_form', ['casestudy' => $path_args[4],'tab'=> $path_args[6]]);
    return $actions;
  }

}
