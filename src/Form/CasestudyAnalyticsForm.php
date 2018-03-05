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


use Drupal\Core\Database\Database;

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
class CasestudyAnalyticsForm extends EntityForm {

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
        $connection = Database::getConnection();

        /** @var \Drupal\webform\WebformInterface $webform */
        $casestudy = $this->getEntity();
        //$form = $this->settingsForm($form, $form_state);
        $form['start_date'] = [
            '#type' => 'date',
            '#title' => $this->t('Start Date'),
            '#default_value' => (!empty($_GET['start_date']))?$_GET['start_date']:'',

        ];
        $form['end_date'] = [
            '#type' => 'date',
            '#title' => $this->t('End Date'),
            '#default_value' => (!empty($_GET['end_date']))?$_GET['end_date']:'',

        ];
        $form['filter_button'] = [
            '#type' => 'submit',
            '#value' => 'Filter',
            '#attributes' => ['class' => ['form-item']]
        ];

        // Total Start Count
        $total_submission_res = $connection->select('casestudy_start', 'sd')
            ->fields('sd', ['sid'])
            ->condition('sd.casestudy_id', $casestudy->id());
        $total_finished_res = $connection->select('casestudy_start', 'sd')
            ->fields('sd', ['sid'])
            ->condition('sd.casestudy_id', $casestudy->id())
            ->condition('sd.status', 1);
        if(isset($_GET['start_date']) && isset($_GET['end_date'])){
            $total_submission_res->condition('sd.date_start',$_GET['start_date'],'>=');
            $total_submission_res->condition('sd.date_start',$_GET['end_date'],'<=');

            $total_finished_res->condition('sd.date_start',$_GET['start_date'],'>=');
            $total_finished_res->condition('sd.date_start',$_GET['end_date'],'<=');
        }
        $total_submission_res = $total_submission_res->execute();
        $total_submission_res->allowRowCount = TRUE;
        $total_submission = $total_submission_res->rowCount();

        $total_finished_res = $total_finished_res->execute();
        $total_finished_res->allowRowCount = TRUE;
        $total_finished = $total_finished_res->rowCount();


        $header = $this->getTableHeader();
        $rows = [];

        $rows['total_finished'] = [
            'title' => [
                '#markup' => 'Number of people who have finished',
            ],
            'number' => [
                '#markup' => $total_finished,
            ]
        ];
        $rows['total_started'] = [
            'title' => [
                '#markup' => 'Number of people who have started, but not finished',
            ],
            'number' => [
                '#markup' => $total_submission,
            ]
        ];
        $form['rows'] = [
                '#type' => 'table',
                '#header' => $header,
                '#empty' => $this->t('Please add tabs to this case study.'),
                '#attributes' => [
                    'class' => ['webform-ui-elements-table'],
                ],

            ] + $rows;


        // Quiz Analytics
        $elements = $casestudy->getElements();
        $q = 0;
        foreach ($elements as $element_key => $element){
            $elementObj = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($element_key);
            if(empty($elementObj)){
                break;
            }
            if($elementObj->getType() == 'question'){
                $q++;
                $total_submission_res = $connection->select('casestudy_submission_data', 'sd')
                    ->fields('sd', ['sid'])
                    ->condition('sd.casestudy_id', $casestudy->id())
                    ->condition('sd.name', $element_key)
                    ->execute();
                $total_submission_res->allowRowCount = TRUE;
                $total_submission = $total_submission_res->rowCount();
                //echo $total_submission;

                $custom_array = range('a', 'd');

                $qrows = [];
                foreach ($custom_array as $answerkey) {
                    $key = 'answer_' . $answerkey;
                    $label_key = strtoupper($answerkey);
                    if(!empty($elementObj->getAnswer($key))) {
                        $total_this_answer_submission_res = $connection->select('casestudy_submission_data', 'sd')
                            ->fields('sd', ['sid'])
                            ->condition('sd.casestudy_id', $casestudy->id())
                            ->condition('sd.name', $element_key)
                            ->condition('sd.value', $key)
                            ->execute();
                        $total_this_answer_submission_res->allowRowCount = TRUE;
                        $total_this_answer_submission = $total_this_answer_submission_res->rowCount();

                        $percentage = number_format(( $total_this_answer_submission / $total_submission), 2, '.', '');

                        if($elementObj->getCorrectAnswer() == $key){
                            $qrows[$key] = ['title'=> ['#markup' => '<strong>'.$answerkey.'. '.$elementObj->getAnswer($key).' (Correct Answer)</strong>', '#attributes' => ['class' => '80%'] ], 'number' => ['#markup' => '<strong>'.$total_this_answer_submission.'</strong>'], 'percentage' => ['#markup' => '<strong>'.number_format(( $percentage*100), 2, '.', '').'%</strong>']];
                        } else {
                            $qrows[$key] = ['title'=> ['#markup' => $answerkey.'. '.$elementObj->getAnswer($key) , '#attributes' => ['class' => ['abc']]], 'number' => ['#markup' => $total_this_answer_submission], 'percentage' => ['#markup' => number_format(( $percentage*100), 2, '.', '').'%']];
                            //$qrows[$key]['data'] = array(array('data'=> $answerkey.'. '.$elementObj->getAnswer($key), 'width'=>'80%'), $answer_count, number_format(( $percentage*100), 2, '.', '').'%');
                        }

                    }
                }


                $header = [];
                $header['title'] = $this->t($elementObj->label());
                $header['number'] = $this->t('Number of Response');
                $header['percentage'] = $this->t('Percentage');

                $css = '.webform-ui-elements-table tr:td { width:80% }';


                $form['quiz_question_analytics_'.$elementObj->id()] = [
                        '#type' => 'table',
                        '#header' => $header,
                        '#caption' => $this->t('<strong style="text-align: center">Question '.$q.' Analytics</strong>'),
                        '#empty' => $this->t('There is no question answer.'),
                        '#attributes' => [
                            'class' => ['webform-ui-elements-table'],
                        ]

                    ] + $qrows;


            }
        }




        $form_state->setMethod('GET');
        return parent::form($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        //drupal_set_message('Nothing Submitted. Just an Example.');
        //header("Location: http://apha-redesign.local/");
        //echo 'xx';exit;
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

//        print_r($casestudy);
//        exit;

//        $elements = \Drupal::entityTypeManager()
//            ->getStorage('question')
//            ->loadByProperties([
//                'casestudy' => $casestudy->id(),
//            ]);
        //print_r($el);

        $elements = $casestudy->getTabs();



        $header = $this->getTableHeader();
        $rows = [];

        $rows['total_started'] = [
            'title' => 'Number',
            'number' => 'Number'
        ];
        $form['rows'] = [
                '#type' => 'table',
                '#header' => $header,
                '#empty' => $this->t('Please add tabs to this case study.'),
                '#attributes' => [
                    'class' => ['webform-ui-elements-table'],
                ],

            ] + $rows;
        //print_r($form);

        // Must preload libraries required by (modal) dialogs.
        //WebformDialogHelper::attachLibraries($form);
        //$form['#attached']['library'][] = 'webform_ui/webform_ui';
        return $form;
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

        //print_r($actions);
        // Change the submit button text.
        //$actions['submit']['#value'] = $this->t('Filter');

        unset($actions['delete']);
        unset($actions['submit']);

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
     * Gets the elements table header.
     *
     * @return array
     *   The header elements.
     */
    protected function getTableHeader() {
        /** @var \Drupal\webform\WebformInterface $webform */
        $casestudy = $this->getEntity();
        $header = [];
        $header['title'] = $this->t($casestudy->label());
        $header['number'] = $this->t('Number');
        return $header;
    }



}
