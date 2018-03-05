<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Url;

/**
 * Class RobotFormBase.
 *
 * Typically, we need to build the same form for both adding a new entity,
 * and editing an existing entity. Instead of duplicating our form code,
 * we create a base class. Drupal never routes to this class directly,
 * but instead through the child classes of RobotAddForm and RobotEditForm.
 *
 * @ingroup config_entity_example
 */
class CasestudyFinalPageForm extends EntityForm {

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * Construct the RobotFormBase.
   *
   * For simple entity forms, there's no need for a constructor. Our robot form
   * base, however, requires an entity query factory to be injected into it
   * from the container. We later use this query factory to build an entity
   * query for the exists() method.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   An entity query factory for the robot entity type.
   */
  public function __construct(QueryFactory $query_factory) {
    $this->entityQueryFactory = $query_factory;
  }

  /**
   * Factory method for RobotFormBase.
   *
   * When Drupal builds this class it does not call the constructor directly.
   * Instead, it relies on this method to build the new object. Why? The class
   * constructor may take multiple arguments that are unknown to Drupal. The
   * create() method always takes one parameter -- the container. The purpose
   * of the create() method is twofold: It provides a standard way for Drupal
   * to construct the object, meanwhile it provides you a place to get needed
   * constructor parameters from the container.
   *
   * In this case, we ask the container for an entity query factory. We then
   * pass the factory to our class as a constructor parameter.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.query'));
  }

    /**
     * {@inheritdoc}
     */
    public function form(array $form, FormStateInterface $form_state) {
        /** @var \Drupal\webform\WebformInterface $webform */
        $casestudy = $this->getEntity();
        $form['final_page_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Final Page Title'),
            '#maxlength' => 255,
            '#default_value' => $casestudy->final_page_title,
            '#required' => TRUE,
            '#id' => 'tab-name',
            '#attributes' => [
                'autofocus' => 'autofocus',
            ],
        ];
        $form['final_page_summary_title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Result page Summary Title'),
            '#maxlength' => 255,
            '#default_value' => $casestudy->final_page_summary_title,
            '#required' => TRUE,
            '#id' => 'tab-name',
            '#attributes' => [
                'autofocus' => 'autofocus',
            ],
        ];
        $form['final_page_description'] = [
            '#type' => 'text_format',
            '#format' => 'full_html',
            '#required' => TRUE,
            '#title' => $this->t('Final Page HTML'),
            '#default_value' => $casestudy->get('final_page_description'),
        ];
        $form['final_page_claim_button_show'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Check to show claim your credit button'),
            '#default_value' => $casestudy->final_page_claim_button_show,
        ];
        $form['final_page_claim_button_text'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Claim your Credit Button Text'),
            '#description' => $this->t('Default is: "Claim your Credit"'),
            '#maxlength' => 255,
            '#default_value' => $casestudy->final_page_claim_button_text,
            '#id' => 'tab-name',
            '#attributes' => [
                'autofocus' => 'autofocus',
            ],
        ];
        $form['final_page_claim_url'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Claim your Credit URL'),
            '#maxlength' => 255,
            '#default_value' => $casestudy->final_page_claim_url,
            '#required' => TRUE,
            '#id' => 'tab-name',
            '#attributes' => [
                'autofocus' => 'autofocus',
            ],
        ];
        return parent::form($form, $form_state);
    }

    /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   *
   * Builds the entity add/edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the robot add/edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get anything we need from the base class.
    $form = parent::buildForm($form, $form_state);

    // Drupal provides the entity to us as a class variable. If this is an
    // existing entity, it will be populated with existing values as class
    // variables. If this is a new entity, it will be a new object with the
    // class of our entity. Drupal knows which class to call from the
    // annotation on our Robot class.
    $robot = $this->entity;

//      return $this->buildDialogForm($form, $form_state);

    // Return the form.
    return $form;
  }


  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * To set the submit button text, we need to override actions().
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
    // Get the basic actins from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');
    unset($actions['delete']);

    // Return the result.
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   *
   * Saves the entity. This is called after submit() has built the entity from
   * the form values. Do not override submit() as save() is the preferred
   * method for entity form controllers.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function save(array $form, FormStateInterface $form_state) {
    // EntityForm provides us with the entity we're working on.

    $casestudy = $this->getEntity();

    $desc = $casestudy->get('final_page_description');
      $casestudy->set('final_page_description',trim($desc['value']));

    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class. PHP
    // allows Drupal to do this even if the method is not defined ahead of
    // time.
    $status = $casestudy->save();

    // Grab the URL of the new entity. We'll use it in the message.
    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      drupal_set_message($this->t('Final Page for casestudy %label has been updated.', ['%label' => $casestudy->label()]));
      //$this->logger('contact')->notice('Settings for casestudy %label has been updated.', ['%label' => $casestudy->label(), 'link' => $edit_link]);
    }
    else {
      // If we created a new entity...
      drupal_set_message($this->t('Final Page %label has been added.', ['%label' => $casestudy->label()]));
      $this->logger('contact')->notice('Robot %label has been added.', ['%label' => $casestudy->label(), 'link' => $edit_link]);
    }

    // Redirect the user back to the listing route after the save operation.
      $url = Url::fromRoute('entity.casestudy.final_page_form', ['casestudy' => $casestudy->id()]);
      $form_state->setRedirectUrl($url);

  }


}
