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
class TabFormBase extends EntityForm {

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

        $form['id'] = [
            '#type' => 'machine_name',
            '#default_value' => $question->id(),
            '#machine_name' => [
                'exists' => '\Drupal\casestudy\Entity\Question::load',
                'source' => ['title'],
            ],
            '#maxlength' => 32,
            '#disabled' => (bool) $question->id(),
            '#required' => TRUE,
        ];
        $form['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Tab Title ( Will Appear in Tab)'),
            '#maxlength' => 255,
            '#default_value' => $question->label(),
            '#required' => TRUE,
            '#id' => 'label',
            '#attributes' => [
                'autofocus' => 'autofocus',
            ],
        ];

        $form['title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
            '#maxlength' => 255,
            '#default_value' => $question->title,
            '#required' => TRUE,
            '#id' => 'title',
            '#attributes' => [
                'autofocus' => 'autofocus',
            ],
        ];
        $form['description'] = [
            '#type' => 'text_format',
            '#format' => 'full_html',
            '#required' => TRUE,
            '#title' => $this->t('Description'),
            '#default_value' => $question->get('description'),
        ];

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
            // update case study elements field
            $casestudy = \Drupal::entityTypeManager()
                ->getStorage('casestudy')
                ->load($casestudyId);
            $originalTabs = $casestudy->getTabs();
            $originalTabs[$question->id()] = ['weight' => 0];
            $casestudy->setTabs($originalTabs);
            $casestudy->save();

            //echo 'xx';
            //print_r($casestudy);
            //exit;
        }

        $question->casestudy = $casestudyId;
        $desc = $question->get('description');
        $question->set('description',trim($desc['value']));

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
            drupal_set_message($this->t('Tab %label has been updated.', ['%label' => $question->label()]));
            //$this->logger('contact')->notice('Robot %label has been updated.', ['%label' => $robot->label(), 'link' => $edit_link]);
        }
        else {
            // If we created a new entity...
            drupal_set_message($this->t('Tab %label has been added.', ['%label' => $question->label()]));
            //$this->logger('contact')->notice('Robot %label has been added.', ['%label' => $robot->label(), 'link' => $edit_link]);
        }

        // Redirect the user back to the listing route after the save operation.

        $url = Url::fromRoute('entity.casestudy.tab_settings_form', ['casestudy' => $casestudyId]);
        $form_state->setRedirectUrl($url);
        //$form_state->setRedirect('entity.casestudy.settings', ['casestudy' => $robot->id()]);
    }



}
