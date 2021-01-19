<?php

/**
 * @file
 * Provide views data for gcontent_moderation.module.
 *
 * @ingroup views_module_handlers
 */

/**
 * Implements hook_views_data_alter().
 */
function gcontent_moderation_views_data_alter(&$data) {
  $data['node_revision']['group_content_respect_unpublished'] = [
    'title' => t('Respect group content unpublished permission'),
    'help' => t("Respect 'own' and 'any' unpublished permission for group content (node)"),
    'filter' => [
      'table' => 'group_content_field_data_node_field_data',
      'field' => 'uid',
      'id' => 'group_content_respect_unpublished',
      'title' => t('Respect group content unpublished permission'),
      'no group by' => TRUE,
    ],
  ];
  return $data;
}
