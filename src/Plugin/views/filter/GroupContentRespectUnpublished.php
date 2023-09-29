<?php

namespace Drupal\gcontent_moderation\Plugin\views\filter;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Respect 'own' and 'any' unpublished permission for group content (node).
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("group_content_respect_unpublished")
 */
class GroupContentRespectUnpublished extends FilterPluginBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $groupStorage;

  /**
   * @var \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface
   */
  protected $groupRelationTypeManager;

  /**
   * Constructs the Gid object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param ContentEntityStorageInterface $group_storage
   *   The group entity storage handler.
   * @param GroupRelationTypeManagerInterface $group_relation_type_manager
   *   The group content enabler plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ContentEntityStorageInterface $group_storage, GroupRelationTypeManagerInterface $group_relation_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->groupStorage = $group_storage;
    $this->groupRelationTypeManager = $group_relation_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('group'),
      $container->get('group_relation_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $groupId = NULL;
    $argument = $this->view->argument;

    if (!array_key_exists('gid', $argument)) {
      return;
    }

    /** @var \Drupal\group\Plugin\views\argument\GroupId $groupIdPlugin */
    $groupId = NULL;
    $groupIdPlugin = $argument['gid'];
    if ($groupIdPlugin->getPluginId() === 'group_id') {
      $argPos = $groupIdPlugin->position;
      if (isset($this->view->args[$argPos])) {
        $groupId = $this->view->args[$argPos];
      }
    }

    if (!$groupId) {
      $this->query->addWhereExpression(
        0,
        "1 != 1");
      return;
    }

    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $this->groupStorage->load($groupId);
    if (!$group) {
      $this->query->addWhereExpression(
        0,
        "1 != 1");
      return;
    }

    $nodeTypes = [];
    foreach ($this->groupRelationTypeManager->getDefinitions() as $pluginDefinition) {
      if ($pluginDefinition['entity_type_id'] === 'node') {
        $nodeTypes[] = $pluginDefinition['entity_bundle'];
      }
    }

    // Since there is no content types for this group we don't need to do
    // anything. Also we shouldn't add any filtering because of that.
    if (empty($nodeTypes)) {
      return;
    }

    $table = 'node_field_revision';
    $account = $this->view->getUser();

    $snippet = "";
    $args = [];
    foreach ($nodeTypes as $nodeType) {
      if ($snippet !== "") {
        $snippet .= " OR ";
      }
      $snippet .= "
        (($table.uid = ***CURRENT_USER*** AND ***CURRENT_USER*** <> 0 AND group_relationship_field_data_node_field_data.type='group-group_node-$nodeType' AND :own_unpublished_$nodeType)
        OR
        (group_relationship_field_data_node_field_data.type='group-group_node-$nodeType' AND :all_unpublished_$nodeType))";
      $args[':own_unpublished_' . $nodeType] = $group->hasPermission("view own unpublished group_node:$nodeType entity", $account) && $group->hasPermission("view latest version", $account);
      $args[':all_unpublished_' . $nodeType] = $group->hasPermission("view unpublished group_node:$nodeType entity", $account) && $group->hasPermission("view latest version", $account);
    }
    $snippet .= "OR ***BYPASS_NODE_ACCESS*** = 1";
    $this->query->addWhereExpression(
      0,
      $snippet, $args);

  }

}
