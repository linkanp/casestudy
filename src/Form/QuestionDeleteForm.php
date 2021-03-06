<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RobotDeleteForm.
 *
 * Provides a confirm form for deleting the entity. This is different from the
 * add and edit forms as it does not inherit from RobotFormBase. The reason for
 * this is that we do not need to build the same form. Instead, we present the
 * user with a simple yes/no question. For this reason, we derive from
 * EntityConfirmFormBase instead.
 *
 * @ingroup config_entity_example
 */
class QuestionDeleteForm extends EntityConfirmFormBase {

  /**
   * Gathers a confirmation question.
   *
   * The question is used as a title in our confirm form. For delete confirm
   * forms, this typically takes the form of "Are you sure you want to
   * delete...", including the entity label.
   *
   * @return string
   *   Translated string.
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete question %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * Gather the confirmation text.
   *
   * The confirm text is used as the text in the button that confirms the
   * question posed by getQuestion().
   *
   * @return string
   *   Translated string.
   */
  public function getConfirmText() {
    return $this->t('Delete Question');
  }

  /**
   * Gets the cancel URL.
   *
   * Provides the URL to go to if the user cancels the action. For entity
   * delete forms, this is typically the route that points at the list
   * controller.
   *
   * @return \Drupal\Core\Url
   *   The URL to go to if the user cancels the deletion.
   */
    public function getCancelUrl() {

        $current_url = Url::fromRoute('<current>');
        $path = $current_url->getInternalPath();
        $path_args = explode('/', $path);

        return new Url('entity.casestudy.settings_form', ['casestudy' => $path_args[4]]);
    }

  /**
   * The submit handler for the confirm form.
   *
   * For entity delete forms, you use this to delete the entity in
   * $this->entity.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Delete the entity.
    $this->entity->delete();
    // Set a message that the entity was deleted.
    drupal_set_message($this->t('Please save this settings page to completely delete %label from this Casestudy or Quiz. ', [
      '%label' => $this->entity->label(),
    ]), 'warning');
    // Redirect the user to the list controller when complete.
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
