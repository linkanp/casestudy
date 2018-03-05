<?php

namespace Drupal\casestudy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\casestudy\Entity\Casestudy;

/**
 * Defines the casestudy submission storage.
 */
class CasestudySubmissionStorage extends SqlContentEntityStorage implements CasestudySubmissionStorageInterface {

  /**
   * Array used to element data schema.
   *
   * @var array
   */
  protected $elementDataSchema = [];

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * WebformSubmissionStorage constructor.
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityManagerInterface $entity_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, AccountProxyInterface $current_user) {
    parent::__construct($entity_type, $database, $entity_manager, $cache, $language_manager);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $field_definitions = $this->entityManager->getBaseFieldDefinitions('webform_submission');

    // For now never let any see or export the serialize YAML data field.
    unset($field_definitions['data']);

    $definitions = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      $definitions[$field_name] = [
        'title' => $field_definition->getLabel(),
        'name' => $field_name,
        'type' => $field_definition->getType(),
        'target_type' => $field_definition->getSetting('target_type'),
      ];
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldDefinitionAccess(Casestudy $casestudy, array $definitions) {
    if (!$casestudy->access('submission_upates_any')) {
      unset($definitions['token']);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    /** @var \Drupal\webform\CasestudySubmissionInterface $entity */
    $entity = parent::doCreate($values);
    if (!empty($values['data'])) {
      $data = (is_array($values['data'])) ? $values['data'] : Yaml::decode($values['data']);
      $entity->setData($data);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    /** @var \Drupal\webform\CasestudySubmissionInterface[] $casestudy_submissions */
    $casestudy_submissions = parent::loadMultiple($ids);
    $this->loadData($casestudy_submissions);
    return $casestudy_submissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildPropertyQuery(QueryInterface $entity_query, array $values) {
    // Add account query wheneven filter by uid.
    if (isset($values['uid'])) {
      $account = User::load($values['uid']);
      $this->addQueryConditions($entity_query, NULL, NULL, $account);
      unset($values['uid']);
    }

    parent::buildPropertyQuery($entity_query, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, $limit = NULL, $max_sid = NULL) {
    $query = $this->getQuery();
    $this->addQueryConditions($query, $casestudy, $source_entity, NULL);
    if ($max_sid) {
      $query->condition('sid', $max_sid, '<=');
    }
    $query->sort('sid');
    if ($limit) {
      $query->range(0, $limit);
    }

    $entity_ids = $query->execute();
    $entities = $this->loadMultiple($entity_ids);
    $this->delete($entities);
    return count($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getTotal(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $in_draft = FALSE) {
    $query = $this->getQuery();
    $this->addQueryConditions($query, $casestudy, $source_entity, $account, ['in_draft' => $in_draft]);

    // Issue: Query count method is not working for SQL Lite.
    // return $query->count()->execute();
    // Work-around: Manually count the number of entity ids.
    return count($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxSubmissionId(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    $query = $this->getQuery();
    $this->addQueryConditions($query, $casestudy, $source_entity, $account);
    $query->sort('sid', 'DESC');
    $query->range(0, 1);

    $result = $query->execute();
    return reset($result);
  }

  /**
   * {@inheritdoc}
   */
  public function hasSubmissionValue(Casestudy $casestudy, $element_key) {
    /** @var \Drupal\Core\Database\StatementInterface $result */
    $result = $this->database->select('casestudy_submission_data', 'sd')
      ->fields('sd', ['sid'])
      ->condition('sd.casestudy_id', $casestudy->id())
      ->condition('sd.name', $element_key)
      ->execute();
    return $result->fetchAssoc() ? TRUE : FALSE;
  }

  /****************************************************************************/
  // Query methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function addQueryConditions(AlterableInterface $query, Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, array $options = []) {
    // Set default options/conditions.
    $options += [
      'check_source_entity' => FALSE,
      'in_draft' => NULL,
    ];

    if ($casestudy) {
      $query->condition('casestudy_id', $casestudy->id());
    }

    if ($source_entity) {
      $query->condition('entity_type', $source_entity->getEntityTypeId());
      $query->condition('entity_id', $source_entity->id());
    }
    elseif ($options['check_source_entity']) {
      $query->notExists('entity_type');
      $query->notExists('entity_id');
    }

    if ($account) {
      $query->condition('uid', $account->id());
      // Add anonymous submission ids stored in $_SESSION.
      if ($account->isAnonymous() && $account->id() == $this->currentUser->id()) {
        $sids = $this->getAnonymousSubmissionIds($account);
        if (empty($sids)) {
          // Look for NULL sid to force returning no results.
          $query->condition('sid', NULL);
        }
        else {
          $query->condition('sid', $sids, 'IN');
        }
      }
    }

    if ($options['in_draft'] !== NULL) {
      $query->condition('in_draft', $options['in_draft']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityTypes(Casestudy $casestudy) {
    $entity_types = Database::getConnection()->select('webform_submission', 's')
      ->distinct()
      ->fields('s', ['entity_type'])
      ->condition('s.casestudy_id', $casestudy->id())
      ->condition('s.entity_type', 'webform', '<>')
      ->orderBy('s.entity_type', 'ASC')
      ->execute()
      ->fetchCol();

    $entity_type_labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels();
    ksort($entity_type_labels);

    return array_intersect_key($entity_type_labels, array_flip($entity_types));
  }

  /**
   * Get a webform submission's terminus (aka first or last).
   *
   * @param \Drupal\webform\CasestudySubmissionInterface $casestudy_submission
   *   A webform submission.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   (optional) A webform submission source entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param array $options
   *   (optional) Additional options and query conditions.
   * @param string $terminus
   *   Submission terminus, first or last.
   *
   * @return \Drupal\webform\CasestudySubmissionInterface|null
   *   The webform submission's terminus (aka first or last).
   */
  protected function getTerminusSubmission(Casestudy $casestudy, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, array $options = [], $terminus = 'first') {
    $options += ['in_draft' => FALSE];
    $query = $this->getQuery();
    $this->addQueryConditions($query, $casestudy, $source_entity, $account, $options);
    $query->sort('sid', ($terminus == 'first') ? 'ASC' : 'DESC');
    $query->range(0, 1);
    return ($entity_ids = $query->execute()) ? $this->load(reset($entity_ids)) : NULL;
  }

  /**
   * Get a webform submission's sibling.
   *
   * @param \Drupal\webform\CasestudySubmissionInterface $casestudy_submission
   *   A webform submission.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   (optional) A webform submission source entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param array $options
   *   (optional) Additional options and query conditions.
   * @param string $direction
   *   Direction of the sibliing.
   *
   * @return \Drupal\webform\CasestudySubmissionInterface|null
   *   The webform submission's sibling.
   */
  protected function getSiblingSubmission(CasestudySubmissionInterface $casestudy_submission, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, array $options = [], $direction = 'previous') {
    $casestudy = $casestudy_submission->getCasestudy();

    $query = $this->getQuery();
    $this->addQueryConditions($query, $casestudy, $source_entity, $account, $options);

    if ($direction == 'previous') {
      $query->condition('sid', $casestudy_submission->id(), '<');
      $query->sort('sid', 'DESC');
    }
    else {
      $query->condition('sid', $casestudy_submission->id(), '>');
      $query->sort('sid', 'ASC');
    }

    $query->range(0, 1);

    return ($entity_ids = $query->execute()) ? $this->load(reset($entity_ids)) : NULL;
  }

  /****************************************************************************/
  // WebformSubmissionEntityList methods.
  /****************************************************************************/

  /**
   * Get specified columns in specified order.
   *
   * @param array $column_names
   *   An associative array of column names.
   * @param array $columns
   *   An associative array containing all available columns.
   *
   * @return array
   *    An associative array containing all specified columns.
   */
  protected function filterColumns(array $column_names, array $columns) {
    $filtered_columns = [];
    foreach ($column_names as $column_name) {
      if (isset($columns[$column_name])) {
        $filtered_columns[$column_name] = $columns[$column_name];
      }
    }
    return $filtered_columns;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    // Get custom columns from the webform's state.
    if ($source_entity) {
      $source_key = $source_entity->getEntityTypeId() . '.' . $source_entity->id();
      $column_names = $casestudy->getState("results.custom.columns.$source_key", []);
      // If the source entity does not have custom columns, then see if we
      // can use the main webform as the default custom columns.
      if (empty($column_names) && $casestudy->getState("results.custom.default", FALSE)) {
        $column_names = $casestudy->getState('results.custom.columns', []);
      }
    }
    else {
      $column_names = $casestudy->getState('results.custom.columns', []);
    }

    // Get columns
    $column_names =  $column_names ?: $this->getDefaultColumnNames($casestudy, $source_entity, $account, $include_elements);
    $columns = $this->getColumns($casestudy, $source_entity, $account, $include_elements);
    return $this->filterColumns($column_names, $columns);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    $column_names = ($casestudy) ? $casestudy->getSetting('submission_user_columns', []) : [];
    $column_names = $column_names ?: $this->getUserDefaultColumnNames($casestudy, $source_entity, $account, $include_elements);
    $columns = $this->getColumns($casestudy, $source_entity, $account, $include_elements);
    return $this->filterColumns($column_names, $columns);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserDefaultColumnNames(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    return ['serial', 'created', 'remote_addr'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultColumnNames(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    $columns = $this->getDefaultColumns($casestudy, $source_entity, $account, $include_elements);
    return array_keys($columns);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    $columns = $this->getColumns($casestudy, $source_entity, $account, $include_elements);
    // Hide certain unnecessary columns, that have default set to FALSE.
    foreach ($columns as $column_name => $column) {
      if (isset($column['default']) && $column['default'] === FALSE) {
        unset($columns[$column_name]);
      }
    }
    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function getColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE) {
    $view_any = ($casestudy && $casestudy->access('submission_view_any')) ? TRUE : FALSE;

    $columns = [];

    // Serial number.
    $columns['serial'] = [
      'title' => $this->t('#'),
    ];

    // Submission ID.
    $columns['sid'] = [
      'title' => $this->t('SID'),
      'default' => FALSE,
    ];

    // Submission label.
    $columns['label'] = [
      'title' => $this->t('Submission title'),
      'default' => FALSE,
      'sort' => FALSE,
    ];

    // UUID.
    $columns['uuid'] = [
      'title' => $this->t('UUID'),
      'default' => FALSE,
    ];

    // Draft
    $columns['in_draft'] = [
      'title' => $this->t('In draft'),
      'default' => FALSE,
    ];

    // Sticky (Starred/Unstarred).
    if (empty($account)) {
      $columns['sticky'] = [
        'title' => $this->t('Starred'),
      ];

      // Notes.
      $columns['notes'] = [
        'title' => $this->t('Notes'),
      ];
    }

    // Created.
    $columns['created'] = [
      'title' => $this->t('Created'),
    ];

    // Completed.
    $columns['completed'] = [
      'title' => $this->t('Completed'),
      'default' => FALSE,
    ];

    // Changed.
    $columns['changed'] = [
      'title' => $this->t('Changed'),
      'default' => FALSE,
    ];

    // Source entity.
    if ($view_any && empty($source_entity)) {
      $columns['entity'] = [
        'title' => $this->t('Submitted to'),
        'sort' => FALSE,
      ];
    }

    // Submitted by.
    if (empty($account)) {
      $columns['uid'] = [
        'title' => $this->t('User'),
      ];
    }

    // Submission language.
    if ($view_any && \Drupal::moduleHandler()->moduleExists('language')) {
      $columns['langcode'] = [
        'title' => $this->t('Language'),
      ];
    }

    // Remote address.
    $columns['remote_addr'] = [
      'title' => $this->t('IP address'),
    ];

    // Webform.
    if (empty($casestudy) && empty($source_entity)) {
      $columns['casestudy_id'] = [
        'title' => $this->t('Webform'),
      ];
    }

    // Webform elements.
    if ($casestudy && $include_elements) {
      /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
      $element_manager = \Drupal::service('plugin.manager.webform.element');
      $elements = $casestudy->getElementsInitializedFlattenedAndHasValue('view');
      foreach ($elements as $element) {
        /** @var \Drupal\webform\Plugin\WebformElementInterface $element_handler */
        $element_handler = $element_manager->createInstance($element['#type']);
        $columns += $element_handler->getTableColumn($element);
      }
    }

    // Operations.
    if (empty($account)) {
      $columns['operations'] = [
        'title' => $this->t('Operations'),
        'sort' => FALSE,
      ];
    }

    // Add name and format to all columns.
    foreach ($columns as $name => &$column) {
      $column['name'] = $name;
      $column['format'] = 'value';
    }

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomSetting($name, $default, Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL) {
    // Return the default value is webform and source entity is not defined.
    if (!$casestudy && !$source_entity) {
      return $default;
    }

    $key = "results.custom.$name";
    if (!$source_entity) {
      return $casestudy->getState($key, $default);
    }

    $source_key = $source_entity->getEntityTypeId() . '.' . $source_entity->id();
    if ($casestudy->hasState("$key.$source_key")) {
      return $casestudy->getState("$key.$source_key", $default);
    }
    if ($casestudy->getState("results.custom.default", FALSE)) {
      return $casestudy->getState($key, $default);
    }
    else {
      return $default;
    }
  }

  /****************************************************************************/
  // Invoke WebformElement and WebformHandler plugin methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function create(array $values = []) {
    /** @var \Drupal\casestudy\CasestudySubmissionInterface $entity */
    // Pre create is called via the WebformSubmission entity.
    // @see: \Drupal\webform\Entity\WebformSubmission::preCreate
    $entity = parent::create($values);

    //$this->invokeWebformElements('postCreate', $entity);
    //$this->invokeWebformHandlers('postCreate', $entity);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function postLoad(array &$entities) {
    /** @var \Drupal\webform\CasestudySubmissionInterface $entity */
    $return = parent::postLoad($entities);
    foreach ($entities as $entity) {
//      $this->invokeWebformElements('postLoad', $entity);
//      $this->invokeWebformHandlers('postLoad', $entity);

      // If this is an anonymous draft..
      // We must add $SESSION to the submission's cache context.
      // @see \Drupal\webform\WebformSubmissionStorage::loadDraft
      // @todo Add support for 'view own submission' permission.
      if ($entity->isDraft() && $entity->getOwner()->isAnonymous()) {
        $entity->addCacheContexts(['session']);
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity) {
    /** @var \Drupal\webform\CasestudySubmissionInterface $entity */
    $id = parent::doPreSave($entity);
    //$this->invokeWebformElements('preSave', $entity);
    //$this->invokeWebformHandlers('preSave', $entity);
    return $id;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\webform\CasestudySubmissionInterface $entity */

    $is_new = $entity->isNew();


//    if (!$entity->serial()) {
//      $next_serial = $this->entityManager->getStorage('casestudy')->getSerial($entity->getCasestudy());
//      $entity->set('serial', $next_serial);
//    }

    $result = parent::doSave($id, $entity);

    // Save data.
    $this->saveData($entity, !$is_new);

    // Set anonymous draft token.
    // This only needs to be called for new anonymous submissions.
    if ($is_new) {
      $this->setAnonymousSubmission($entity);
    }


    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update) {
    /** @var \Drupal\webform\CasestudySubmissionInterface $entity */
    parent::doPostSave($entity, $update);
//
//    // Log submission events.
//    if ($entity->getCasestudy()->hasSubmissionLog()) {
//      $t_args = ['@title' => $entity->label()];
//      switch ($entity->getState()) {
//        case CasestudySubmissionInterface::STATE_DRAFT:
//          if ($update) {
//            $operation = 'draft updated';
//            $message = $this->t('@title draft updated.', $t_args);
//          }
//          else {
//            $operation = 'draft created';
//            $message = $this->t('@title draft created.', $t_args);
//          }
//          break;
//
//        case CasestudySubmissionInterface::STATE_COMPLETED:
//          if ($update) {
//            $operation = 'submission completed';
//            $message = $this->t('@title completed using saved draft.', $t_args);
//          }
//          else {
//            $operation = 'submission created';
//            $message = $this->t('@title created.', $t_args);
//          }
//          break;
//
//        case CasestudySubmissionInterface::STATE_CONVERTED:
//          $operation = 'submission converted';
//          $message = $this->t('@title converted from anonymous to @user.', $t_args + ['@user' => $entity->getOwner()->label()]);
//          break;
//
//        case CasestudySubmissionInterface::STATE_UPDATED:
//          $operation = 'submission updated';
//          $message = $this->t('@title updated.', $t_args);
//          break;
//
//        case CasestudySubmissionInterface::STATE_UNSAVED:
//          $operation = 'submission submitted';
//          $message = $this->t('@title submitted.', $t_args);
//          break;
//
//        default:
//          throw new \Exception('Unexpected webform submission state');
//          break;
//      }
//
//      $this->log($entity, [
//        'handler_id' => '',
//        'operation' => $operation,
//        'message' => $message,
//      ]);
//    }
//
//    $this->invokeWebformElements('postSave', $entity, $update);
//    $this->invokeWebformHandlers('postSave', $entity, $update);
//
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    /** @var \Drupal\webform\CasestudySubmissionInterface $entity */
    if (!$entities) {
      // If no entities were passed, do nothing.
      return;
    }

//    foreach ($entities as $entity) {
//      $this->invokeWebformElements('preDelete', $entity);
//      $this->invokeWebformHandlers('preDelete', $entity);
//    }

    $return = parent::delete($entities);
    $this->deleteData($entities);

//    foreach ($entities as $entity) {
//      $this->invokeWebformElements('postDelete', $entity);
//      $this->invokeWebformHandlers('postDelete', $entity);
//    }

    // Delete submission log after all pre and post delete hooks are called.
    $this->deleteLog($entities);

    // Log deleted.
    foreach ($entities as $entity) {
      \Drupal::logger('webform')
        ->notice('Deleted @form: Submission #@id.', [
          '@id' => $entity->id(),
          '@form' => $entity->getCasestudy()->label(),
        ]);
    }

    return $return;
  }

  /****************************************************************************/
  // Invoke methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function invokeWebformHandlers($method, CasestudySubmissionInterface $casestudy_submission, &$context1 = NULL, &$context2 = NULL) {
    $casestudy = $casestudy_submission->getCasestudy();
    $casestudy->invokeHandlers($method, $casestudy_submission, $context1, $context2);
  }

  /**
   * {@inheritdoc}
   */
  public function invokeWebformElements($method, CasestudySubmissionInterface $casestudy_submission, &$context1 = NULL, &$context2 = NULL) {
    $casestudy = $casestudy_submission->getCasestudy();
    $casestudy->invokeElements($method, $casestudy_submission, $context1, $context2);
  }

  /****************************************************************************/
  // Purge methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function purge($count) {
    $days_to_seconds = 60 * 60 * 24;

    $query = $this->entityManager->getStorage('webform')->getQuery();
    $query->condition('settings.purge', [self::PURGE_DRAFT, self::PURGE_COMPLETED, self::PURGE_ALL], 'IN');
    $query->condition('settings.purge_days', 0, '>');
    $casestudys_to_purge = array_values($query->execute());

    $casestudy_submissions_to_purge = [];

    if (!empty($casestudys_to_purge)) {
      $casestudys_to_purge = $this->entityManager->getStorage('webform')->loadMultiple($casestudys_to_purge);
      foreach ($casestudys_to_purge as $casestudy) {
        $query = $this->getQuery();
        $query->condition('created', REQUEST_TIME - ($casestudy->getSetting('purge_days') * $days_to_seconds), '<');
        $query->condition('casestudy_id', $casestudy->id());
        switch ($casestudy->getSetting('purge')) {
          case self::PURGE_DRAFT:
            $query->condition('in_draft', TRUE);
            break;

          case self::PURGE_COMPLETED:
            $query->condition('in_draft', FALSE);
            break;
        }
        $query->range(0, $count - count($casestudy_submissions_to_purge));
        $result = array_values($query->execute());
        if (!empty($result)) {
          $casestudy_submissions_to_purge = array_merge($casestudy_submissions_to_purge, $result);
        }
        if (count($casestudy_submissions_to_purge) == $count) {
          // We've collected enough webform submissions for purging in this run.
          break;
        }
      }
    }

    if (!empty($casestudy_submissions_to_purge)) {
      $casestudy_submissions_to_purge = $this->loadMultiple($casestudy_submissions_to_purge);
      $this->delete($casestudy_submissions_to_purge);
    }
  }

  /****************************************************************************/
  // Data handlers.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function saveData(CasestudySubmissionInterface $casestudy_submission, $delete_first = TRUE) {
    // Get submission data rows.
    $data = $casestudy_submission->getData();

    $casestudy_id = $casestudy_submission->getCasestudy()->id();
    $sid = $casestudy_submission->id();

    //$elements = $casestudy_submission->getCasestudy()->getElementsInitializedFlattenedAndHasValue();
    $rows = [];
    foreach ($data as $name => $item) {
        if($item != null){
            $rows[] = [
                'casestudy_id' => $casestudy_id,
                'sid' => $sid,
                'name' => $name,
                'property' => '',
                'delta' => 0,
                'value' => (string) $item,
            ];
        }
    }

//    if ($delete_first) {
//      // Delete existing submission data rows.
//      $this->database->delete('casestudy_submission_data')
//        ->condition('sid', $sid)
//        ->execute();
//    }

    // Insert new submission data rows.
    if(!empty($rows)){
        $query = $this->database
          ->insert('casestudy_submission_data')
          ->fields(['casestudy_id', 'sid', 'name', 'property', 'delta', 'value']);
        foreach ($rows as $row) {
            // Delete first if this row exist
            $this->database->delete('casestudy_submission_data')
                ->condition('casestudy_id', $row['casestudy_id'])
                ->condition('sid', $row['sid'])
                ->condition('name', $row['name'])
                ->execute();
            $query->values($row);

            // Update Submitted status for this Question
            $data = [
                'submitted' => 1
            ];
            $this->database->update('casestudy_visit_status')->fields(
                $data
            )->condition('sid', $row['sid'], '=')
                ->condition('casestudy_id', $row['casestudy_id'], '=')
                ->condition('element_id', $row['name'], '=')
                ->execute();


        }
        $query->execute();



    }
  }

  /**
   * Save webform submission data from the 'casestudy_submission_data' table.
   *
   * @param array $casestudy_submissions
   *   An array of webform submissions.
   */
  protected function loadData(array &$casestudy_submissions) {
    // Load webform submission data.
    if ($sids = array_keys($casestudy_submissions)) {
      /** @var \Drupal\Core\Database\StatementInterface $result */
      $result = $this->database->select('casestudy_submission_data', 'sd')
        ->fields('sd', ['casestudy_id', 'sid', 'name', 'property', 'delta', 'value'])
        ->condition('sd.sid', $sids, 'IN')
        ->orderBy('sd.sid', 'ASC')
        ->orderBy('sd.name', 'ASC')
        ->orderBy('sd.property', 'ASC')
        ->orderBy('sd.delta', 'ASC')
        ->execute();
      $submissions_data = [];
      while ($record = $result->fetchAssoc()) {
        $sid = $record['sid'];
        $name = $record['name'];

        $elements = $casestudy_submissions[$sid]->getCasestudy()->getElements();
        //$element = (isset($elements[$name])) ? $elements[$name] : ['#webform_multiple' => FALSE, '#webform_composite' => FALSE];
        $submissions_data[$sid][$name] = $record['value'];
      }

      // Set webform submission data via setData().
      foreach ($submissions_data as $sid => $submission_data) {
        $casestudy_submissions[$sid]->setData($submission_data);
        $casestudy_submissions[$sid]->setOriginalData($submission_data);
      }
    }
  }

  /**
   * Delete casestudy submission data from the 'casestudy_submission_data' table.
   *
   * @param array $casestudy_submissions
   *   An array of casestudy submissions.
   */
  protected function deleteData(array $casestudy_submissions) {
    $sids = [];
    foreach ($casestudy_submissions as $casestudy_submission) {
      $sids[$casestudy_submission->id()] = $casestudy_submission->id();
    }
    $this->database->delete('casestudy_submission_data')
      ->condition('sid', $sids, 'IN')
      ->execute();
  }

  /****************************************************************************/
  // Log methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function log(CasestudySubmissionInterface $casestudy_submission, array $values = []) {
    // Submission ID is required for logging.
    // @todo Enable logging for submissions not saved to the database.
    if (empty($casestudy_submission->id())) {
      return;
    }

    $values += [
      'uid' => $this->currentUser->id(),
      'casestudy_id' => $casestudy_submission->getCasestudy()->id(),
      'sid' => $casestudy_submission->id() ?: NULL,
      'handler_id' => NULL,
      'data' => [],
      'timestamp' => time(),
    ];
    $values['data'] = serialize($values['data']);
    \Drupal::database()
      ->insert('casestudy_submission_log')
      ->fields($values)
      ->execute();
  }

  /**
   * Delete casestudy submission events from the 'casestudy_submission_log' table.
   *
   * @param array $casestudy_submissions
   *   An array of casestudy submissions.
   */
  protected function deleteLog(array $casestudy_submissions) {
    $sids = [];
    foreach ($casestudy_submissions as $casestudy_submission) {
      $sids[$casestudy_submission->id()] = $casestudy_submission->id();
    }
    $this->database->delete('casestudy_submission_log')
      ->condition('sid', $sids, 'IN')
      ->execute();
  }

  /****************************************************************************/
  // Draft methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function loadDraft(Casestudy $casestudy, EntityInterface $source_entity = NULL, AccountInterface $account = NULL) {
    $options = [
      'check_source_entity' => TRUE,
      'in_draft' => TRUE,
    ];

    $query = $this->getQuery();
    $this->addQueryConditions($query, $casestudy, $source_entity, $account, $options);

    // Only load the most recent draft.
    $query->sort('sid', 'DESC');

    return ($sids = $query->execute()) ? $this->load(reset($sids)) : NULL;
  }

  /****************************************************************************/
  // Anonymous submission methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function userLogin(UserInterface $account) {
    if (empty($_SESSION['casestudy_submissions'])) {
      return;
    }

    // Move all anonymous submissions to UID of this account.
    $query = $this->getQuery();
    $query->condition('uid', 0);
    $query->condition('sid', $_SESSION['casestudy_submissions'], 'IN');
    $query->sort('sid');
    if ($sids = $query->execute()) {
      $casestudy_submissions = $this->loadMultiple($sids);
      foreach ($casestudy_submissions as $sid => $casestudy_submission) {
        $casestudy = $casestudy_submission->getCasestudy();
        // Do not convert confidential submissions and check for convert
        // anonymous setting.
        if ($casestudy->isConfidential() || empty($casestudy->getSetting('form_convert_anonymous'))) {
          continue;
        }

        unset($_SESSION['casestudy_submissions'][$sid]);
        $casestudy_submission->convert($account);
      }
    }

    // Now that the user has logged in because when the log out $_SESSION is
    // completely reset.
    unset($_SESSION['casestudy_submissions']);
  }

  /**
   * Track anonymous submissions.
   *
   * Anonymous submission are tracked so that they can be assigned to the user
   * if they login.
   *
   * We use session for storing draft tokens. So we can only do it for the
   * current user.
   *
   * We do not use PrivateTempStore because it utilizes session ID as the key in
   * key-value hash map where it stores its data. During user login the session
   * ID is regenerated (see user_login_finalize()) so it is not suitable for us
   * since we need to "carry" the draft tokens from anonymous session to the
   * logged in one.
   *
   * @param \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission
   *   A casestudy submission.
   *
   * @see CasestudySubmissionStorage::loadDraft
   * @see CasestudySubmissionStorage::userLogin
   */
  protected function setAnonymousSubmission(CasestudySubmissionInterface $casestudy_submission) {
    // Make sure the account and current user are identical.
    if ($casestudy_submission->getOwnerId() != $this->currentUser->id()) {
      return;
    }

    // Make sure the submission is anonymous.
    if (!$casestudy_submission->getOwner()->isAnonymous()) {
      return;
    }

    $_SESSION['casestudy_submissions'][$casestudy_submission->id()] = $casestudy_submission->id();
  }

  /**
   * Get anonymous users sumbmission ids.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   *
   * @return array|
   *   A array of submission ids or NULL if the user us not anonymous or has
   *   not saved submissions.
   */
  protected function getAnonymousSubmissionIds(AccountInterface $account) {
    // Make sure the account and current user are identical.
    if ($account->id() != $this->currentUser->id()) {
      return NULL;
    }

    if (empty($_SESSION['casestudy_submissions'])) {
      return NULL;
    }

    // Cleanup sids because drafts could have been purged or the casestudy
    // submission could have been deleted.
    $_SESSION['casestudy_submissions'] = $this->getQuery()
      ->condition('sid', $_SESSION['casestudy_submissions'], 'IN')
      ->sort('sid')
      ->execute();
    if (empty($_SESSION['casestudy_submissions'])) {
      unset($_SESSION['casestudy_submissions']);
      return NULL;
    }

    return $_SESSION['casestudy_submissions'];
  }

}
