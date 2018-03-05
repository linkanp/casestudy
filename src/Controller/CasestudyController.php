<?php

namespace Drupal\casestudy\Controller;

use Dompdf\Autoloader;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;

use Drupal\casestudy\Entity\Casestudy;
#use Drupal\webform\WebformRequestInterface;
use Drupal\casestudy\CasestudySubmissionInterface;
#use Drupal\webform\WebformTokenManagerInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

use Drupal\casestudy\Utility\GeneralHelper;
use Drupal\core\Url;
use Drupal\Core\Database\Database;


require_once DRUPAL_ROOT .'/libraries/dompdf/src/Autoloader.php';
Autoloader::register();

use Dompdf\Dompdf;



/**
 * Provides route responses for webform.
 */
class CasestudyController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * Constructs a WebformController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
    #$this->requestHandler = $request_handler;
    #$this->tokenManager = $token_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
      #$container->get('webform.request'),
      #$container->get('webform.token_manager')
    );
  }

    /**
     * Returns a casestudy to add a new submission to a casestudy.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     * @param \Drupal\casestudy\Entity\Casestudy $casestudy
     *   The casestudy this submission will be added to.
     *
     * @return array
     *   The casestudy submission webform.
     */
    public function redirectForm(Request $request, Casestudy $casestudy) {
        $getCookieData = GeneralHelper::decryptCookieData($casestudy->getCookieName());
        $current_url = Url::fromRoute('<current>');
        $path = $current_url->getInternalPath();
        $path_args = explode('/', $path);
        if(!empty($path_args[3])){
            $cookie_data = [
                'start_id' => $getCookieData['start_id'],
                'submission_id' => $getCookieData['submission_id'],
                'current_page' => $path_args[3]
            ];
        }
        //print_r($cookie_data);
        setcookie($casestudy->getCookieName(), GeneralHelper::encryptData($cookie_data), time() + 3600 * 24 * 180, "/");
        return $this->redirect('entity.casestudy.canonical',['casestudy'=>$casestudy->id()]);

    }

    /**
     * Returns a casestudy to add a new submission to a casestudy.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     * @param \Drupal\casestudy\Entity\Casestudy $casestudy
     *   The casestudy this submission will be added to.
     *
     * @return array
     *   The casestudy submission webform.
     */
    public function restartQuiz(Request $request, Casestudy $casestudy) {

        $cookie_name = $casestudy->getCookieName();
        unset($_COOKIE[$cookie_name]);
        setcookie($cookie_name, '', time() - 3600 * 24 * 180, "/");

        return $this->redirect('entity.casestudy.canonical',['casestudy'=>$casestudy->id()]);

    }

    /**
     * Returns a casestudy to add a new submission to a casestudy.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     * @param \Drupal\casestudy\Entity\Casestudy $casestudy
     *   The casestudy this submission will be added to.
     *
     * @return array
     *   The casestudy submission webform.
     */
    public function resutlShare(Request $request, Casestudy $casestudy, $sid) {
        $elements = $casestudy->getElements();
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
                    ->condition('sd.sid', $sid)
                    ->execute()->fetch();
                if($result->value == $elementObj->getCorrectAnswer()){
                    $correct_answer++;
                }
            }
        }

        $text = 'I answered '.$correct_answer.' out of the '.$total_answer.' questions correctly. Take this Show You Know quiz and find out!';
        $build = [];
        $build['#markup'] = $text;

        $element = array(
            '#tag' => 'meta',
            '#attributes' => array(
                'property' => 'og:description',
                'content' => $text,
            ),
        );
        $build['#attached']['html_head'][] = [$element, 'og:description'];
        $element = array(
            '#tag' => 'meta',
            '#attributes' => array(
                'property' => 'og:image',
                'content' => 'http://www.pharmacist.com/sites/all/modules/custom/casestudy/images/SYK_again_share.png',
            ),
        );
        $build['#attached']['html_head'][] = [$element, 'og:image'];

        // Twitter Card
        $element = array(
            '#tag' => 'meta',
            '#attributes' => array(
                'property' => 'twitter:card',
                'content' => 'summary',
            ),
        );
        $build['#attached']['html_head'][] = [$element, 'twitter:card'];
        $element = array(
            '#tag' => 'meta',
            '#attributes' => array(
                'property' => 'twitter:title',
                'content' => $casestudy->label(),
            ),
        );
        $build['#attached']['html_head'][] = [$element, 'twitter:title'];
        $element = array(
            '#tag' => 'meta',
            '#attributes' => array(
                'property' => 'twitter:description',
                'content' => $text,
            ),
        );
        $build['#attached']['html_head'][] = [$element, 'twitter:description'];
        return $build;
    }

    /**
     * Returns a casestudy to add a new submission to a casestudy.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request.
     * @param \Drupal\casestudy\Entity\Casestudy $casestudy
     *   The casestudy this submission will be added to.
     *
     * @return array
     *   The casestudy submission webform.
     */
    public function pdfDownload(Request $request, Casestudy $casestudy) {

        $elements = $casestudy->getElements();
        $getCookieData = GeneralHelper::decryptCookieData($casestudy->getCookieName());

        $elementHtml = '';
        foreach ($elements as $key => $element){
            $elementObj = $casestudy->getElement($key);

            if($elementObj->getType() == 'question'){

                $questionOptions = [];
                // Set the selected option from submission data if any
                $connection = Database::getConnection();

                $result = $connection->select('casestudy_submission_data', 'sd')
                    ->fields('sd', ['name','value'])
                    ->condition('sd.name', $key)
                    ->condition('sd.sid', $getCookieData['submission_id'])
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


                $elementHtml .= '<h3><span class="casestudy-question-start">Q: </span>'.$elementObj->label().'<span title="This field is required." class="casestudy-form-required">*</span></h3>';
                $elementHtml .= '<div class="case-study-question-description">'.$this->t($elementObj->getDescription()).'</div>';

                $optionsHtml = '';
                foreach ($questionOptions as $k => $v){
                    $selected = '';
                    if($k == $selectedOption){
                        $selected = 'checked="checked"';
                    }
                    $optionsHtml .= '<div class="form-item form-type-radio"><input type="radio" class="form-radio" value="' . $k . '" name="submitted[casestudy_question_' . $k . ']"  ' . $selected . '">';
                    $optionsHtml .= "<label class=\"option\">{$v}</label></div>";
                }
                $elementHtml .= $optionsHtml;


            }


        }


        $html = '<!DOCTYPE html>
<html>
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />  
      <link type="text/css" href="pdf.css" rel="stylesheet" />
      <title> Case Study</title>
     </head>
   <body>
      <div class="casestudy-pdf">
         
        
         <div class="casestudy-result-page-title">
          ' . $casestudy->getFinalPageTitle() . '
         </div>
         <div class="casestudy-result-html">
          ' . $casestudy->getFinalPageDescription() . '
         </div>
         <div class="casestudy-result-page-title">
          ' . $casestudy->getFinalPageSummaryTitle() . '
         </div>
         ' . $elementHtml . '
         
      </div>
     
   </body>
</html>';

//        echo $html;
//        exit;

        $dompdf = new Dompdf();


        $dompdf->loadHtml($html);
        $dompdf->setBasePath(drupal_get_path('module', 'casestudy') . '/css/');
        // (Optional) Setup the paper size and orientation
        //$dompdf->setPaper('A4', 'landscape');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream();

        exit;

    }
  /**
   * Returns a casestudy to add a new submission to a casestudy.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\casestudy\Entity\Casestudy $casestudy
   *   The casestudy this submission will be added to.
   *
   * @return array
   *   The casestudy submission webform.
   */
  public function addForm(Request $request, Casestudy $casestudy) {

//    return $casestudy->getSubmissionForm();

      // Generate the Tabs.

      $form = $casestudy->getSubmissionForm();


      $tab_head = '';
      $tab_content = '';
      $form_tab_header = '';
      $left_nav = false;
      $theme_name = 'quiz_display';
      if($casestudy->prefix != 'quiz'){
          $tabs = $casestudy->getTabs();
          foreach ($tabs as $tab_key => $tab_value) {
              $class = '';
              $tab = \Drupal::entityTypeManager()
                  ->getStorage('tab')
                  ->load($tab_key);
              $tab_head .= "<li class=\"{$class} castudytabs\"> <a title=\"{$tab->label}\" href=\"#casestudy-tab-{$tab_key}\">{$tab->label} </a></li>";
              $tab_content .= "<div id=\"casestudy-tab-{$tab_key}\"><h1 class=\"case-study-title\">".$tab->title."</h1>".$tab->get('description')."</div>";
          }
          $tabName = (!empty($casestudy->getTabName())?$casestudy->getTabName():'Case Study');
          $form_tab_header = '<li class="castudytabs castudytabs-intro"><a title="'.$casestudy->label().'" href="#casestudy-tab-'.$casestudy->id().'">'.$tabName.'</a></li>';

          $left_nav = $casestudy->getLeftNav();
          $theme_name = 'casestudy_display';
      }



      // Check if this is Intro Page


      if($form['elements']['intro_page']['#access'] == true){
          $left_nav = false;
      }
      //print_r($form['elements']);
      //print_r(array_keys($form));
      //exit;

      return [
          '#theme' => $theme_name,
          '#tabs_header' => $tab_head,
          '#tabs_content' => $tab_content,
          '#form_tab_header' => $form_tab_header,
          '#form' => $form,
          '#casestudy_id' => $casestudy->id(),
          '#casestudy' => $casestudy,
          '#left_nav' => $left_nav,
          '#casestudy_title' => $casestudy->label(),
          '#url_path' => 'http://' . $_SERVER['SERVER_NAME']. $_SERVER['REQUEST_URI']
      ];
  }

  /**
   * Returns a webform's CSS.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function css(Request $request, WebformInterface $webform) {
    $assets = $webform->getAssets();
    return new Response($assets['css'], 200, ['Content-Type' => 'text/css']);
  }

  /**
   * Returns a webform's JavaScript.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function javascript(Request $request, WebformInterface $webform) {
    $assets = $webform->getAssets();
    return new Response($assets['javascript'], 200, ['Content-Type' => 'text/javascript']);
  }

  /**
   * Returns a webform confirmation page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\webform\WebformInterface|null $webform
   *   A webform.
   * @param \Drupal\webform\WebformSubmissionInterface|null $webform_submission
   *   A webform submission.
   *
   * @return array
   *   A render array representing a webform confirmation page
   */
  public function confirmation(Request $request, WebformInterface $webform = NULL, WebformSubmissionInterface $webform_submission = NULL) {
    /** @var \Drupal\Core\Entity\EntityInterface $source_entity */
    if (!$webform) {
      list($webform, $source_entity) = $this->requestHandler->getWebformEntities();
    }
    else {
      $source_entity = $this->requestHandler->getCurrentSourceEntity('webform');
    }

    if ($token = $request->get('token')) {
      /** @var \Drupal\webform\CasestudySubmissionStorageInterface $webform_submission_storage */
      $webform_submission_storage = $this->entityTypeManager()->getStorage('webform_submission');
      if ($entities = $webform_submission_storage->loadByProperties(['token' => $token])) {
        $webform_submission = reset($entities);
      }
    }

    // Get title.
    $title = $webform->getSetting('confirmation_title') ?: (($source_entity) ? $source_entity->label() : $webform->label());

    // Replace tokens in title.
    $title = $this->tokenManager->replace($title, $webform_submission ?: $webform);

    $build = [
      '#title' => $title,
      '#theme' => 'webform_confirmation',
      '#webform' => $webform,
      '#source_entity' => $source_entity,
      '#webform_submission' => $webform_submission,
    ];

    // Add entities cacheable dependency.
    $this->renderer->addCacheableDependency($build, $webform);
    if ($webform_submission) {
      $this->renderer->addCacheableDependency($build, $webform_submission);
    }
    if ($source_entity) {
      $this->renderer->addCacheableDependency($build, $source_entity);
    }

    return $build;
  }

  /**
   * Returns a webform filter webform autocomplete matches.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param bool $templates
   *   If TRUE, limit autocomplete matches to webform templates.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function autocomplete(Request $request, $templates = FALSE) {
    $q = $request->query->get('q');

    $webform_storage = $this->entityTypeManager()->getStorage('webform');

    $query = $webform_storage->getQuery()
      ->condition('title', $q, 'CONTAINS')
      ->range(0, 10)
      ->sort('title');

    // Limit query to templates.
    if ($templates) {
      $query->condition('template', TRUE);
    }
    elseif ($this->moduleHandler()->moduleExists('webform_templates')) {
      // Filter out templates if the webform_template.module is enabled.
      $query->condition('template', FALSE);
    }

    $entity_ids = $query->execute();

    if (empty($entity_ids)) {
      return new JsonResponse([]);
    }
    $webforms = $webform_storage->loadMultiple($entity_ids);

    $matches = [];
    foreach ($webforms as $webform) {
      if ($webform->access('view')) {
        $value = new FormattableMarkup('@label (@id)', ['@label' => $webform->label(), '@id' => $webform->id()]);
        $matches[] = ['value' => $value, 'label' => $value];
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   *
   * @return string
   *   The casestudy label as a render array.
   */
  public function title(Casestudy $casestudy = NULL) {
    /** @var \Drupal\Core\Entity\EntityInterface $source_entity */
    if($casestudy){
        return $casestudy->label();
    } else {
        return 'Case Study';
    }
  }

}
