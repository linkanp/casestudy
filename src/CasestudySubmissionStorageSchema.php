<?php

namespace Drupal\casestudy;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the webform submission schema handler.
 */
class CasestudySubmissionStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset = FALSE);

    $schema['casestudy_submission_data'] = [
      'description' => 'Stores all submitted data for casestudy submissions.',
      'fields' => [
        'casestudy_id' => [
          'description' => 'The casestudy id.',
          'type' => 'varchar',
          'length' => 32,
          'not null' => TRUE,
        ],
        'sid' => [
          'description' => 'The unique identifier for this submission.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'name' => [
          'description' => 'The name of the element.',
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
        ],
        'property' => [
          'description' => "The property of the element's value.",
          'type' => 'varchar',
          'length' => 128,
          'not null' => TRUE,
          'default' => '',
        ],
        'delta' => [
          'description' => "The delta of the element's value.",
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'value' => [
          'description' => "The element's value.",
          'type' => 'text',
          'size' => 'medium',
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['sid', 'name', 'property', 'delta'],
      'indexes' => [
        'casestudy_id' => ['casestudy_id'],
        'sid_casestudy_id' => ['sid', 'casestudy_id'],
      ],
    ];

    $schema['casestudy_submission_log'] = [
      'description' => 'Table that contains logs of all casestudy submission events.',
      'fields' => [
        'lid' => [
          'type' => 'serial',
          'not null' => TRUE,
          'description' => 'Primary Key: Unique log event ID.',
        ],
        'casestudy_id' => [
          'description' => 'The casestudy id.',
          'type' => 'varchar',
          'length' => 32,
          'not null' => TRUE,
        ],
        'sid' => [
          'description' => 'The casestudy submission id.',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
        'handler_id' => [
          'description' => 'The casestudy handler id.',
          'type' => 'varchar',
          'length' => 64,
          'not null' => FALSE,
        ],
        'uid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
          'description' => 'The {users}.uid of the user who triggered the event.',
        ],
        'operation' => [
          'type' => 'varchar_ascii',
          'length' => 64,
          'not null' => TRUE,
          'default' => '',
          'description' => 'Type of operation, for example "save", "sent", or "update."',
        ],
        'message' => [
          'type' => 'text',
          'not null' => TRUE,
          'size' => 'big',
          'description' => 'Text of log message.',
        ],
        'data' => [
          'type' => 'blob',
          'not null' => TRUE,
          'size' => 'big',
          'description' => 'Serialized array of data.',
        ],
        'timestamp' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Unix timestamp of when event occurred.',
        ],
      ],
      'primary key' => ['lid'],
      'indexes' => [
        'casestudy_id' => ['casestudy_id'],
        'sid' => ['sid'],
        'uid' => ['uid'],
        'handler_id' => ['handler_id'],
        'handler_id_operation' => ['handler_id', 'operation'],
      ],
    ];

      $schema['casestudy_start'] = [
          'description' => 'Table for storing casestudy start history',
          'fields' => [
              'id' => [
                  'type' => 'serial',
                  'not null' => TRUE,
                  'description' => 'Primary Key: Unique ID.',
              ],
              'sid' => [
                  'description' => 'The casestudy submission id.',
                  'type' => 'int',
                  'unsigned' => TRUE,
                  'not null' => FALSE,
              ],
              'casestudy_id' => [
                  'description' => 'The casestudy id.',
                  'type' => 'varchar',
                  'length' => 32,
                  'not null' => TRUE,
              ],
              'user_ip' => [
                  'description' => 'unique ID for survey.',
                  'type' => 'varchar',
                  'length' => 255,
                  'not null' => TRUE,
              ],
              'date_start' => [
                  'description' => 'last answered Date',
                  'mysql_type' => 'DATETIME',
                  'not null' => TRUE,
              ],
              'status' => ['type' => 'int', 'size' => 'tiny', 'default' => 0, 'not null' => TRUE],
          ],
          'primary key' => ['id'],
      ];
      $schema['casestudy_visit_status'] = [
          'description' => 'Table for storing Visit Data',
          'fields' => [
              'id' => [
                  'description' => 'The identifier of a visit.',
                  'not null' => TRUE,
                  'type' => 'serial',
              ],
              'start_id' => [
                  'description' => 'ID from case study start table',
                  'type' => 'int',
                  'size' => 'big',
                  'not null' => TRUE,
              ],
              'sid' => [
                  'description' => 'The casestudy submission id.',
                  'type' => 'int',
                  'unsigned' => TRUE,
                  'not null' => FALSE,
              ],
              'casestudy_id' => [
                  'description' => 'The casestudy id.',
                  'type' => 'varchar',
                  'length' => 32,
                  'not null' => TRUE,
              ],
              'element_id' => [
                  'description' => 'The element(Question or HTML page) id.',
                  'type' => 'varchar',
                  'length' => 32,
                  'not null' => TRUE,
              ],
              'visited' => ['type' => 'int', 'size' => 'tiny', 'default' => 0, 'not null' => TRUE],
              'submitted' => ['type' => 'int', 'size' => 'tiny', 'default' => 0, 'not null' => TRUE],

          ],
          'primary key' => ['id']

      ];
    return $schema;
  }

}
