<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use \Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

use Drupal\Core\Url;
use Drupal\webform\Utility\WebformDialogHelper;


use Drupal\Core\Serialization\Yaml;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Render\Element;
use Drupal\webform\Form\WebformEntityAjaxFormTrait;
use Drupal\webform\WebformEntityForm;


use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\AliasStorageInterface;


use Drupal\Core\Language\LanguageInterface;


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
class CasestudyFormBase extends EntityForm {

    /**
     * The path alias manager.
     *
     * @var \Drupal\Core\Path\AliasManagerInterface
     */
    protected $aliasManager;

    /**
     * The path alias manager.
     *
     * @var \Drupal\Core\Path\AliasStorageInterface
     */
    protected $aliasStorage;

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
  public function __construct(QueryFactory $query_factory, AliasStorageInterface $alias_storage, AliasManagerInterface $alias_manager) {
    $this->entityQueryFactory = $query_factory;
      $this->aliasManager = $alias_manager;
      $this->aliasStorage = $alias_storage;
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
    return new static($container->get('entity.query'),$container->get('path.alias_storage'),
        $container->get('path.alias_manager'));
  }

    /**
     * {@inheritdoc}
     */
    public function form(array $form, FormStateInterface $form_state) {
        /** @var \Drupal\webform\WebformInterface $webform */
        $casestudy = $this->getEntity();
//
//        print_r($casestudy);
//        exit;

        // Only display id, title, and description for new webforms.
        // Once a webform is created this information is moved to the webform's settings
        // tab.
        //if ($casestudy->isNew()) {
            $form['label'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Case Study or Quiz Title'),
                '#maxlength' => 255,
                '#default_value' => $casestudy->label(),
                '#required' => TRUE,
                '#id' => 'label',
                '#attributes' => [
                    'autofocus' => 'autofocus',
                ],
            ];

            $form['id'] = [
                '#type' => 'machine_name',
                '#default_value' => $casestudy->id(),
                '#machine_name' => [
                    'exists' => '\Drupal\casestudy\Entity\Casestudy::load',
                    'source' => ['label'],
                ],
                '#maxlength' => 32,
                '#disabled' => (bool) $casestudy->id(),
                '#required' => TRUE,
            ];

            $form['tab'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Case Study Tab name'),
                '#description' => $this->t('Default is "Case Study"'),
                '#maxlength' => 255,
                '#default_value' => $casestudy->tab,
                '#id' => 'tab-name',
                '#attributes' => [
                    'autofocus' => 'autofocus',
                ],
            ];
            $form['path'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Case Study or Quiz Path'),
                '#description' => $this->t('Example paths are "/casestudy2", "/mynewcasestudy" or "/example-casestudy" etc. Please select "Directory Prefix" below for creating the ultimate path.' ),
                '#maxlength' => 255,
                '#default_value' => $casestudy->path,
                '#required' => TRUE,
                '#id' => 'tab-name',
                '#attributes' => [
                    'autofocus' => 'autofocus',
                ],
            ];
            $form['prefix'] = [
                '#type' => 'select',
                '#title' => $this->t('Directory Prefix'),
                '#options' => array('CTP'=>'CTP','ACT'=>'ACT','advanced-training'=>'advanced-training', 'quiz'=>'Quiz'),
                '#default_value' => $casestudy->prefix,
                '#required' => TRUE,
                '#id' => 'prefix',
            ];

            $default_image = array();
            if( !empty($casestudy->banner) ) {
                $default_image = array($casestudy->banner);
            }

            $form['banner'] = [
                '#type' => 'managed_file',
                '#title' => $this->t('Banner'),
                '#description' => $this->t('Allowed extensions: gif png jpg jpeg'),
                '#upload_validators' => array(
                    'file_validate_is_image' => array(),
                    'file_validate_extensions' => array('gif png jpg jpeg'),
                    'file_validate_size' => array(25600000),
                ),
                '#upload_location' => 'public://casestudy_banner',
                '#theme' => 'image_widget',
                #'#required' => TRUE,
                '#preview_image_style' => 'medium',
                '#default_value' => $default_image,
            ];

            $form['gallery'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Case study slide Show ID (Photo Gallery Node ID)'),
                '#maxlength' => 255,
                '#default_value' => $casestudy->gallery,
                '#id' => 'gallery-id',
                '#attributes' => [
                    'autofocus' => 'autofocus',
                ],
            ];

            $form['status'] = [
                '#type' => 'select',
                '#title' => $this->t('Status'),
                '#options' => array('Disabled','Enabled'),
                '#default_value' => $casestudy->status(),
                '#required' => TRUE,
                '#id' => 'status',
            ];

            $form['left'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Check to show left navigation menu'),
                '#default_value' => $casestudy->left,
            ];
//            $form['description'] = [
//                '#type' => 'webform_html_editor', // Here this module depends on Webform, need to overcome this dependency using a text type provided by Drupal Core
//                '#required' => TRUE,
//                '#title' => $this->t('Case Study Introduction Text'),
//                '#default_value' => $casestudy->get('description'),
//            ];

        $form['description'] = [
            '#type' => 'text_format',
            '#format' => 'full_html',
            '#required' => TRUE,
            '#title' => $this->t('Case Study Introduction Text'),
            '#default_value' => $casestudy->get('description'),
        ];

        //}

        // Call the isolated edit webform that can be overridden by the
        // webform_ui.module.
        ///$form = $this->editForm($form, $form_state);

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
    $actions['submit']['#validate'][] = '::validateForm';

    // Return the result.
    return $actions;
  }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

    // Add code here to validate your config entity's form elements.
    // Nothing to do here.
    $path = $form_state->getValue('path');
    // Trim the submitted value of whitespace and slashes. Ensure to not trim
    // the slash on the left side.
    $path = rtrim(trim(trim($path), ''), "\\/");
    if ($path[0] !== '/') {
      $form_state->setErrorByName('path', 'The path has to start with a slash.');
    }
        $path = '/'.$form_state->getValue('prefix').$path;

        // Language is only set if language.module is enabled, otherwise save for all
      // languages.

      $langcode = $form_state->getValue('langcode', LanguageInterface::LANGCODE_NOT_SPECIFIED);


      if ( $form['path']['#default_value'] != $form_state->getValue('path') && $this->aliasStorage->aliasExists($path, $langcode)) {
          $form_state->setErrorByName('path', t('The path %path is already in use.', ['%path' => $path]));
      }


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
    $robot = $this->getEntity();
    if($robot->isNew()){
        $robot->created = date('Y-m-d h:m:i');
    }
    $desc = $robot->get('description');
    $robot->set('description',trim($desc['value']));

    if(!empty($robot->banner)){
        $image = $robot->banner;
        //print_r($image);

        $file = File::load( $image[0] );
        $file->setPermanent();
        $file->save();
        //print_r($file);
        $robot->banner = $image[0];
    }

    //exit;
    //print_r($robot);
    //exit;
//    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class. PHP
    // allows Drupal to do this even if the method is not defined ahead of
    // time.
    //exit;
    $status = $robot->save();
    \Drupal::service('path.alias_storage')->save('/casestudy/'.$robot->id(), '/'.$robot->prefix.$robot->path);
    // Grab the URL of the new entity. We'll use it in the message.
    $url = $robot->urlInfo();

    // Create an edit link.
    $edit_link = Link::fromTextAndUrl($this->t('Edit'), $url)->toString();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      drupal_set_message($this->t('Casestudy %label has been updated.', ['%label' => $robot->label()]));
      $this->logger('contact')->notice('Robot %label has been updated.', ['%label' => $robot->label(), 'link' => $edit_link]);
    }
    else {
      // If we created a new entity...
      drupal_set_message($this->t('Casestudy %label has been added.', ['%label' => $robot->label()]));
      $this->logger('contact')->notice('Robot %label has been added.', ['%label' => $robot->label(), 'link' => $edit_link]);
    }
    // Update the Path Alias
    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.casestudy.list');
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
            $header['weight'] = $this->t('Weight');
        return $header;
    }


}
