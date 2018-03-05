<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use \Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Url;
use Drupal\webform\Utility\WebformDialogHelper;


use Drupal\Core\Serialization\Yaml;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Render\Element;
use Drupal\webform\Form\WebformEntityAjaxFormTrait;
use Drupal\webform\WebformEntityForm;

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
class CasestudySettingsForm extends EntityForm {

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
        //$casestudy = $this->getEntity();
        $form = $this->settingsForm($form, $form_state);
        return parent::form($form, $form_state);
    }
    /**
     * Edit webform element's source code webform.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The webform structure.
     */
    protected function settingsForm(array $form, FormStateInterface $form_state) {
        $casestudy = $this->getEntity();

        if ($casestudy->isNew()) {
            return $form;
        }

//        $elements = \Drupal::entityTypeManager()
//            ->getStorage('question')
//            ->loadByProperties([
//                'casestudy' => $casestudy->id(),
//            ]);
        //print_r($el);

        $elements = $casestudy->getElements();
//        print_r($casestudy);
//        print_r($elements);
//        exit;

        $header = $this->getTableHeader();
        $rows = [];
        $delta = count($elements);
        foreach ($elements as $key => $element) {
            $elementObj = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($key);
            if($elementObj){
                $elementObj->weight = $element['weight'];
                $rows[$key] = $this->getElementRow($elementObj, $delta);
            }
        }


//        $local_actions = [];
//        $local_actions['add_element'] = [
//            '#theme' => 'menu_local_action',
//            '#link' => [
//                'title' => $this->t('Add Question'),
//                'url' => new Url('entity.question.add', ['casestudy' => $casestudy->id()]),
//                //'attributes' => WebformDialogHelper::getModalDialogAttributes(800),
//            ]
//        ];
//            $local_actions['add_page'] = [
//                '#theme' => 'menu_local_action',
//                '#link' => [
//                    'title' => $this->t('Add HTML Page'),
//                    'url' => new Url('entity.question.add_html', ['casestudy' => $casestudy->id()]),
//                    'attributes' => WebformDialogHelper::getModalDialogAttributes(800),
//                ]
//            ];
//
//        $form['local_actions'] = [
//                '#prefix' => '<ul class="action-links">',
//                '#suffix' => '</ul>',
//            ] + $local_actions;

        $form['elements'] = [
                '#type' => 'table',
                '#header' => $header,
                '#empty' => $this->t('Please add elements to this case study.'),
                '#attributes' => [
                    'class' => ['webform-ui-elements-table'],
                ],
                '#tabledrag' => [
                    [
                        'action' => 'order',
                        'relationship' => 'sibling',
                        'group' => 'row-weight',
                    ],
                ],
            ] + $rows;
        //print_r($form);

        // Must preload libraries required by (modal) dialogs.
        //WebformDialogHelper::attachLibraries($form);
        //$form['#attached']['library'][] = 'webform_ui/webform_ui';
        return $form;
    }


    /**
     * Gets an row for a single element.
     *
     * @param Question entity $element
     *   Webform element.
     * @param int $delta
     *   The number of elements. @todo is this correct?
     *
     * @return array
     *   The row for the element.
     */
    protected function getElementRow($element, $delta) {
        /** @var \Drupal\webform\WebformInterface $webform */
        $casestudy = $this->getEntity();
        //print_r($casestudy);

        //exit;
        $row = [];


        $key = $element->id();
        //echo $key.'#'.$weights[$key]['weight'];

//        $element_dialog_attributes = WebformDialogHelper::getModalDialogAttributes(800);
//        $plugin_id = $this->elementManager->getElementPluginId($element);
//
//        /** @var \Drupal\webform\Plugin\WebformElementInterface $webform_element */
//        $webform_element = $this->elementManager->createInstance($plugin_id);
//
//        $is_container = $webform_element->isContainer($element);
//        $is_root = $webform_element->isRoot();
//
//        // If disabled, display warning.
//        if ($webform_element->isDisabled()) {
//            $webform_element->displayDisabledWarning($element);
//        }

        $is_root = true;
        // Get row class names.
        $row_class = ['draggable'];
        if ($is_root) {
            $row_class[] = 'tabledrag-root';
            $row_class[] = 'webform-ui-element-root';
        }
//        if (!$is_container) {
//            $row_class[] = 'tabledrag-leaf';
//        }
//        if ($is_container) {
//            $row_class[] = 'webform-ui-element-container';
//        }
//        if (!empty($element['#type'])) {
//            $row_class[] = 'webform-ui-element-type-' . $element['#type'];
//        }
//        else {
//            $row_class[] = 'webform-ui-element-container';
//        }

        // Add element key.
        //$row['#attributes']['data-webform-key'] = $element['#webform_key'];

        $row['#attributes']['class'] = $row_class;

        $row['title'] = [
            '#markup' => $element->label(),
//            '#url' => new Url('entity.webform_ui.element.edit_form', [
//                'webform' => $webform->id(),
//                'key' => $key,
//            ]),
            //'#attributes' => $element_dialog_attributes,
        ];


            $row['key'] = [
                '#markup' => $element->id(),
            ];

            $row['type'] = [
                '#markup' => $element->type,
            ];
            $row['correct_answer'] = [
                '#markup' => $element->correct_answer,
            ];

        $row['weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for ID @id', ['@id' => $key]),
            '#title_display' => 'invisible',
            '#default_value' => $element->weight,
            '#attributes' => [
                'class' => ['row-weight'],
            ],
            '#delta' => $delta,
        ];

        $row['operations'] = [
            '#type' => 'operations',
        ];
        $row['operations']['#links']['edit'] = [
            'title' => $this->t('Edit'),
            'url' => new Url(
                'entity.question.edit_form',
                [
                    'casestudy' => $casestudy->id(),
                    'question' => $key,
                ]
            ),
        ];

        $row['operations']['#links']['delete'] = [
            'title' => $this->t('Delete'),
            'url' => new Url(
                'entity.question.delete_form',
                [
                    'casestudy' => $casestudy->id(),
                    'question' => $key,
                ]
            ),
            'attributes' => WebformDialogHelper::getModalDialogAttributes(700),
        ];

        //print_r($row);
        //exit;
        return $row;
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
   * Checks for an existing robot.
   *
   * @param string|int $entity_id
   *   The entity ID.
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this format already exists, FALSE otherwise.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    // Use the query factory to build a new robot entity query.
    $query = $this->entityQueryFactory->get('robot');

    // Query the entity ID to see if its in use.
    $result = $query->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute();

    // We don't need to return the ID, only if it exists or not.
    return (bool) $result;
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

    // Return the result.
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::validate().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    // Add code here to validate your config entity's form elements.
    // Nothing to do here.
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



      //print_r($casestudy);
    $casestudy->setElements($casestudy->get('elements'));
      //print_r($casestudy);
      //echo 'herere'; exit;


//    print_r($robot);
//    exit;
    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class. PHP
    // allows Drupal to do this even if the method is not defined ahead of
    // time.
    $status = $casestudy->save();

    // Grab the URL of the new entity. We'll use it in the message.
    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      drupal_set_message($this->t('Settings for casestudy %label has been updated.', ['%label' => $casestudy->label()]));
      //$this->logger('contact')->notice('Settings for casestudy %label has been updated.', ['%label' => $casestudy->label(), 'link' => $edit_link]);
    }
    else {
      // If we created a new entity...
      drupal_set_message($this->t('Casestudy %label has been added.', ['%label' => $casestudy->label()]));
      $this->logger('contact')->notice('Robot %label has been added.', ['%label' => $casestudy->label(), 'link' => $edit_link]);
    }

    // Redirect the user back to the listing route after the save operation.
      $url = Url::fromRoute('entity.casestudy.settings_form', ['casestudy' => $casestudy->id()]);
      $form_state->setRedirectUrl($url);
  }

    /**
     * Gets the elements table header.
     *
     * @return array
     *   The header elements.
     */
    protected function getTableHeader() {
        /** @var \Drupal\webform\WebformInterface $webform */
        $webform = $this->getEntity();
        $header = [];
            $header['title'] = $this->t('Title');

            $header['key'] = [
                'data' => $this->t('Key'),
                'class' => [RESPONSIVE_PRIORITY_LOW],
            ];
            $header['type'] = [
                'data' => $this->t('Type'),
                'class' => [RESPONSIVE_PRIORITY_LOW],
            ];
            $header['correct_answer'] = $this->t('Answer');
            $header['weight'] = $this->t('Weight');
            $header['operations'] = $this->t('Operations');
//            $header['answer'] = [
//                'data' => $this->t('Answer'),
//                'class' => [RESPONSIVE_PRIORITY_LOW],
//            ];
//
//            $header['required'] = [
//                'data' => $this->t('Required'),
//                'class' => ['webform-ui-element-required', RESPONSIVE_PRIORITY_LOW],
//            ];
//        $header['parent'] = $this->t('Parent');
//            $header['operations'] = [
//                'data' => $this->t('Operations'),
//                'class' => ['webform-ui-element-operations'],
//            ];
        return $header;
    }

}
