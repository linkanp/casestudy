<?php

namespace Drupal\casestudy\Entity;

use Drupal\Core\Serialization\Yaml;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
#use Drupal\webform\Plugin\Field\FieldType\WebformEntityReferenceItem;
#use Drupal\webform\WebformInterface;
use Drupal\casestudy\CasestudySubmissionInterface;

/**
 * Defines the CasestudySubmission entity.
 *
 * @ingroup casestudy
 *
 * @ContentEntityType(
 *   id = "casestudy_submission",
 *   label = @Translation("Casestudy submission"),
 *   bundle_label = @Translation("Casestudy"),
 *   handlers = {
 *     "storage" = "Drupal\casestudy\CasestudySubmissionStorage",
 *     "storage_schema" = "Drupal\casestudy\CasestudySubmissionStorageSchema",
 *     "list_builder" = "Drupal\casestudy\CasestudySubmissionListBuilder",
 *     "form" = {
 *       "default" = "Drupal\casestudy\CasestudySubmissionForm",
 *     },
 *   },
 *   bundle_entity_type = "casestudy",
 *   base_table = "casestudy_submission",
 *   entity_keys = {
 *     "id" = "sid",
 *     "bundle" = "casestudy_id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/casestudy/manage/{casestudy}/submission/{casestudy}",
 *     "collection" = "/admin/config/casestudy/results/manage/list",
 *     "edit-form" = "/admin/config/casestudy/manage/{casestudy}/submission/{casestudy_submission}/edit",
 *     "delete-form" = "/admin/config/casestudy/manage/{casestudy}/submission/{casestudy_submission}/delete"
 *   }
 * )
 */
class CasestudySubmission extends ContentEntityBase implements CasestudySubmissionInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * Store a reference to the current temporary casestudy.
   *
   * @var \Drupal\casestudy\Entity\Casestudy
   *
   */
  protected static $casestudy;

  /**
   * The data.
   *
   * @var array
   */
  protected $data = [];

  /**
   * Reference to original data loaded before any updates.
   *
   * @var array
   */
  protected $originalData = [];

  /**
   * Flag to indicated is submission is being converted from anonymous to authenticated.
   *
   * @var bool
   */
  protected $converting = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['serial'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Serial number'))
      ->setDescription(t('The serial number of the casestudy submission entity.'))
      ->setReadOnly(TRUE);

    $fields['sid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Submission ID'))
      ->setDescription(t('The ID of the casestudy submission entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('Submission UUID'))
      ->setDescription(t('The UUID of the casestudy submission entity.'))
      ->setReadOnly(TRUE);

    $fields['token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Token'))
      ->setDescription(t('A secure token used to look up a submission.'))
      ->setSetting('max_length', 255)
      ->setReadOnly(TRUE);

    $fields['uri'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submission URI'))
      ->setDescription(t('The URI the user submitted the casestudy.'))
      ->setSetting('max_length', 2000)
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the casestudy submission was first saved as draft or submitted.'));

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed'))
      ->setDescription(t('The time that the casestudy submission was submitted as complete (not draft).'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the casestudy submission was last saved (complete or draft).'));

    $fields['in_draft'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is draft'))
      ->setDescription(t('Is this a draft of the submission?'))
      ->setDefaultValue(FALSE);

    $fields['current_page'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Current page'))
      ->setDescription(t('The current wizard page.'))
      ->setSetting('max_length', 128);

    $fields['remote_addr'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote IP address'))
      ->setDescription(t('The IP address of the user that submitted the casestudy.'))
      ->setSetting('max_length', 128);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Submitted by'))
      ->setDescription(t('The username of the user that submitted the casestudy.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\casestudy\Entity\WebformSubmission::getCurrentUserId');

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The submission language code.'));

    $fields['casestudy_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Casestudy'))
      ->setDescription(t('The associated casestudy.'))
      ->setSetting('target_type', 'casestudy');

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submitted to: Entity type'))
      ->setDescription(t('The entity type to which this submission was submitted from.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    // Can't use entity reference without a target type because it defaults to
    // an integer which limits reference to only content entities (and not
    // config entities like Views, Panels, etc...).
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::propertyDefinitions()
    $fields['entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Submitted to: Entity ID'))
      ->setDescription(t('The ID of the entity of which this casestudy submission was submitted from.'))
      ->setSetting('max_length', 255);

    $fields['sticky'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sticky'))
      ->setDescription(t('A flag that indicate the status of the casestudy submission.'))
      ->setDefaultValue(FALSE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Administrative notes about the casestudy submission.'))
      ->setDefaultValue('');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function serial() {
    return $this->get('serial')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $submission_label = $this->getCasestudy()->getSetting('submission_label')
      ?: \Drupal::config('casestudy.settings')->get('settings.default_submission_label');
    return \Drupal::service('casestudy.token_manager')->replace($submission_label, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    if (isset($this->get('created')->value)) {
      return $this->get('created')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($created) {
    $this->set('created', $created);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChangedTime($timestamp) {
    $this->set('changed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompletedTime() {
    return $this->get('completed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCompletedTime($timestamp) {
    $this->set('completed', $timestamp);
    return $this;
  }


  /**
   * {@inheritdoc}
   */
  public function getRemoteAddr() {
    return $this->get('remote_addr')->value ?: $this->t('(unknown)');
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteAddr($ip_address) {
    $this->set('remote_addr', $ip_address);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentPage() {
    return $this->get('current_page')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentPage($current_page) {
    $this->set('current_page', $current_page);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentPageTitle() {
    $current_page = $this->getCurrentPage();
    $page = $this->getCasestudy()->getPage($current_page);
    return ($page && isset($page['#title'])) ? $page['#title'] : $current_page;
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key = NULL) {
    if ($key !== NULL) {
      return (isset($this->data[$key])) ? $this->data[$key] : NULL;
    }
    else {
      return $this->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalData($key = NULL) {
    if ($key !== NULL) {
      return (isset($this->originalData[$key])) ? $this->originalData[$key] : NULL;
    }
    else {
      return $this->originalData;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalData(array $data) {
    $this->originalData = $data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken() {
    return $this->token->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCasestudy() {
    if (isset($this->casestudy_id->entity)) {
      return $this->casestudy_id->entity;
    }
    else {
      return static::$casestudy;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntity() {
    if ($this->entity_type->value && $this->entity_id->value) {
      $entity_type = $this->entity_type->value;
      $entity_id = $this->entity_id->value;
      return $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceUrl() {
    $uri = $this->uri->value;
    if ($uri !== NULL && ($url = \Drupal::pathValidator()->getUrlIfValid($uri))) {
      return $url->setOption('absolute', TRUE);
    }
    elseif (($entity = $this->getSourceEntity()) && $entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl()->setOption('absolute', TRUE);
    }
    else {
      return $this->getCasestudy()->toUrl()->setOption('absolute', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUrl() {
    return $this->getSourceUrl()
      ->setOption('query', ['token' => $this->token->value]);
  }



  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    $user = $this->get('uid')->entity;
    if (!$user || $user->isAnonymous()) {
      $user = User::getAnonymousUser();
    }
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDraft() {
    return $this->get('in_draft')->value ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isConverting() {
    return $this->converting;
  }

  /**
   * {@inheritdoc}
   */
  public function isCompleted() {
    return $this->get('completed')->value ? TRUE : FALSE;
  }


  /**
   * {@inheritdoc}
   */
  public function getState() {
    if (!$this->id()) {
      return self::STATE_UNSAVED;
    }
    elseif ($this->isConverting()) {
      return self::STATE_CONVERTED;
    }
    elseif ($this->isDraft()) {
      return self::STATE_DRAFT;
    }
    elseif ($this->completed->value == $this->changed->value) {
      return self::STATE_COMPLETED;
    }
    else {
      return self::STATE_UPDATED;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['casestudy'] = $this->getCasestudy()->id();
    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    /** @var \Drupal\casestudy\WebformSubmissionInterface $duplicate */
    $duplicate = parent::createDuplicate();

    $duplicate->set('serial', NULL);
    $duplicate->set('token', Crypt::randomBytesBase64());

    // Clear state.
    $duplicate->set('in_draft', FALSE);
    $duplicate->set('current_page', NULL);

    // Create timestamps.
    $duplicate->set('created', NULL);
    $duplicate->set('changed', NULL);
    $duplicate->set('completed', NULL);

    // Clear admin notes and sticky.
    $duplicate->set('notes', '');
    $duplicate->set('sticky', FALSE);

    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    if (empty($values['casestudy_id']) && empty($values['casestudy'])) {
      if (empty($values['casestudy_id'])) {
        throw new \Exception('Casestudy id (casestudy_id) is required to create a casestudy submission.');
      }
      elseif (empty($values['casestudy'])) {
        throw new \Exception('Casestudy (casestudy) is required to create a casestudy submission.');
      }
    }

    // Get temporary casestudy entity and store it in the static
    // CasestudySubmission::$casestudy property.
    // This could be reworked to use \Drupal\user\PrivateTempStoreFactory
    // but it might be overkill since we are just using this to validate
    // that a casestudy's elements can be rendered.
    // @see \Drupal\casestudy\WebformEntityElementsValidator::validateRendering()
    // @see \Drupal\casestudy_ui\Form\WebformUiElementTestForm::buildForm()
    if (isset($values['casestudy']) && ($values['casestudy'] instanceof WebformInterface)) {
      $casestudy = $values['casestudy'];
      static::$casestudy = $values['casestudy'];
      $values['casestudy_id'] = $values['casestudy']->id();
    }
    else {
      /** @var \Drupal\webform\WebformInterface $webform */
      $casestudy = Casestudy::load($values['casestudy_id']);
      static::$casestudy = NULL;
    }

//    // Get request's source entity parameter.
//    /** @var \Drupal\webform\WebformRequestInterface $request_handler */
//    $request_handler = \Drupal::service('webform.request');
//    $source_entity = $request_handler->getCurrentSourceEntity('webform');
//    $values += [
//      'entity_type' => ($source_entity) ? $source_entity->getEntityTypeId() : NULL,
//      'entity_id' => ($source_entity) ? $source_entity->id() : NULL,
//    ];

    // Decode all data in an array.
    if (empty($values['data'])) {
      $values['data'] = [];
    }
    elseif (is_string($values['data'])) {
      $values['data'] = Yaml::decode($values['data']);
    }
//
//    // Get default date from source entity 'webform' field.
//    if ($values['entity_type'] && $values['entity_id']) {
//      $source_entity = \Drupal::entityTypeManager()
//        ->getStorage($values['entity_type'])
//        ->load($values['entity_id']);
//      if ($webform_field_name = WebformEntityReferenceItem::getEntityWebformFieldName($source_entity)) {
//        if ($source_entity->$webform_field_name->target_id == $webform->id() && $source_entity->$webform_field_name->default_data) {
//          $values['data'] += Yaml::decode($source_entity->$webform_field_name->default_data);
//        }
//      }
//    }
//
//    // Set default values.
//    $current_request = \Drupal::requestStack()->getCurrentRequest();
//    $values += [
//      'in_draft' => FALSE,
//      'uid' => \Drupal::currentUser()->id(),
//      'langcode' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
//      'token' => Crypt::randomBytesBase64(),
//      'uri' => preg_replace('#^' . base_path() . '#', '/', $current_request->getRequestUri()),
//      'remote_addr' => ($webform && $webform->isConfidential()) ? '' : $current_request->getClientIp(),
//    ];
//
//    $webform->invokeHandlers(__FUNCTION__, $values);
//    $webform->invokeElements(__FUNCTION__, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Set created.
    if (!$this->created->value) {
      $this->created->value = REQUEST_TIME;
    }

    // Set changed.
    $this->changed->value = REQUEST_TIME;

    // Set completed.
    if ($this->isDraft()) {
      $this->completed->value = NULL;
    }
    elseif (!$this->isCompleted()) {
      $this->completed->value = REQUEST_TIME;
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    // Clear the remote_addr for confidential submissions.
    //if ($this->getCasestudy()->isConfidential()) {
      //$this->get('remote_addr')->value = '';
    //}

    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function convert(UserInterface $account) {
    $this->converting = TRUE;
    $this->setOwner($account);
    $this->save();
    $this->converting = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray($custom = FALSE, $check_access = FALSE) {
    if ($custom === FALSE) {
      return parent::toArray();
    }
    else {
      $values = parent::toArray();
      foreach ($values as $key => $item) {
        // Issue #2567899 It seems it is impossible to save an empty string to an entity.
        // @see https://www.drupal.org/node/2567899
        // Solution: Set empty (aka NULL) items to an empty string.
        if (empty($item)) {
          $values[$key] = '';
        }
        else {
          $value = reset($item);
          $values[$key] = reset($value);
        }
      }

      $values['data'] = $this->getData();

      // Check access.
      if ($check_access) {
        // Check field definition access.
        $submission_storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
        $field_definitions = $submission_storage->getFieldDefinitions();
        $field_definitions = $submission_storage->checkFieldDefinitionAccess($this->getWebform(), $field_definitions + ['data' => TRUE]);
        $values = array_intersect_key($values, $field_definitions);

        // Check element data access.
        $elements = $this->getCasestudy()->getElementsInitializedFlattenedAndHasValue('view');
        $values['data'] = array_intersect_key($values['data'], $elements);
      }

      return $values;
    }
  }


  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

}
