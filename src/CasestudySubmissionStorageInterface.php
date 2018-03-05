<?php

namespace Drupal\casestudy;

use Drupal\casestudy\Entity\Casestudy;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Defines an interface for casestudy submission classes.
 */
interface CasestudySubmissionStorageInterface extends ContentEntityStorageInterface {

  /**
   * Return status for saving of casestudy submission when saving results is disabled.
   */
  const SAVED_DISABLED = 0;

  /**
   * Denote not to purge automatically anything at all.
   *
   * @var string
   */
  const PURGE_NONE = 'none';

  /**
   * Denote to purge automatically only drafts.
   *
   * @var string
   */
  const PURGE_DRAFT = 'draft';

  /**
   * Denote to purge automatically only completed submissions.
   *
   * @var string
   */
  const PURGE_COMPLETED = 'completed';

  /**
   * Denote to purge automatically all submissions.
   *
   * @var string
   */
  const PURGE_ALL = 'all';

  /**
   * Get casestudy submission entity field definitions.
   *
   * The helper method is generally used for exporting results.
   *
   * @see \Drupal\webform\Element\WebformExcludedColumns
   * @see \Drupal\webform\Controller\WebformResultsExportController
   *
   * @return array
   *   An associative array of field definition key by field name containing
   *   title, name, and datatype.
   */
  public function getFieldDefinitions();

  /**
   * Check field definition access.
   *
   * Access checks include...
   * - Only allowing user who can update any access to the 'token' field.
   *
   * @param \Drupal\casestudy\Entity\Casestudy $casestudy
   *   The casestudy to check field definition access.
   * @param array $definitions
   *   Field definitions.
   *
   * @return array
   *   Field definitions with access checked.
   */
  public function checkFieldDefinitionAccess(Casestudy $casestudy, array $definitions);

  /**
   * Delete all casestudy submissions.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   (optional) The casestudy to delete the submissions from.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   (optional) A casestudy submission source entity.
   * @param int $limit
   *   (optional) Number of submissions to be deleted.
   * @param int $max_sid
   *   (optional) Maximum casestudy submission id.
   *
   * @return int
   *   The number of casestudy submissions deleted.
   */
  public function deleteAll(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, $limit = NULL, $max_sid = NULL);

  /**
   * Get the total number of submissions.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   (optional) A casestudy. If set the total number of submissions for the
   *   Casestudy will be returned.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   (optional) A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) A user account.
   * @param bool $in_draft
   *   (optional) Look for submissions in draft. Defaults to FALSE.
   *   Setting to NULL will return all saved submissions and drafts.
   *
   * @return int
   *   Total number of submissions.
   */
  public function getTotal(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $in_draft = FALSE);


  /**
   * Determine if a casestudy element has submission values.
   *
   * @param \Drupal\casestudy\Entity\Casestudy  $casestudy
   *   A casestudy.
   * @param string $element_key
   *   An element key.
   *
   * @return bool
   *   TRUE if a casestudy element has submission values.
   */
  public function hasSubmissionValue(Casestudy $casestudy, $element_key);

  /****************************************************************************/
  // Query methods.
  /****************************************************************************/

  /**
   * Add condition to submission query.
   *
   * @param \Drupal\Core\Database\Query\AlterableInterface $query
   *   The query instance.
   * @param \Drupal\casestudy\Entity\Casestudy $casestudy
   *   (optional) A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   (optional) A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The current user account.
   * @param array $options
   *   (optional) Additional options and query conditions.
   */
  public function addQueryConditions(AlterableInterface $query, Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, array $options = []);

  /**
   * Get casestudy submission source entity types.
   *
   * @param \Drupal\casestudy\Entity\Casestudy $casestudy
   *   A casestudy.
   *
   * @return array
   *   An array of entity types that the casestudy has been submitted from.
   */
  public function getSourceEntityTypes(Casestudy $casestudy);

  /****************************************************************************/
  // WebformSubmissionEntityList methods.
  /****************************************************************************/

  /**
   * Get customized submission columns used to display custom table.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   * @param bool $include_elements
   *   Flag that include all form element in the list of columns.
   *
   * @return array|mixed
   *   An associative array of columns keyed by name.
   */
  public function getCustomColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE);

  /**
   * Get user submission columns used to display results.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   * @param bool $include_elements
   *   Flag that include all form element in the list of columns.
   *
   * @return array|mixed
   *   An associative array of columns keyed by name.
   */
  public function getUserColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE);

  /**
   * Get user default submission columns used to display results.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   * @param bool $include_elements
   *   Flag that include all form element in the list of columns.
   *
   * @return array|mixed
   *   An associative array of columns names.
   */
  public function getUserDefaultColumnNames(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE);

  /**
   * Get default submission columns used to display results.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   * @param bool $include_elements
   *   Flag that include all form element in the list of columns.
   *
   * @return array|mixed
   *   An associative array of columns keyed by name.
   */
  public function getDefaultColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE);

  /**
   * Get submission columns used to display results table.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   * @param bool $include_elements
   *   Flag that include all form element in the list of columns.
   *
   * @return array|mixed
   *   An associative array of columns keyed by name.
   */
  public function getColumns(Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL, AccountInterface $account = NULL, $include_elements = TRUE);

  /**
   * Get customize setting.
   *
   * @param string $name
   *   Custom settings name.
   * @param mixed $default
   *   Custom settings default value.
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   *
   * @return mixed
   *   Custom setting.
   */
  public function getCustomSetting($name, $default, Casestudy $casestudy = NULL, EntityInterface $source_entity = NULL);

  /****************************************************************************/
  // Invoke methods.
  /****************************************************************************/

  /**
   * Invoke a casestudy submission's casestudy's handlers method.
   *
   * @param string $method
   *   The casestudy handler method to be invoked.
   * @param \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission
   *   A casestudy submission.
   * @param mixed $context1
   *   (optional) An additional variable that is passed by reference.
   * @param mixed $context2
   *   (optional) An additional variable that is passed by reference.
   */
  public function invokeWebformHandlers($method, CasestudySubmissionInterface $casestudy_submission, &$context1 = NULL, &$context2 = NULL);

  /**
   * Invoke a casestudy submission's casestudy's elements method.
   *
   * @param string $method
   *   The casestudy element method to be invoked.
   * @param \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission
   *   A casestudy submission.
   * @param mixed $context1
   *   (optional) An additional variable that is passed by reference.
   * @param mixed $context2
   *   (optional) An additional variable that is passed by reference.
   */
  public function invokeWebformElements($method, CasestudySubmissionInterface $casestudy_submission, &$context1 = NULL, &$context2 = NULL);

  /****************************************************************************/
  // Purge methods.
  /****************************************************************************/

  /**
   * Purge casestudy submissions.
   *
   * @param int $count
   *   Amount of casestudy submissions to purge.
   */
  public function purge($count);

  /****************************************************************************/
  // Data handlers.
  /****************************************************************************/

  /**
   * Save casestudy submission data to the 'casestudy_submission_data' table.
   *
   * This method is public the allow casestudy handler (ie remote posts) to
   * update [casestudy:handler] tokens stored in the submission data.
   *
   * @param \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission
   *   A casestudy submission.
   * @param bool $delete_first
   *   TRUE to delete any data first. For new submissions this is not needed.
   *
   * @see \Drupal\webform\Plugin\WebformHandler\RemotePostWebformHandler::remotePost
   */
  public function saveData(CasestudySubmissionInterface $casestudy_submission, $delete_first = TRUE);

  /****************************************************************************/
  // Log methods.
  /****************************************************************************/

  /**
   * Write an event to the casestudy submission log.
   *
   * @param \Drupal\casestudy\CasestudySubmissionInterface $casestudy_submission
   *   A casestudy submission.
   * @param array $values
   *   The value to be logged includes 'handler_id', 'operation', 'message', and 'data'.
   */
  public function log(CasestudySubmissionInterface $casestudy_submission, array $values = []);

  /****************************************************************************/
  // Draft methods.
  /****************************************************************************/

  /**
   * Get casestudy submission draft.
   *
   * @param \Drupal\casestudy\Entity\Casestudy|null $casestudy
   *   A casestudy.
   * @param \Drupal\Core\Entity\EntityInterface|null $source_entity
   *   A casestudy submission source entity.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   A user account.
   *
   * @return \Drupal\casestudy\CasestudySubmissionInterface
   *   A casestudy submission.
   */
  public function loadDraft(Casestudy $casestudy, EntityInterface $source_entity = NULL, AccountInterface $account = NULL);

  /**
   * React to an event when a user logs in.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account that has just logged in.
   */
  public function userLogin(UserInterface $account);

}
