<?php

namespace Drupal\casestudy;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Form\WebformDialogFormTrait;
use Drupal\webform\Plugin\Field\FieldType\WebformEntityReferenceItem;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Plugin\WebformHandlerInterface;

use Drupal\casestudy\Utility\CasestudyArrayHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\casestudy\Utility\GeneralHelper;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;

/**
 * Provides a casestudy form to collect and edit submissions.
 */
class CasestudySubmissionForm extends ContentEntityForm {

  use WebformDialogFormTrait;

  /**
   * Denote wizard page should be disabled.
   *
   * @var string
   */
  const DISABLE_PAGES = 'disable_pages';

  /**
   * Denote form is being submitted via API, which trigger validation.
   *
   * @var string
   */
  const API_SUBMISSION = 'api_submission';

  /**
   * Determines how a webform should displayed and or processed.
   *
   * @var string
   */
  protected $mode = NULL;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The webform element (plugin) manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * The webform submission storage.
   *
   * @var \Drupal\webform\CasestudySubmissionStorageInterface
   */
  protected $storage;

  /**
   * Webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * The webform third party settings manager.
   *
   * @var \Drupal\webform\WebformThirdPartySettingsManagerInterface
   */
  protected $thirdPartySettingsManager;

  /**
   * The webform message manager.
   *
   * @var \Drupal\webform\WebformMessageManagerInterface
   */
  protected $messageManager;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The webform settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * The webform submission.
   *
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  protected $entity;

  /**
   * The source entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $sourceEntity;

  /**
   * Constructs a CasestudySubmissionForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   */
  public function __construct(EntityManagerInterface $entity_manager, RendererInterface $renderer, AliasManagerInterface $alias_manager, PathValidatorInterface $path_validator) {
    parent::__construct($entity_manager);
    $this->renderer = $renderer;
    #$this->requestHandler = $request_handler;
    $this->aliasManager = $alias_manager;
    $this->pathValidator = $path_validator;
    #$this->elementManager = $element_manager;
    $this->storage = $this->entityManager->getStorage('webform_submission');
    #$this->thirdPartySettingsManager = $third_party_settings_manager;
    #$this->messageManager = $message_manager;
    #$this->tokenManager = $token_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('renderer'),
      $container->get('path.alias_manager'),
      $container->get('path.validator')
      //$container->get('webform.request'),
      //$container->get('plugin.manager.webform.element'),
      //$container->get('webform.third_party_settings_manager'),
      //$container->get('webform.message_manager'),
      //$container->get('webform.token_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    $form_id = $this->entity->getEntityTypeId();
    if ($this->entity->getEntityType()->hasKey('bundle')) {
      $form_id .= '_' . $this->entity->bundle();
    }
    if ($source_entity = $this->entity->getSourceEntity()) {
      $form_id .= '_' . $source_entity->getEntityTypeId() . '_' . $source_entity->id();
    }
    if ($this->operation != 'default') {
      $form_id .= $this->operation;
    }
    return $form_id . '_form';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL) {
    $this->mode = $mode;

    /* @var $casestudy_submission \Drupal\casestudy\CasestudySubmissionInterface */
    $casestudy_submission = $this->getEntity();
    $casestudy = $this->getCasestudy();

    // Add this webform and the webform settings to the cache tags.
    $form['#cache']['tags'][] = 'config:webform.settings';

    // This submission webform is based on the current URL, and hence it depends
    // on the 'url' cache context.
    $form['#cache']['contexts'][] = 'url';

    // All anonymous submissions are tracked in the $_SESSION.
    // @see \Drupal\webform\WebformSubmissionStorage::setAnonymousSubmission
    if ($this->currentUser()->isAnonymous()) {
      $form['#cache']['contexts'][] = 'session';
    }

    // Add the webform as a cacheable dependency.
    $this->renderer->addCacheableDependency($form, $casestudy);

    // Display status messages.
    //$this->displayMessages($form, $form_state);

    // Build the webform.
    $form = parent::buildForm($form, $form_state);

    // Alter webform via webform handler.
    //$this->getCasestudy()->invokeHandlers('alterForm', $form, $form_state, $casestudy_submission);

    // Call custom webform alter hook.
    $form_id = $this->getFormId();
    //$this->thirdPartySettingsManager->alter('webform_submission_form', $form, $form_state, $form_id);

    return $this->buildAjaxForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    // Add a reference to the webform's id to the $form render array.
    $form['#casestudy_id'] = $this->getCasestudy()->id();

    // Check for a custom webform, track it, and return it.
//    if ($custom_form = $this->getCustomForm($form, $form_state)) {
//      $custom_form['#custom_form'] = TRUE;
//      return $custom_form;
//    }

    /* @var $casestudy_submission \Drupal\casestudy\CasestudySubmissionInterface */
    $casestudy_submission = $this->getEntity();
    $casestudy = $this->getCasestudy();

      /* Elements */

    $form = parent::form($form, $form_state);
      // Get webform elements.
      $elements = $casestudy->getElements();
      if(empty($elements)){
          drupal_set_message("This casestudy has no element. Please add Questions or HTML Page from the settings page.", 'warning');
          return $form;
      }

    /* Data */

    $data = $casestudy_submission->getData();



    $current_page = $this->getCurrentPage($form, $form_state);
    //exit;
    //Set Start Data and Set Cookie for Future Reference;
    $getCookieData = GeneralHelper::decryptCookieData($casestudy->getCookieName());
//    print_r($getCookieData);
//    exit;
    $submission_id = !empty($getCookieData['submission_id'])?$getCookieData['submission_id']:$casestudy_submission->id();
    if(empty($getCookieData['start_id'])){
        $start_id = $this->saveStartData($submission_id,$current_page);
    } else {
        $start_id = $getCookieData['start_id'];
    }
    $cookie_data = [
        'start_id' => $start_id,
        'submission_id' => $submission_id,
        'current_page' => $current_page
    ];
    //print_r($cookie_data);
    setcookie($casestudy->getCookieName(), GeneralHelper::encryptData($cookie_data), time() + 3600 * 24 * 180, "/");

    // Prepare Intro Page
    if($current_page == 'intro_page'){
        $node = Node::load($casestudy->gallery);
        $html = '';
        if(!empty($node)){
            $a =  \Drupal::service('renderer')->render(node_view($node));
            $html = $a;
        }
        $html .= $this->t($casestudy->getDescription());
        $form['elements']['intro_page'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class'=>'casestudy-question-html'],
            '#value' => $html,
            '#access' => true
        ];
    }

    // Prepare Graph for Previous Question
    if($current_page != 'intro_page' && $current_page != 'final_page'){
        if($casestudy->prefix == 'quiz'){
            $this->prepareQuizGraphChart($form, $form_state,$submission_id);
        } else {
            $this->prepareGraphChart($form, $form_state,$submission_id);
        }
    }


      // Prepare casestudy elements.
    $this->prepareElements($elements, $form, $form_state, $submission_id);

    // Prepare casestudy elements.
    if($casestudy->prefix == 'quiz'){
        if($current_page != 'final_page') {
            $this->prepareProgressBar($elements, $form, $form_state);
        }
    }

    if($current_page == 'final_page') {
        if($casestudy->prefix == 'quiz'){

            $this->prepareQuizFinalResultPage($elements, $form, $form_state, $submission_id);

        } else {

            $this->prepareFinalResultPage($elements, $form, $form_state, $submission_id);
        }
      $this->updateCasestudyStartStatus($submission_id, $current_page);
    }

    $this->saveCurrentPageVisitedData($elements, $form, $form_state,$submission_id,$start_id);




      // Append elements to the webform.
      //$form['elements'] = $elements;



    // Populate webform elements with webform submission data.
//    $this->populateElements($elements, $data);
//
//    // Prepare webform elements.
//    $this->prepareElements($elements, $form, $form_state);

    // Add wizard progress tracker to the webform.

//
//    if ($current_page && $this->getWebformSetting('wizard_progress_bar') || $this->getWebformSetting('wizard_progress_pages') || $this->getWebformSetting('wizard_progress_percentage')) {
//      $form['progress'] = [
//        '#theme' => 'webform_progress',
//        '#webform' => $this->getWebform(),
//        '#current_page' => $current_page,
//        '#weight' => -20,
//      ];
//    }
//
//    // Append elements to the webform.
//    $form['elements'] = $elements;
//
    // Pages: Set current wizard or preview page.
//    $this->displayCurrentPage($form, $form_state);
//    print_r($form['elements']);
//    exit;
//
//    /* Webform  */
//
//    // Move all $elements properties to the $form.
//    $this->setFormPropertiesFromElements($form, $elements);
//
//    // Default: Add CSS and JS.
//    // @see https://www.drupal.org/node/2274843#inline
//    $form['#attached']['library'][] = 'webform/webform.form';
//
//    // Assets: Add custom shared and webform specific CSS and JS.
//    // @see webform_library_info_build()
//    $assets = $webform->getAssets();
//    foreach ($assets as $type => $value) {
//      if ($value) {
//        $form['#attached']['library'][] = 'webform/webform.' . $type. '.' . $webform->id() ;
//      }
//    }
//
//    // Attach disable back button.
//    if ($this->getWebformSetting('form_disable_back')) {
//      $form['#attached']['library'][] = 'webform/webform.form.disable_back';
//    }
//
//    // Unsaved: Add unsaved message.
//    if ($this->getWebformSetting('form_unsaved')) {
//      $form['#attributes']['class'][] = 'js-webform-unsaved';
//      $form['#attached']['library'][] = 'webform/webform.form.unsaved';
//      $current_page = $this->getCurrentPage($form, $form_state);
//      if ($current_page && ($current_page != $this->getFirstPage($form, $form_state))) {
//        $form['#attributes']['data-webform-unsaved'] = TRUE;
//      }
//    }
//
//    // Submit once: Prevent duplicate submissions.
//    if ($this->getWebformSetting('form_submit_once')) {
//      $form['#attributes']['class'][] = 'js-webform-submit-once';
//      $form['#attached']['library'][] = 'webform/webform.form.submit_once';
//    }
//
//    // Autocomplete: Add autocomplete=off attribute to form if autocompletion is
//    // disabled.
//    if ($this->getWebformSetting('form_disable_autocomplete')) {
//      $form['#attributes']['autocomplete'] = 'off';
//    }
//
//    // Novalidate: Add novalidate attribute to form if client side validation disabled.
//    if ($this->getWebformSetting('form_novalidate')) {
//      $form['#attributes']['novalidate'] = 'novalidate';
//    }
//
//    // Details toggle: Display collapse/expand all details link.
//    if ($this->getWebformSetting('form_details_toggle')) {
//      $form['#attributes']['class'][] = 'js-webform-details-toggle';
//      $form['#attributes']['class'][] = 'webform-details-toggle';
//      $form['#attached']['library'][] = 'webform/webform.element.details.toggle';
//    }
//
//    // Autofocus: Add autofocus class to webform.
//    if ($this->entity->isNew() && $this->getWebformSetting('form_autofocus')) {
//      $form['#attributes']['class'][] = 'js-webform-autofocus';
//    }
//
//    // Details save: Attach details element save open/close library.
//    // This ensures that the library will be loaded even if the webform is
//    // used as a block or a node.
//    if ($this->config('webform.settings')->get('ui.details_save')) {
//      $form['#attached']['library'][] = 'webform/webform.element.details.save';
//    }
//
//    // Pages: Disable webform auto submit on enter for wizard webform pages only.
//    if ($this->getPages($form, $form_state)) {
//      $form['#attributes']['class'][] = 'js-webform-disable-autosubmit';
//    }
//
//    // Add #after_build callbacks.
//    $form['#after_build'][] = '::afterBuild';


    return $form;
  }
    /*
   * prepare the final result page Data
   */
    public function prepareFinalResultPage(array $elements, array &$form, FormStateInterface $form_state, $submission_id)
    {
        $casestudy = $this->getCasestudy();
        $url = Url::fromRoute('entity.casestudy.pdf_download',['casestudy'=>$casestudy->id()]) ;

        $current_page = $this->getCurrentPage($form,$form_state);
        $form['#attached']['library'][] = 'casestudy/casestudy_finalstep';

        $form['elements']['print_button'] = [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => ['class' => ['btn-print','addthis_toolbox_item addthis_button_print at300b']],
            '#value' => $this->t('Print'),
            '#weight' => -1,
            '#access' => true
        ];
        $form['elements']['pdf_button'] = [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => ['class' => ['case-study-save-pdf btn-pdf'], 'href'=> '/'.$url->getInternalPath()],
            '#value' => $this->t('Save PDF'),
            '#weight' => -1,
            '#access' => true
        ];

        $form['elements']['final_page_title'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => ['casestudy-result-page-title']],
            '#value' => $casestudy->getFinalPageTitle(),
            '#weight' => -1,
            '#access' => true
        ];
        $form['elements']['final_page_html'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class'=> ['casestudy-result-html']],
            '#value' => $casestudy->getFinalPageDescription(),
            '#weight' => -1,
            '#access' => true
        ];
        $form['elements']['final_page_summary_title'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class'=> ['casestudy-result-page-title final-page-summry-title']],
            '#value' => $casestudy->getFinalPageSummaryTitle(),
            '#weight' => -1,
            '#access' => true
        ];

        foreach ($elements as $element_key => $element){
            $elementObj = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($element_key);
            $access = true;

            if($elementObj->getType() == 'question'){

                $questionOptions = [];
                // Set the selected option from submission data if any
                $connection = Database::getConnection();
                $result = $connection->select('casestudy_submission_data', 'sd')
                    ->fields('sd', ['name','value'])
                    ->condition('sd.name', $element_key)
                    ->condition('sd.sid', $submission_id)
                    ->execute()->fetch();

                $selectedOption = !empty($result->value)?$result->value: '';
                // echo $selectedOption;exit;
                //echo $elementObj->getAnswer();
                $text = ['answer_a' => '','answer_b' => '','answer_c' => '','answer_d' => ''];
                $text[$elementObj->getCorrectAnswer()] = '( Correct Answer )';
                $text[$selectedOption] = '( Your Selection )';
                if($elementObj->getCorrectAnswer() == $selectedOption){
                    $text[$selectedOption] = '( Your Selection, Correct Answer )';
                }


                $elementObj->getAnswerA()?$questionOptions['answer_a'] = 'a. '.$elementObj->getAnswerA().' <strong>'.$text['answer_a'].'</strong>':'';
                $elementObj->getAnswerB()?$questionOptions['answer_b'] = 'b. '.$elementObj->getAnswerB().' <strong>'.$text['answer_b'].'</strong>':'';
                $elementObj->getAnswerC()?$questionOptions['answer_c'] = 'c. '.$elementObj->getAnswerC().' <strong>'.$text['answer_c'].'</strong>':'';
                $elementObj->getAnswerD()?$questionOptions['answer_d'] = 'd. '.$elementObj->getAnswerD().' <strong>'.$text['answer_d'].'</strong>':'';

                $form['elements'][$element_key.'_title'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'h3',
                    '#value' => '<span class="casestudy-question-start">Q: </span>'.$elementObj->label().'<span title="This field is required." class="casestudy-form-required">*</span>',
                    '#access' => $access
                ];
                $form['elements'][$element_key.'_description'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#value' => $this->t($elementObj->getDescription()),
                    '#access' => $access
                ];

                $form['elements'][$element_key] = [
                    '#type' => 'radios',
                    '#title' => '',
                    '#options' => $questionOptions,
                    '#default_value' => $selectedOption,
                    '#required' => TRUE,
                    '#disabled' => TRUE,
                    '#access' => $access
                ];
            } 
        }
    }

    /*
   * prepare the final result page Data for quiz
   */
    public function prepareQuizFinalResultPage(array $elements, array &$form, FormStateInterface $form_state, $submission_id)
    {
        $casestudy = $this->getCasestudy();
        $correct_answer = 0;
        $total_answer = 0;
        foreach ($elements as $element_key => $element) {
            $elementObj = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($element_key);
            if ($elementObj->getType() == 'question') {
                $total_answer++;
                // Set the selected option from submission data if any
                $connection = Database::getConnection();
                $result = $connection->select('casestudy_submission_data', 'sd')
                    ->fields('sd', ['name', 'value'])
                    ->condition('sd.name', $element_key)
                    ->condition('sd.sid', $submission_id)
                    ->execute()->fetch();
                if($result->value == $elementObj->getCorrectAnswer()){
                    $correct_answer++;
                }
            }
        }

        $cid = '';
        $case_study_ID = '';

       
        $text = 'I answered '.$correct_answer.' out of the '.$total_answer.' questions correctly. Take this Show You Know quiz and find out!';
        $fb_url = 'http://facebook.com/sharer.php?u='.urlencode('http://'. $_SERVER['SERVER_NAME']. '/casestudy/'.$casestudy->id().'/share-result/'.$submission_id);
        $t_url = 'https://twitter.com/intent/tweet?text='.urlencode($text).'&url='.urlencode('http://'. $_SERVER['SERVER_NAME']. '/casestudy/'.$casestudy->id().'/share-result/'.$submission_id);
        $in_url = 'https://www.linkedin.com/shareArticle?mini=true&url='.urlencode('http://'. $_SERVER['SERVER_NAME']. '/casestudy/'.$casestudy->id().'/share-result/'.$submission_id).'&ro=false&summary='.urlencode($text);

        $data['correct_answer'] = $correct_answer;
        $data['total_answer'] = $total_answer;
        $data['fb_url'] = $fb_url;
        $data['t_url'] = $t_url;
        $data['in_url'] = $in_url;
        $data['restart_link'] = Url::fromRoute('entity.casestudy.restart',['casestudy'=>$casestudy->id()]);

        $form['elements']['quiz_final_result'] = [
            '#theme' => 'quiz_final_result',
            '#data' => $data
        ];
        $form['elements']['final_page_html'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class'=> ['casestudy-result-html']],
            '#value' => $casestudy->getFinalPageDescription(),
            '#access' => true
        ];
//        $form['elements']['quiz_final_result_summary'] = [
//            '#type' => 'html_tag',
//            '#value' => $html,
//        ];


    }

    /*
     * Save the Start Data
     */
    public function updateCasestudyStartStatus($submission_id, $current_page){

        $connection = Database::getConnection();
        $connection->update('casestudy_start')->fields(
            ['status' => 1]
            )->condition('casestudy_id', $this->getCasestudy()->id(), '=')
            ->condition('sid', $submission_id, '=')
            ->execute();
    }
  /*
   * Save the Start Data
   */
    public function saveStartData($submission_id, $current_page){

        $connection = Database::getConnection();
        $casestudy = $this->getCasestudy();
        //if($current_page == 'intro_page'){ // Set Start Data
            $start = $connection->select('casestudy_start', 'sd')
                ->fields('sd', ['id'])
                ->condition('sd.casestudy_id', $casestudy->id())
                ->condition('sd.sid', $submission_id)
                ->execute();
            $start->allowRowCount = TRUE;

            if($start->rowCount() > 0){
                $res = $start->fetch();
                $start_id = $res->id;
            } else {
                $row = [
                    'sid' => $submission_id,
                    'casestudy_id' => $casestudy->id(),
                    'user_ip' => '',
                    'date_start' => date('Y-m-d h:m:i'),
                    'status' => 0
                ];
                $query = $connection->insert('casestudy_start')
                    ->fields(['sid', 'casestudy_id', 'user_ip', 'date_start', 'status']);
                $query->values($row);
                $start_id = $query->execute();
            }


        //}
        return $start_id;
    }

    /*
   * Save the Visit Status Data
   */
    public function saveCurrentPageVisitedData(array $elements, array &$form, FormStateInterface $form_state, $submission_id,$start_id){
        $connection = Database::getConnection();
        $casestudy = $this->getCasestudy();
        $current_page = $this->getCurrentPage($form,$form_state);
        $start = $connection->select('casestudy_visit_status', 'sd')
            ->fields('sd', ['sid'])
            ->condition('sd.casestudy_id', $casestudy->id())
            ->condition('sd.sid', $submission_id)
            ->condition('sd.start_id', $start_id)
            ->condition('sd.element_id', $current_page)
            ->execute();
        $start->allowRowCount = TRUE;
        if($start->rowCount() == 0){
            $row = [
                'start_id' => $start_id,
                'sid' => $submission_id,
                'casestudy_id' => $casestudy->id(),
                'element_id' => $current_page,
                'visited' => 1,
                'submitted' => 0
            ];
            $query = $connection->insert('casestudy_visit_status')
                ->fields(['start_id', 'sid', 'casestudy_id', 'element_id', 'visited', 'submitted']);

            $query->values($row);

            $query->execute();
        }
        if(!empty($submission_id)){
            $connection->update('casestudy_start')->fields(
                ['sid' => $submission_id]
            )->condition('id', $start_id, '=')
                ->execute();
            $connection->update('casestudy_visit_status')->fields(
                ['sid' => $submission_id]
            )->condition('id', $start_id, '=')
                ->execute();

        }

    }

    /*
     * Create the Graph Chart Based on the Submitted Data
     *
     */
    public function prepareQuizGraphChart(array &$form, FormStateInterface $form_state, $submission_id)
    {
        $casestudy = $this->getCasestudy();
        $prev_page = $this->getPreviousPage($form, $form_state);
        //echo $prev_page;
        $connection = Database::getConnection();
        $result = $connection->select('casestudy_submission_data', 'sd')
            ->fields('sd', ['name','value'])
            ->condition('sd.casestudy_id', $casestudy->id())
            ->condition('sd.name', $prev_page)
            ->condition('sd.sid', $submission_id)
            ->execute();
        // Get all the results
        $submission = $result->fetch(\PDO::FETCH_OBJ);
        if($submission) {
            $question = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($prev_page);


            $custom_array = range('a', 'd');
            $headers = array();
            $colors = array();

            foreach ($custom_array as $answerkey) {
                $key = 'answer_' . $answerkey;
                $label_key = strtoupper($answerkey);
                if(!empty($question->getAnswer($key))) {
                    if ($submission->value == $key && $question->getCorrectAnswer() == $key) {
                        $headers[$answerkey]['value'] = $question->getAnswer($key);
                        $headers[$answerkey]['wrong_correct'] = 'correct-box your-choice';
                        $headers[$answerkey]['answer'] = 'Your Choice.';
                        $colors[] = '#5FCE9B';
                    } else if ($submission->value == $key) {
                        $headers[$answerkey]['value'] = $question->getAnswer($key);
                        $headers[$answerkey]['wrong_correct'] = 'wrong-box your-choice';
                        $headers[$answerkey]['answer'] = 'Your Choice.';
                        $colors[] = '#EBC95E';
                    } else if ($question->getCorrectAnswer() == $key) {
                        $headers[$answerkey]['value'] = $question->getAnswer($key);
                        $headers[$answerkey]['wrong_correct'] = 'correct-box';
                        $headers[$answerkey]['answer'] = 'Correct Choice.';
                        $colors[] = '#5FCE9B';
                    } else {
                        $headers[$answerkey]['value'] = $question->getAnswer($key);
                        $headers[$answerkey]['wrong_correct'] = 'wrong-box other-choice';
                        $colors[] = in_array('#D74A7F', $colors) ? '#E87352' : '#D74A7F';
                    }
                }
            }


        }

        $total_submission_res = $connection->select('casestudy_submission_data', 'sd')
            ->fields('sd', ['sid'])
            ->condition('sd.casestudy_id', $casestudy->id())
            ->condition('sd.name', $prev_page)
            ->execute();
        $total_submission_res->allowRowCount = TRUE;
        $total_submission = $total_submission_res->rowCount();


        $rows =[];


        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $total_this_answer_submission_res = $connection->select('casestudy_submission_data', 'sd')
                    ->fields('sd', ['sid'])
                    ->condition('sd.casestudy_id', $casestudy->id())
                    ->condition('sd.name', $prev_page)
                    ->condition('sd.value', 'answer_'.$key)
                    ->execute();
                $total_this_answer_submission_res->allowRowCount = TRUE;
                $total_this_answer_submission = $total_this_answer_submission_res->rowCount();

                $percentage = number_format(( $total_this_answer_submission / $total_submission), 2, '.', '');
                $rows[] = array('class' => $value['wrong_correct'], 'answer' => $value['answer'], 'value'=>$value['value'], 'percentage' => (float) $percentage*100);
            }
            unset($total_answer);
        }
        if(!empty($rows)){
            $form['elements']['chart'] = [
                '#theme' => 'quiz_result_chart',
                '#rows' => $rows
            ];
            $form['elements']['result_html'] = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => ['class' => 'casestudy-result-html'],
                '#value' => $this->t($question->getResultHTML()),
                '#access' => true
            ];
        }
    }
    /*
     * Create the Graph Chart Based on the Submitted Data
     *
     */
    public function prepareGraphChart(array &$form, FormStateInterface $form_state, $submission_id){
        $casestudy = $this->getCasestudy();
        $prev_page = $this->getPreviousPage($form, $form_state);
        //echo $prev_page;
        $connection = Database::getConnection();
        $result = $connection->select('casestudy_submission_data', 'sd')
            ->fields('sd', ['name','value'])
            ->condition('sd.casestudy_id', $casestudy->id())
            ->condition('sd.name', $prev_page)
            ->condition('sd.sid', $submission_id)
            ->execute();
        // Get all the results
        $submission = $result->fetch(\PDO::FETCH_OBJ);
        if($submission){
            $question = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($prev_page);
            $form['elements']['result_html'] = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => ['class'=>'casestudy-result-html'],
                '#value' => $this->t($question->getResultHTML()),
                '#access' => true
            ];


            $custom_array = range('a', 'd');
            $headers = array();
            $colors = array();
            $rows = [];
            foreach ($custom_array as $answerkey) {
                $label_key = strtoupper($answerkey);
                $key = 'answer_'.$answerkey;
                if($submission->value == $key && $question->getCorrectAnswer() == $key){
                    $headers[$answerkey]['value'] = $question->getAnswer($key);
                    $headers[$answerkey]['label'] = "Choice {$label_key} (Your Selection, Correct Answer)";
                    $colors[] = '#5FCE9B';
                } else if($submission->value == $key){
                    $headers[$answerkey]['value'] = $question->getAnswer($key);
                    $headers[$answerkey]['label'] = "Choice {$label_key} (Your Selection)";
                    $colors[] = '#EBC95E';
                } else if($question->getCorrectAnswer() == $key){
                    $headers[$answerkey]['value'] = $question->getAnswer($key);
                    $headers[$answerkey]['label'] = "Choice {$label_key} (Correct Answer)";
                    $colors[] = '#5FCE9B';
                } else {
                    $headers[$answerkey]['value'] = $question->getAnswer($key);
                    $headers[$answerkey]['label'] = "Choice {$label_key} ";
                    $colors[] = in_array('#D74A7F', $colors) ? '#E87352' : '#D74A7F';
                }
            }

            // Get Total Submission of this Question
            $total_submission_res = $connection->select('casestudy_submission_data', 'sd')
                ->fields('sd', ['sid'])
                ->condition('sd.casestudy_id', $casestudy->id())
                ->condition('sd.name', $prev_page)
                ->execute();
            $total_submission_res->allowRowCount = TRUE;
            $total_submission = $total_submission_res->rowCount();
            //print_r($total_submission_res);
            //$total_submission = count($total_submission_res);
            //echo $total_submission;

            if (!empty($headers)) {
                foreach ($headers as $key => $value) {
                    // Get Total Submission for this Answer option
                    $total_this_answer_submission_res = $connection->select('casestudy_submission_data', 'sd')
                        ->fields('sd', ['sid'])
                        ->condition('sd.casestudy_id', $casestudy->id())
                        ->condition('sd.name', $prev_page)
                        ->condition('sd.value', 'answer_'.$key)
                        ->execute();
                    $total_this_answer_submission_res->allowRowCount = TRUE;
                    $total_this_answer_submission = $total_this_answer_submission_res->rowCount();

                    //$total_this_answer_submission = count($total_this_answer_submission_res);
                    //echo $total_this_answer_submission;

                    $debug_data[$key] = $total_this_answer_submission . "===" . $total_submission;
                    $percentage = number_format(( $total_this_answer_submission / $total_submission), 2, '.', '');
                    if ($key == 'a') {
                        $rows[] = array($value['label'], $value['value'], (float) $percentage, 0, 0, 0);
                    }

                    if ($key == 'b') {
                        $rows[] = array($value['label'], $value['value'], 0, (float) $percentage, 0, 0);
                    }

                    if ($key == 'c') {
                        $rows[] = array($value['label'], $value['value'], 0, 0, (float) $percentage, 0);
                    }

                    if ($key == 'd') {
                        $rows[] = array($value['label'], $value['value'], 0, 0, 0, (float) $percentage);
                    }
                }

                unset($total_answer);
            }

            //print_r($headers);
            //print_r($rows);
            $chart = [
                'colors' => $colors,
                'rows' => $rows,
                'debug' => $debug_data
            ];
            $form['#attached']['drupalSettings']['chart'] = $chart;
            $form['elements']['chart'] = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => ['id'=>'gchart','style'=>"height:240px"],
                '#value' => '',
                '#access' => true
            ];
        }
    }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $casestudy = $this->getCasestudy();
    /** @var \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission */
    $casestudy_submission = $this->getEntity();

    // Make sure the uri and remote addr are set correctly because
    // Ajax requests can cause these values to be reset.
    if ($casestudy_submission->isNew()) {
      $uri = preg_replace('#^' . base_path() . '#', '/', $this->getRequest()->getRequestUri());
      // Remove Ajax query string parameters.
      $uri = preg_replace('/(ajax_form=1|_wrapper_format=drupal_ajax)(&|$)/', '', $uri);
      // Remove empty query string.
      $uri = preg_replace('/\?$/', '', $uri);
      $remote_addr = $this->getRequest()->getClientIp();
      $casestudy_submission->set('uri', $uri);
      $casestudy_submission->set('remote_addr', $remote_addr);
    }

    // Block users from submitting templates that they can't update.
//    if ($casestudy->isTemplate() && !$casestudy->access('update')) {
//      return;
//    }

    // Save and log webform submission.
    $casestudy_submission->save();

    // Check limits and invalidate cached and rebuild.
//    if ($this->checkTotalLimit() || $this->checkUserLimit()) {
//      Cache::invalidateTags(['webform:' . $this->getWebform()->id()]);
//      $form_state->setRebuild();
//    }
  }

    /****************************************************************************/
    // Wizard page functions
    /****************************************************************************/

    /**
     * Get visible wizard pages.
     *
     * Note: The array of pages is stored in the webform's state so that it can be
     * altered using hook_form_alter() and #validate callbacks.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   Array of visible wizard pages.
     */
    protected function getPages(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('pages') === NULL) {
            $pages = $this->getCasestudy()->getPages();
            foreach ($pages as &$page) {
                $page['#access'] = TRUE;
            }
            $form_state->set('pages', $pages);
        }

        // Get pages and check #access.
        $pages = $form_state->get('pages');
        foreach ($pages as $page_key => $page) {
            if ($page['#access'] === FALSE) {
                unset($pages[$page_key]);
            }
        }

        return $pages;
    }

    /**
     * Get the current page's key.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return string
     *   The current page's key.
     */
    protected function getCurrentPage(array &$form, FormStateInterface $form_state) {
        if ($form_state->get('current_page') === NULL) {


            $pages = $this->getCasestudy()->getPages();
            $cookie_name = $this->getCasestudy()->getCookieName();
            $data = GeneralHelper::decryptCookieData($cookie_name);
            if($form_state->isSubmitted() == false && !empty($data['current_page'])){
                $form_state->set('current_page', $data['current_page']);

            } else {

                if (empty($pages)) {
                    $form_state->set('current_page', '');
                } else {
                    $current_page = $this->entity->getCurrentPage();
                    if ($current_page && isset($pages[$current_page]) && $this->draftEnabled()) {
                        $form_state->set('current_page', $current_page);
                    } else {
                        $form_state->set('current_page', CasestudyArrayHelper::getFirstKey($pages));
                    }
                }
            }
        }
        return $form_state->get('current_page');
    }

    /**
     * Get first page's key.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return null|string
     *   The first page's key.
     */
    protected function getFirstPage(array &$form, FormStateInterface $form_state) {
        $pages = $this->getPages($form, $form_state);
        return CasestudyArrayHelper::getFirstKey($pages);
    }

    /**
     * Get last page's key.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return null|string
     *   The last page's key.
     */
    protected function getLastPage(array &$form, FormStateInterface $form_state) {
        $pages = $this->getPages($form, $form_state);
        return CasestudyArrayHelper::getLastKey($pages);
    }

    /**
     * Get next page's key.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return null|string
     *   The next page's key. NULL if there is no next page.
     */
    protected function getNextPage(array &$form, FormStateInterface $form_state) {
        $pages = $this->getPages($form, $form_state);
        $current_page = $this->getCurrentPage($form, $form_state);
        return CasestudyArrayHelper::getNextKey($pages, $current_page);
    }

    /**
     * Get previous page's key.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return null|string
     *   The previous page's key. NULL if there is no previous page.
     */
    protected function getPreviousPage(array &$form, FormStateInterface $form_state) {
        $pages = $this->getPages($form, $form_state);
        $current_page = $this->getCurrentPage($form, $form_state);
        return CasestudyArrayHelper::getPreviousKey($pages, $current_page);
    }

    /**
     * Set webform wizard current page.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function displayCurrentPage(array &$form, FormStateInterface $form_state) {
        $current_page = $this->getCurrentPage($form, $form_state);

        if ($current_page == 'casestudy_preview') {
            // If reqquired preview in future
        }
        else {
            // Get all pages so that we can also hide skipped pages.
            $pages = $this->getCasestudy()->getElements();
            foreach ($pages as $page_key => $page) {
                if (isset($form['elements'][$page_key])) {
                    if ($page_key != $current_page) {
                        $form['elements'][$page_key]['#access'] = FALSE;
                        $this->hideElements($form['elements'][$page_key]);
                    }
                    else {
                        $form['elements'][$page_key]['#type'] = 'container';
                    }
                }
            }
        }
    }


    /**
     * Get the webform submission's webform.
     *
     * @return \Drupal\casestudy\Entity\Casestudy
     *   A webform.
     */
    protected function getCasestudy() {
        /** @var \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission */
        $casestudy_submission = $this->getEntity();
        return $casestudy_submission->getCasestudy();
    }

    /**
     * Prepopulate element data.
     *
     * @param array $data
     *   An array of default.
     */
    protected function prepopulateData(array &$data) {
        if ($this->getWebformSetting('form_prepopulate')) {
            $data += $this->getRequest()->query->all();
        }
    }


    /****************************************************************************/
    // Elements functions
    /****************************************************************************/

    /**
     * Hide webform elements by settings their #access to FALSE.
     *
     * @param array $elements
     *   An render array representing elements.
     */
    protected function hideElements(array &$elements) {
        foreach ($elements as $key => &$element) {
            if (Element::property($key) || !is_array($element)) {
                continue;
            }

            // Set #access to FALSE which will suppresses webform #required validation.
            $element['#access'] = FALSE;

            // ISSUE: Hidden elements still need to call #element_validate because
            // certain elements, including managed_file, checkboxes, password_confirm,
            // etc..., will also massage the submitted values via #element_validate.
            //
            // SOLUTION: Call #element_validate for all hidden elements but suppresses
            // #element_validate errors.
            //
            // Set hidden element #after_build handler.
            $element['#after_build'][] = [get_class($this), 'hiddenElementAfterBuild'];

            $this->hideElements($element);
        }
    }

    /**
     * Webform element #after_build callback: Wrap #element_validate so that we suppress element validation errors.
     */
    public static function hiddenElementAfterBuild(array $element, FormStateInterface $form_state) {
        if (!empty($element['#element_validate'])) {
            $element['#_element_validate'] = $element['#element_validate'];
            $element['#element_validate'] = [[get_called_class(), 'hiddenElementValidate']];
        }
        return $element;
    }

    /**
     * Webform element #element_validate callback: Execute #element_validate and suppress errors.
     */
    public static function hiddenElementValidate(array $element, FormStateInterface $form_state) {
        // Create a temp webform state that will capture and suppress all element
        // validation errors.
        $temp_form_state = clone $form_state;
        $temp_form_state->setLimitValidationErrors([]);

        // @see \Drupal\Core\Form\FormValidator::doValidateForm
        foreach ($element['#_element_validate'] as $callback) {
            $complete_form = &$form_state->getCompleteForm();
            call_user_func_array($form_state->prepareCallback($callback), [&$element, &$temp_form_state, &$complete_form]);
        }

        // Get the temp webform state's values.
        $form_state->setValues($temp_form_state->getValues());
    }

    /**
     * Prepare casestudy elements.
     *
     * @param array $elements
     *   An render array representing elements.
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function prepareProgressBar(array &$elements, array &$form, FormStateInterface $form_state) {
        $current_page = $this->getCurrentPage($form,$form_state);
        $form['progress'] = [
            '#theme' => 'casestudy_progress_tracker',
            '#casestudy' => $this->getCasestudy(),
            '#current_page' => $current_page,
            '#weight' => 0,
        ];
    }
        //print_r($elements);
    /**
     * Prepare casestudy elements.
     *
     * @param array $elements
     *   An render array representing elements.
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function prepareElements(array &$elements, array &$form, FormStateInterface $form_state, $submission_id) {
        //print_r($elements);
        $current_page = $this->getCurrentPage($form, $form_state);
        $casestudy = $this->getCasestudy();
        $pages = $casestudy->getPages();
        $page_keys = array_keys($pages);
        $page_indexes = array_flip($page_keys);
        //print_r($page_indexes);
        $current_index = $page_indexes[$current_page];

        // Add Class for Form Step
        $form['#attributes']['class'][] = 'form-step-'.($current_index+1).' '. $current_page;

        foreach ($elements as $element_key => $element){
            $elementObj = \Drupal::entityTypeManager()
                ->getStorage('question')
                ->load($element_key);
            $access = false;
            if($current_page == $element_key){
                $access = true;
            }
            if(empty($elementObj)){
                break;
            }
            if($elementObj->getType() == 'question'){

                $questionOptions = [];
                // Set the selected option from submission data if any
                $connection = Database::getConnection();
                $result = $connection->select('casestudy_submission_data', 'sd')
                    ->fields('sd', ['name','value'])
                    ->condition('sd.name', $current_page)
                    ->condition('sd.sid', $submission_id)
                    ->execute()->fetch();
                $selectedOption = !empty($result->value)?$result->value: '';
               // echo $selectedOption;exit;
                //echo $elementObj->getAnswer();
                $elementObj->getAnswerA()?$questionOptions['answer_a'] = 'a. '.$elementObj->getAnswerA():'';
                $elementObj->getAnswerB()?$questionOptions['answer_b'] = 'b. '.$elementObj->getAnswerB():'';
                $elementObj->getAnswerC()?$questionOptions['answer_c'] = 'c. '.$elementObj->getAnswerC():'';
                $elementObj->getAnswerD()?$questionOptions['answer_d'] = 'd. '.$elementObj->getAnswerD():'';

//                $form['elements']['question'] = array(
//                    '#type' => 'fieldset',
//                    '#title' => 'xxxxxx',
//                );

                $form['elements'][$element_key.'_html'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#attributes' => ['class'=>'casestudy-question-html'],
                    '#value' => $this->t($elementObj->getQuestionHTML()),
                    '#access' => $access
                ];

                if($casestudy->prefix == 'quiz'){
                    $q_label = 'Q'.($current_index+1);
                } else {
                    $q_label = 'Q';
                }

                $form['elements'][$element_key.'_title'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'h3',
                    '#prefix' => '<div class="question-container">',
                    '#value' => '<span class="casestudy-question-start">'.$q_label.': </span>'.$elementObj->label().'<span title="This field is required." class="casestudy-form-required">*</span>',
                    '#access' => $access
                ];
                $form['elements'][$element_key.'_description'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#value' => $this->t($elementObj->getDescription()),
                    '#access' => $access
                ];

                $form['elements'][$element_key] = [
                    '#type' => 'radios',
                    '#title' => '',
                    '#options' => $questionOptions,
                    '#default_value' => $selectedOption,
                    '#required' => TRUE,
                    '#suffix' => '</div>',
                    '#access' => $access
                ];
            } else if($elementObj->getType() == 'html_page'){

                $form['elements'][$element_key.'_title'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'h1',
                    '#value' => $this->t($elementObj->label()),
                    '#access' => $access
                ];
                $form['elements'][$element_key.'_description'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    '#value' => $this->t($elementObj->getDescription()),
                    '#access' => $access
                ];
            }
        }
        //print_r($form);
        //exit;
    }

    /**
     * {@inheritdoc}
     */
    protected function actions(array $form, FormStateInterface $form_state) {
        /* @var $casestudy_submission \Drupal\casestudy\CasestudySubmissionInterface */
        $casestudy_submission = $this->entity;
        $element = parent::actions($form, $form_state);
         // Remove the delete button from the casestudy submission casestudy.
        unset($element['delete']);

        // Mark the submit action as the primary action, when it appears.
        $element['submit']['#button_type'] = 'primary';
        $element['submit']['#attributes']['class'][] = 'webform-button--submit';
        $element['submit']['#weight'] = 10;

        // Customize the submit button's label for new submissions only.
        if ($casestudy_submission->isNew() || $casestudy_submission->isDraft()) {
            //$element['submit']['#value'] = $this->config('webform.settings')->get('settings.default_submit_button_label');
            $element['submit']['#value'] = 'Next Default';
        }

        // Add validate and complete handler to submit.
        //$element['submit']['#validate'][] = '::validateForm';
        //$element['submit']['#validate'][] = '::autosave';
        //$element['submit']['#validate'][] = '::complete';

        // Add confirm(ation) handler to submit button.
        $element['submit']['#submit'][] = '::confirmForm';

        $pages = $this->getCasestudy()->getPages();
        $current_page = $this->getCurrentPage($form, $form_state);
        if ($pages) {
            // Get current page element which can contain custom prev(ious) and next button
            // labels.
            $current_page_element = $this->getCasestudy()->getElement($current_page);

            $is_first_page = ($current_page == $this->getFirstPage($form, $form_state)) ? TRUE : FALSE;
            $is_last_page = (in_array($current_page, ['casestudy_intro', 'casestudy_final', $this->getLastPage($form, $form_state)])) ? TRUE : FALSE;

            // Only show that save button if this is the last page of the wizard or
            // on preview page or right before the optional preview.
            $element['submit']['#access'] = $is_last_page;

            if (!$is_first_page) {

//                    if (isset($current_page_element['#prev_button_label'])) {
//                        $previous_button_label = $current_page_element['#prev_button_label'];
//                        $previous_button_custom = TRUE;
//                    }
//                    else {
//                        $previous_button_label = $this->config('webform.settings')->get('settings.default_wizard_prev_button_label');
//                        $previous_button_custom = FALSE;
//                    }
                $previous_button_label = 'Previous';
                    $element['wizard_prev'] = [
                        '#type' => 'submit',
                        '#value' => $previous_button_label,
                        #'#webform_actions_button_custom' => $previous_button_custom,
                        '#validate' => ['::noValidate'],
                        '#submit' => ['::previous'],
                        '#attributes' => ['class' => ['webform-button--previous', 'js-webform-novalidate', 'webform-previous']],
                        '#weight' => 0,
                    ];

            }

            if (!$is_last_page) {
//                    if (isset($current_page_element['#next_button_label'])) {
//                        $next_button_label = $current_page_element['#next_button_label'];
//                        $next_button_custom = TRUE;
//                    }
//                    else {
//                        $next_button_label = $this->config('webform.settings')->get('settings.default_wizard_next_button_label');
//                        $next_button_custom = FALSE;
//                    }
                    $final_result_button = '';
                    if($current_page == 'intro_page'){
                        $next_button_label = 'Begin';
                    } else if($current_page == 'last_question_result'){
                        $next_button_label = 'View Final Result';
                        $final_result_button = 'final-result-button';
                    } else  {
                        $next_button_label = 'Next';
                    }
                    $element['wizard_next'] = [
                        '#type' => 'submit',
                        '#value' => $next_button_label,
                        #'#webform_actions_button_custom' => $next_button_custom,
                        '#validate' => ['::validateForm'],
                        '#submit' => ['::next'],
                        '#attributes' => ['class' => ['webform-button--next', 'webform-next', 'button-primary', $final_result_button]],
                        '#weight' => 1,
                    ];

            }
        }
        if($form_state->get('current_page') == 'final_page'){
            unset($element['submit']);
            if($this->getCasestudy()->prefix == 'quiz'){
                unset($element['wizard_next']);
                unset($element['wizard_prev']);
            }
            if(!empty($this->getCasestudy()->getFinalPageClaimButtonShow())){
                $element['claim_button'] = [
                    '#type' => 'html_tag',
                    '#tag' => 'a',
                    '#attributes' => ['class'=>['btn btn-primary btn-claim'],'href' =>$this->getCasestudy()->getFinalPageClaimUrl()],
                    '#value' => (!empty($this->getCasestudy()->getFinalPageClaimButtonText()))?$this->getCasestudy()->getFinalPageClaimButtonText():'Claim your Credit'
                ];
            }

        }
        return $element;
    }

    /**
     * Webform submission handler for the 'next' action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function next(array &$form, FormStateInterface $form_state) {
        if ($form_state->getErrors()) {
            return;
        }
        $form_state->set('current_page', $this->getNextPage($form, $form_state));
        $this->wizardSubmit($form, $form_state);
    }

    /**
     * Webform submission handler for the 'previous' action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function previous(array &$form, FormStateInterface $form_state) {
        $form_state->set('current_page', $this->getPreviousPage($form, $form_state));
        $this->wizardSubmit($form, $form_state);
    }

    /**
     * Casestudy submission handler for the wizard submit action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    protected function wizardSubmit(array &$form, FormStateInterface $form_state) {
//        if ($this->draftEnabled() && $this->getWebformSetting('draft_auto_save') && !$this->entity->isCompleted()) {
//            $form_state->setValue('in_draft', TRUE);
//
//            $this->submitForm($form, $form_state);
//            $this->save($form, $form_state);
//        }
//        else {
            $this->submitForm($form, $form_state);
            $this->save($form, $form_state);
//        }
//
        $this->rebuild($form, $form_state);
    }

    /**
     * Webform submission handler to autosave when there are validation errors.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function autosave(array &$form, FormStateInterface $form_state) {
        if ($form_state->hasAnyErrors()) {
            if ($this->draftEnabled() && $this->getWebformSetting('draft_auto_save') && !$this->entity->isCompleted()) {
                $form_state->setValue('in_draft', TRUE);

                $this->submitForm($form, $form_state);
                $this->save($form, $form_state);
                $this->rebuild($form, $form_state);
            }
        }
    }

    /**
     * Webform submission handler for the 'draft' action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function draft(array &$form, FormStateInterface $form_state) {
        $form_state->clearErrors();
        $form_state->setValue('in_draft', TRUE);
        $form_state->set('draft_saved', TRUE);
        $this->entity->validate();
    }

    /**
     * Webform submission handler for the 'complete' action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function complete(array &$form, FormStateInterface $form_state) {
        $form_state->setValue('in_draft', FALSE);
    }

    /**
     * Webform submission validation that does nothing but clear validation errors.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function noValidate(array &$form, FormStateInterface $form_state) {
        $form_state->clearErrors();
        $this->entity->validate();
    }

    /**
     * Webform submission handler for the 'rebuild' action.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function rebuild(array &$form, FormStateInterface $form_state) {
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

        // Validate webform via webform handler.
        //$this->getWebform()->invokeHandlers('validateForm', $form, $form_state, $this->entity);

        // Webform validate handlers (via form['#validate']) are not called when
        // #validate handlers are attached to the trigger element
        // (ie submit button), so we need to manually call $form['validate']
        // handlers to support the modules that use form['#validate'] like the
        // validators.module.
        // @see \Drupal\webform\WebformSubmissionForm::actions
        // @see \Drupal\Core\Form\FormBuilder::doBuildForm
        $trigger_element = $form_state->getTriggeringElement();
        if (isset($trigger_element['#validate'])) {
            $handlers = array_filter($form['#validate'], function ($callback) {
                // Remove ::validateForm to prevent a recursion.
                return (is_array($callback) || $callback != '::validateForm');
            });
            // @see \Drupal\Core\Form\FormValidator::executeValidateHandlers
            foreach ($handlers as $callback) {
                call_user_func_array($form_state->prepareCallback($callback), [&$form, &$form_state]);
            }
        }
    }
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        /* @var $casestudy_submission \Drupal\casestudy\CasestudySubmissionInterface */
        $casestudy_submission = $this->entity;
        $casestudy = $casestudy_submission->getCasestudy();

        // Get elements values from webform submission.
        $values = array_intersect_key(
            $form_state->getValues(),
            $casestudy->getElements()
        );
        // Serialize the values as YAML and merge existing data.
        $casestudy_submission->setData($values + $casestudy_submission->getData());

        parent::submitForm($form, $form_state);

        // Submit webform via webform handler.
        //$this->getCasestudy()->invokeHandlers('submitForm', $form, $form_state, $casestudy_submission);
    }


}
