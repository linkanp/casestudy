<?php

namespace Drupal\casestudy\Entity;

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
 * @see Drupal\Core\Annotation\Translation
 *
 * @ingroup config_entity_example
 *
 * @ConfigEntityType(
 *   id = "question",
 *   label = @Translation("Question"),
 *   admin_permission = "administer question",
 *   handlers = {
 *     "list_builder" = "Drupal\casestudy\Controller\QuestionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\casestudy\Form\QuestionAddForm",
 *       "edit" = "Drupal\casestudy\Form\QuestionEditForm",
 *       "delete" = "Drupal\casestudy\Form\QuestionDeleteForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   }
 * )
 */
class Question extends ConfigEntityBase {

  /**
   * The question ID.
   *
   * @var string
   */
  public $id;

  /**
   * The question UUID.
   *
   * @var string
   */
  public $uuid;


   /**
   * The question label.
   *
   * @var string
   */
  public $label;
    /**
     * The question description.
     *
     * @var string
     */
    protected $description;

    /**
     * The answer a.
     *
     * @var string
     */
    public $answer_a;
    /**
     * The answer b.
     *
     * @var string
     */
    public $answer_b;
    /**
     * The answer c.
     *
     * @var string
     */
    public $answer_c;
    /**
     * The answer d.
     *
     * @var string
     */
    public $answer_d;
    /**
     * The correct answer.
     *
     * @var string
     */
    public $correct_answer;

    /**
     * The question page html.
     *
     * @var string
     */
    public $question_page_html;

    /**
     * The result page html.
     *
     * @var string
     */
    public $result_page_html;

    /**
     * The question label.
     *
     * @var string
     */
    public $casestudy;


    /**
     * The type, we plan to create HTML content using this Entity, so need a type field.
     *
     * @var string
     */
    public $type;


    public function getType(){
        return $this->type;
    }

    public function getAnswerA(){
        return $this->answer_a;
    }
    public function getAnswerB(){
        return $this->answer_b;
    }
    public function getAnswerC(){
        return $this->answer_c;
    }
    public function getAnswerD(){
        return $this->answer_d;
    }
    public function getAnswer($key){
        return $this->{$key};
    }
    public function getCorrectAnswer(){
        return $this->correct_answer;
    }


    public function getDescription(){
        return $this->description;
    }
    public function getQuestionHTML(){
        return $this->question_page_html;
    }
    public function getResultHTML(){
        return $this->result_page_html;
    }



}
