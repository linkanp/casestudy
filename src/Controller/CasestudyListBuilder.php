<?php

namespace Drupal\casestudy\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
#use Drupal\examples\Utility\DescriptionTemplateTrait;

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a listing of robot entities.
 *
 * List Controllers provide a list of entities in a tabular form. The base
 * class provides most of the rendering logic for us. The key functions
 * we need to override are buildHeader() and buildRow(). These control what
 * columns are displayed in the table, and how each row is displayed
 * respectively.
 *
 * Drupal locates the list controller by looking for the "list" entry under
 * "controllers" in our entity type's annotation. We define the path on which
 * the list may be accessed in our module's *.routing.yml file. The key entry
 * to look for is "_entity_list". In *.routing.yml, "_entity_list" specifies
 * an entity type ID. When a user navigates to the URL for that router item,
 * Drupal loads the annotation for that entity type. It looks for the "list"
 * entry under "controllers" for the class to load.
 *
 * @ingroup config_entity_example
 */
class CasestudyListBuilder extends ConfigEntityListBuilder {
  #use DescriptionTemplateTrait;

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'casestudy';
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   *
   * @see \Drupal\Core\Entity\EntityListController::render()
   */
  public function buildHeader() {
    $header['label'] = $this->t('Title');
    $header['path'] = $this->t('Path');
    $header['status'] = $this->t('Status');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to build the row.
   *
   * @return array
   *   A render array of the table row for displaying the entity.
   *
   * @see \Drupal\Core\Entity\EntityListController::render()
   */
  public function buildRow(EntityInterface $entity) {
    //$row['label'] = $entity->label();
    $row['label'] = $entity->toLink()->toString();
    $row['path'] = $entity->getURL();
    $row['status'] = ($entity->status)?'Enabled':'Disabled';
    $row['created'] = $entity->created;

    return $row + parent::buildRow($entity);
  }

 
    /**
     * {@inheritdoc}
     */
    public function getDefaultOperations(EntityInterface $entity, $type = 'edit') {
        /* @var $entity \Drupal\webform\WebformInterface */
        $route_parameters = ['casestudy' => $entity->id()];

            $operations = parent::getDefaultOperations($entity);
//            if ($entity->access('view')) {
//                $operations['view'] = [
//                    'title' => $this->t('View'),
//                    'weight' => 20,
//                    'url' => Url::fromRoute('entity.webform.canonical', $route_parameters),
//                ];
//            }

            //if ($entity->access('duplicate')) {
                $operations['settings'] = [
                    'title' => $this->t('Settings'),
                    'weight' => 23,
                    'url' => Url::fromRoute('entity.casestudy.settings_form', $route_parameters),
                    //'attributes' => WebformDialogHelper::getModalDialogAttributes(700),
                ];
            //}


        return $operations;
    }

}
