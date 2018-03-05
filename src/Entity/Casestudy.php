<?php

namespace Drupal\casestudy\Entity;

use Drupal\casestudy\Utility\GeneralHelper;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Serialization\Yaml;

/**
 * Defines the robot entity.
 *
 * The lines below, starting with '@ConfigEntityType,' are a plugin annotation.
 * These define the entity type to the entity type manager.
 *
 * The properties in the annotation are as follows:
 *  - id: The machine name of the entity type.
 *  - label: The human-readable label of the entity type. We pass this through
 *    the "@Translation" wrapper so that the multilingual system may
 *    translate it in the user interface.
 *  - handlers: An array of entity handler classes, keyed by handler type.
 *    - access: The class that is used for access checks.
 *    - list_builder: The class that provides listings of the entity.
 *    - form: An array of entity form classes keyed by their operation.
 *  - entity_keys: Specifies the class properties in which unique keys are
 *    stored for this entity type. Unique keys are properties which you know
 *    will be unique, and which the entity manager can use as unique in database
 *    queries.
 *  - links: entity URL definitions. These are mostly used for Field UI.
 *    Arbitrary keys can set here. For example, User sets cancel-form, while
 *    Node uses delete-form.
 *
 * @see http://previousnext.com.au/blog/understanding-drupal-8s-config-entities
 * @see annotation
 *
 *
 * @ConfigEntityType(
 *   id = "casestudy",
 *   label = @Translation("Casestudy"),
 *   admin_permission = "administer casestudy",
 *   handlers = {
 *     "access" = "Drupal\casestudy\CasestudyAccessController",
 *     "list_builder" = "Drupal\casestudy\Controller\CasestudyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\casestudy\Form\CasestudyAddForm",
 *       "edit" = "Drupal\casestudy\Form\CasestudyEditForm",
 *       "delete" = "Drupal\casestudy\Form\CasestudyDeleteForm",
 *       "settings" = "Drupal\casestudy\Form\CasestudySettingsForm",
 *       "tabsettings" = "Drupal\casestudy\Form\CasestudyTabSettingsForm",
 *       "analytics" = "Drupal\casestudy\Form\CasestudyAnalyticsForm",
 *       "finalpagesettings" = "Drupal\casestudy\Form\CasestudyFinalPageForm"
 *     }
 *   },
 *   bundle_of = "casestudy_submission",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "canonical" = "/casestudy/{casestudy}",
 *     "edit-form" = "/admin/structure/casestudy/manage/{casestudy}",
 *     "settings-form" = "/admin/structure/casestudy/manage/{casestudy}/settings",
 *     "delete-form" = "/admin/structure/casestudy/manage/{casestudy}/delete"
 *   }
 * )
 */
class Casestudy extends ConfigEntityBase {

  /**
   * The casestudy ID.
   *
   * @var string
   */
  public $id;

  /**
   * The casestudy UUID.
   *
   * @var string
   */
  public $uuid;

    /**
     * The casestudy status.
     *
     * @var bool
     */
  public $status = true;

   /**
   * The casestudy label.
   *
   * @var string
   */
  public $label;

    /**
     * The casestudy description.
     *
     * @var string
     */
    protected $description;

  /**
   * The casestudy tab name.
   *
   * @var string
   */
  public $tab;

    /**
     * The casestudy gallery id.
     *
     * @var integer
     */
    public $gallery;

    /**
     * The left navigation flag.
     *
     * @var bool
     */
    public $left = false;

    /**
     * The casestudy directory prefix.
     *
     * @var string
     */
    public $prefix;

    /**
     * The casestudy path(URL).
     *
     * @var string
     */
    public $path;

    /**
     * The casestudy created date time.
     *
     * @var string
     */
    public $created;

    /**
     * The casestudy banner.
     *
     * @var string
     */
    public $banner;

    /**
     * The casestudy elements (Question HTML).
     *
     * @var string
     */
    protected $elements;

    /**
     * The casestudy Tabs.
     *
     * @var string
     */
    protected $tabs;

    /*
     * Casestudy final page fields
     */
    /*
     * @var string
     */
    public $final_page_title;
    /*
     * @var string
     */
    public $final_page_summary_title;
    /*
     * @var string
     */
    protected $final_page_description;
    /*
     * @var string
     */
    public $final_page_claim_button_show;
    /*
     * @var string
     */
    public $final_page_claim_button_text;
    /*
         * @var string
         */
    public $final_page_claim_url;



    /**
     * get the Custom View URL
     */
    public function getURL() {
        $url = $this->prefix.$this->path;
        return $url;
    }

    /**
     * {@inheritdoc}
     */
    public function setElements(array $elements) {
        $this->elements = Yaml::encode($elements);
        //$this->resetElements();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function getElements() {
        return Yaml::decode($this->elements);
    }

    /**
     * {@inheritdoc}
     */
    public function getPages() {
        $elements = Yaml::decode($this->elements);
        if($this->prefix != 'quiz'){
            $pages = array_merge(['intro_page' => ['title'=>'Introduction']], $elements);
            $pages = array_merge($pages,['final_page'=>['title'=>'Final Results']]);
        } else {
            $pages = array_merge($elements,['last_question_result' => ['title'=>''] ,'final_page'=>['title'=>'Final Results']]);
            //$pages = array_merge($elements,['final_page'=>['title'=>'Final Results']]);
        }
        return $pages;
    }

    /**
     * {@inheritdoc}
     */
    public function getElement($key) {
        return \Drupal::entityTypeManager()
            ->getStorage('question')
            ->load($key);
    }


    /**
     * {@inheritdoc}
     */
    public function setTabs(array $tabs) {
        $this->tabs = Yaml::encode($tabs);
        //$this->resetElements();
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function getTabs() {
        return Yaml::decode($this->tabs);
    }


    /**
     * {@inheritdoc}
     */
    public function getSubmissionForm(array $values = [], $operation = 'default') {
        // Set this webform's id.
        $values['casestudy_id'] = $this->id();

        // Retrieve Cookie Data
        $cookeData = GeneralHelper::decryptCookieData($this->getCookieName());
        if($cookeData['submission_id']){
            $casestudy_submission = $this->entityTypeManager()
                ->getStorage('casestudy_submission')
                ->load($cookeData['submission_id']);
        } else {
            $casestudy_submission = $this->entityTypeManager()
                ->getStorage('casestudy_submission')
                ->create($values);
        }
        return \Drupal::service('entity.form_builder')
            ->getForm($casestudy_submission, $operation);
    }

    public function getTabName(){
        return $this->tab;
    }

    public function getLeftNav(){
        return $this->left;
    }
    public function getDescription(){
        return $this->description;
    }
    public function getCookieName(){
        return 'apha_casestudy_'.$this->id;
    }

    public function getFinalPageTitle(){
        return $this->final_page_title;
    }
    public function getFinalPageSummaryTitle(){
        return $this->final_page_summary_title;
    }

    public function getFinalPageDescription(){
        return $this->final_page_description;
    }

    public function getFinalPageClaimButtonShow(){
        return $this->final_page_claim_button_show;
    }
    public function getFinalPageClaimButtonText(){
        return $this->final_page_claim_button_text;
    }
    public function getFinalPageClaimUrl(){
        return $this->final_page_claim_url;
    }

}
