<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Url;
use Drupal\webform\Utility\WebformDialogHelper;

/**
 * Class RobotAddForm.
 *
 * Provides the add form for our Robot entity.
 *
 * @ingroup config_entity_example
 */
class QuestionFormBase extends EntityForm {

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


    public static function create(ContainerInterface $container) {
        return new static($container->get('entity.query'));
    }

    /**
     * {@inheritdoc}
     */
    public function form(array $form, FormStateInterface $form_state) {
        /** @var \Drupal\webform\WebformInterface $webform */
        $question = $this->getEntity();

        $current_url = Url::fromRoute('<current>');
        $path = $current_url->getInternalPath();
        $path_args = explode('/', $path);
        if($question->isNew()){
            $question->type = $path_args[7];
        }

        // Only display id, title, and description for new webforms.
        // Once a webform is created this information is moved to the webform's settings
        // tab.
            $form['label'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Title'),
                '#maxlength' => 255,
                '#default_value' => $question->label(),
                '#required' => TRUE,
                '#id' => 'label',
                '#attributes' => [
                    'autofocus' => 'autofocus',
                ],
            ];

            $form['id'] = [
                '#type' => 'machine_name',
                '#default_value' => $question->id(),
                '#machine_name' => [
                    'exists' => '\Drupal\casestudy\Entity\Question::load',
                    'source' => ['label'],
                ],
                '#maxlength' => 32,
                '#disabled' => (bool) $question->id(),
                '#required' => TRUE,
            ];
            $form['description'] = [
                '#type' => 'text_format',
                '#format' => 'full_html',
                '#required' => TRUE,
                '#title' => $this->t('Description'),
                '#default_value' => $question->get('description'),
            ];

            if($question->type == 'question') {

                $form['answer_a'] = [
                    '#type' => 'textfield',
                    '#required' => TRUE,
                    '#title' => $this->t('Answer (a)'),
                    '#default_value' => $question->answer_a,
                ];
                $form['answer_b'] = [
                    '#type' => 'textfield',
                    '#required' => TRUE,
                    '#title' => $this->t('Answer (b)'),
                    '#default_value' => $question->answer_b,
                ];
                $form['answer_c'] = [
                    '#type' => 'textfield',
                    '#title' => $this->t('Answer (c)'),
                    '#default_value' => $question->answer_c,
                ];
                $form['answer_d'] = [
                    '#type' => 'textfield',
                    '#title' => $this->t('Answer (d)'),
                    '#default_value' => $question->answer_d,
                ];

                $form['correct_answer'] = [
                    '#type' => 'select',
                    '#options' => ['answer_a' => 'Answer (a)', 'answer_b' => 'Answer (b)', 'answer_c' => 'Answer (c)', 'answer_d' => 'Answer (d)'],
                    '#title' => $this->t('Select Correct Answer'),
                    '#default_value' => $question->getCorrectAnswer(),
                ];
                $form['question_page_html'] = [
                    '#type' => 'text_format',
                    '#format' => 'full_html',
                    '#required' => TRUE,
                    '#title' => $this->t('Question Page HTML'),
                    '#default_value' => $question->question_page_html,
                ];
                $form['result_page_html'] = [
                    '#type' => 'text_format',
                    '#format' => 'full_html',
                    '#required' => TRUE,
                    '#title' => $this->t('Result Page HTML'),
                    '#default_value' => $question->result_page_html,
                ];
            }

//            $form['description'] = [
//                '#type' => 'webform_html_editor', // Here this module depends on Webform, need to overcome this dependency using a text type provided by Drupal Core
//                '#required' => TRUE,
//                '#title' => $this->t('Case Study Introduction Text'),
//                '#default_value' => $question->get('description'),
//            ];



        // Call the isolated edit webform that can be overridden by the
        // webform_ui.module.
        //$form = $this->editForm($form, $form_state);

        return parent::form($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        /** @var \Drupal\webform\WebformInterface $webform */
        $webform = $this->getEntity();

        $form = parent::buildForm($form, $form_state);
        return $form;
        //return $this->buildDialogForm($form, $form_state);
    }

    /**
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   An associative array containing the current state of the form.
     * @return Redirect to URL
     */
    public function save(array $form, FormStateInterface $form_state) {
        // EntityForm provides us with the entity we're working on.
        $question = $this->getEntity();

        $current_url = Url::fromRoute('<current>');
        $path = $current_url->getInternalPath();
        $path_args = explode('/', $path);

        $casestudyId = $path_args[4]; // Get from URL
        if($question->isNew()){
            $type = (!empty($path_args[7]))?$path_args[7]:'question';
            $question->type = $type;

            // update case study elements field
            $casestudy = \Drupal::entityTypeManager()
                ->getStorage('casestudy')
                ->load($casestudyId);
            $originalElements = $casestudy->getElements();
            $originalElements[$question->id()] = ['weight' => 0];
            $casestudy->setElements($originalElements);
            $casestudy->save();

            //echo 'xx';
            //print_r($casestudy);
            //exit;
        }

        $question->casestudy = $casestudyId;
        $desc = $question->get('description');
        $question->set('description',trim($desc['value']));

        $question->question_page_html = $question->question_page_html['value'];
        $question->result_page_html = $question->result_page_html['value'];

        // Drupal already populated the form values in the entity object. Each
        // form field was saved as a public variable in the entity class. PHP
        // allows Drupal to do this even if the method is not defined ahead of
        // time.
        $status = $question->save();

        // Grab the URL of the new entity. We'll use it in the message.
        //$url = $robot->urlInfo();
        //print_r($url);
        //$url = $robot->toUrl('edit-form');
        //print_r($url);
       // exit;
        // Create an edit link.
        //$edit_link = Link::fromTextAndUrl($this->t('Edit'), $url)->toString();

        if ($status == SAVED_UPDATED) {
            // If we edited an existing entity...
            drupal_set_message($this->t('Question %label has been updated.', ['%label' => $question->label()]));
            //$this->logger('contact')->notice('Robot %label has been updated.', ['%label' => $robot->label(), 'link' => $edit_link]);
        }
        else {
            // If we created a new entity...
            drupal_set_message($this->t('Question %label has been added.', ['%label' => $question->label()]));
            //$this->logger('contact')->notice('Robot %label has been added.', ['%label' => $robot->label(), 'link' => $edit_link]);
        }

        // Redirect the user back to the listing route after the save operation.

        $url = Url::fromRoute('entity.casestudy.settings_form', ['casestudy' => $casestudyId]);
        $form_state->setRedirectUrl($url);
        //$form_state->setRedirect('entity.casestudy.settings', ['casestudy' => $robot->id()]);
    }



}
