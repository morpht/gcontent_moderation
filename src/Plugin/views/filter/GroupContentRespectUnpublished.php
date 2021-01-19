<?php

namespace Drupal\gcontent_moderation\Plugin\views\filter;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
   * The group storage.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $groupStorage;

  /**
   * Constructs the Gid object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param ContentEntityStorageInterface $group_storage
   *   The group entity storage handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentEntityStorageInterface $group_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupStorage = $group_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('group')
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
    $groupIdPlugin = $argument['gid'];
    if ($groupIdPlugin->getPluginId() === 'group_id') {
      $argPos = $groupIdPlugin->position;
      $groupId = $this->view->args[$argPos];
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

    /** @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.group_content_enabler');
    $nodeTypes = [];
    /** @var \Drupal\gnode\Plugin\GroupContentEnabler\GroupNode $plugin */
    foreach ($plugin_manager->getAll() as $plugin) {
      $pluginDefinition = $plugin->getPluginDefinition();
      if ($pluginDefinition['entity_type_id'] === 'node') {
        $nodeTypes[] = $pluginDefinition['entity_bundle'];
      }
    }

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
      // @todo check for 'view latest version' as well?
      $snippet .= "(($table.uid = ***CURRENT_USER*** AND group_content_field_data_node_field_data.type='group-group_node-$nodeType' AND :own_unpublished_$nodeType) OR
      (group_content_field_data_node_field_data.type='group-group_node-$nodeType' AND :all_unpublished_$nodeType))";
      $args[':own_unpublished_' . $nodeType] = $group->hasPermission("view own unpublished group_node:$nodeType entity", $account);
      $args[':all_unpublished_' . $nodeType] = $group->hasPermission("view unpublished group_node:$nodeType entity", $account);;
    }
    $this->query->addWhereExpression(
      0,
      $snippet, $args);

  }

}
