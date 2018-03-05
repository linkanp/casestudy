<?php

/**
 * @file
 * Preprocessors and helper functions to make theming easier.
 */


/******************************************************************************/
// Progress templates.
/******************************************************************************/

/**
 * Prepares variables for casestudy 'wizard' progress template.
 *
 * Default template: casestudy-display.html.twig.
 *
 * @param array $variables
 *   An associative array containing the following key:
 *   - casestudy: A casestudy.
 *   - current_page: The current wizard page.
 */
function template_preprocess_casestudy_display(array &$variables) {

  /** @var \Drupal\casestudy\Entity\Casestudy $casestudy*/
  $casestudy = $variables['casestudy'];
  $getCookieData = Drupal\casestudy\Utility\GeneralHelper::decryptCookieData($casestudy->getCookieName());
  //pirnt_r($getCookieData);
  //exit;
  $connection = Drupal\Core\Database\Database::getConnection();

  //$current_page = $variables['current_page'];

  $pages = $casestudy->getPages();

  $page_keys = array_keys($pages);
  $page_indexes = array_flip($page_keys);
  //$current_index = $page_indexes[$current_page];

  //$total = count($page_keys);

  $legend_content = [];
    foreach ($pages as $k => $page) {

        $visit_data = $connection->select('casestudy_visit_status', 'sd')
            ->fields('sd', ['visited','submitted'])
            ->condition('sd.casestudy_id', $casestudy->id())
            ->condition('sd.element_id', $k)
            //->condition('sd.sid', $getCookieData['submission_id'])
            ->condition('sd.start_id', $getCookieData['start_id'])
            ->execute()->fetch();
        //print_r($visit_data);
        $class = '';
        if($visit_data->visited){
            $class = 'visited';
        }
        if($visit_data->submitted){
            $class = 'answered';
        }
        $elementObj = $casestudy->getElement($k);


        $title = (isset($page['title'])) ? $page['title'] : $elementObj->label;
        $link = '/casestudy/'.$casestudy->id().'/page/'.$k;
        $legend_content[] = [
            'title' => $title,
            'link' => $link,
            'class' => $class,
        ];
    }


    $variables['casestudy_legend'] = [
      '#theme' => 'casestudy_legend',
      '#casestudy' => $variables['casestudy'],
      '#current_page' => null,
      '#legend_content' => $legend_content,
    ];

   // print_r($variables);


}


/**
 * Prepares variables for casestudy 'wizard' progress template.
 *
 * Default template: casestudy-display.html.twig.
 *
 * @param array $variables
 *   An associative array containing the following key:
 *   - casestudy: A casestudy.
 *   - current_page: The current wizard page.
 */
function template_preprocess_quiz_display(array &$variables) {

}



/**
 * Prepares variables for casestudy 'wizard' progress tracker template.
 *
 * Default template: casestudy-progress-tracker.html.twig.
 *
 * @param array $variables
 *   An associative array containing the following key:
 *   - casestudy: A casestudy.
 *   - current_page: The current wizard page.
 */
function template_preprocess_casestudy_progress_tracker(array &$variables) {

  _template_preprocess_casestudy_progress($variables);
}

/**
 * Prepares variables for casestudy 'wizard' progress bar & tracker template.
 *
 * @param array $variables
 *   An associative array containing the following key:
 *   - casestudy: A casestudy.
 *   - current_page: The current wizard page.
 */
function _template_preprocess_casestudy_progress(array &$variables) {
  /** @var \Drupal\casestudy\Entity\Casestudy $casestudy */
  $casestudy = $variables['casestudy'];
  $current_page = $variables['current_page'];

  $pages = $casestudy->getPages();
//  print_r($pages);
//  exit;


  $page_keys = array_keys($pages);
  $page_indexes = array_flip($page_keys);
  //print_r($page_indexes);
  $current_index = $page_indexes[$current_page];
  $variables['current_index'] = $current_index;

  // Reset the pages variable.
  $variables['progress'] = [];
  foreach ($pages as $k => $page) {
      //$elementObj = $casestudy->getElement($k);

      if(empty($page['title'])){
          if($k == 'last_question_result'){
              $page['title'] = 'Q'.$page_indexes[$k].' Result';
          } else {
              $page['title'] = 'Q'.($page_indexes[$k]+1);
          }
      }
      $variables['progress'][] = $page['title'];
  }
}
