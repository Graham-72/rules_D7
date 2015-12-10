<?php

/**
 * @file
 * Contains \Drupal\rules\Tests\NodeIntegrationTest.
 */

namespace Drupal\Tests\rules\Kernel;

use Drupal\rules\Context\ContextConfig;
use Drupal\rules\Context\ContextDefinition;
use Drupal\rules\Engine\RulesComponent;

/**
 * Test using the Rules API with nodes.
 *
 * @group rules
 */
class NodeIntegrationTest extends RulesDrupalTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'field', 'text', 'user'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
  }

  /**
   * Tests that a complex data selector can be applied to nodes.
   */
  public function testNodeDataSelector() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    $node = $entity_type_manager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);

    $user = $entity_type_manager->getStorage('user')
      ->create([
        'name' => 'test value',
      ]);

    $user->save();
    $node->setOwner($user);

    $rule = $this->expressionManager->createRule();

    // Test that the long detailed data selector works.
    $rule->addCondition('rules_test_string_condition', ContextConfig::create()
      ->map('text', 'node:uid:0:entity:name:0:value')
    );

    // Test that the shortened data selector without list indices.
    $rule->addCondition('rules_test_string_condition', ContextConfig::create()
      ->map('text', 'node:uid:entity:name:value')
    );

    $rule->addAction('rules_test_log');

    RulesComponent::create($rule)
      ->addContextDefinition('node', ContextDefinition::create('entity:node'))
      ->setContextValue('node', $node)
      ->execute();
  }

  /**
   * Tests that a node is automatically saved after being changed in an action.
   */
  public function testNodeAutoSave() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    $node = $entity_type_manager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);

    // We use the rules_test_node action plugin which marks its node context for
    // auto saving.
    // @see \Drupal\rules_test\Plugin\RulesAction\TestNodeAction
    $action = $this->expressionManager->createAction('rules_test_node')
      ->setConfiguration(ContextConfig::create()
        ->map('node', 'node')
        ->map('title', 'title')
        ->toArray()
      );

    RulesComponent::create($action)
      ->addContextDefinition('node', ContextDefinition::create('entity:node'))
      ->addContextDefinition('title', ContextDefinition::create('string'))
      ->setContextValue('node', $node)
      ->setContextValue('title', 'new title')
      ->execute();
    $this->assertNotNull($node->id(), 'Node ID is set, which means that the node has been saved.');
  }

  /**
   * Tests that tokens in action parameters get replaced.
   */
  public function testTokenReplacements() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    $node = $entity_type_manager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);

    $user = $entity_type_manager->getStorage('user')
      ->create([
        'name' => 'klausi',
      ]);

    $user->save();
    $node->setOwner($user);

    // Configure a simple rule with one action.
    $action = $this->expressionManager->createInstance('rules_action',
      ContextConfig::create()
        ->map('message', 'message')
        ->map('type', 'type')
        ->process('message', 'rules_tokens')
        ->setConfigKey('action_id', 'rules_system_message')
        ->toArray()
    );

    $rule = $this->expressionManager->createRule()
      ->addExpressionObject($action);

    RulesComponent::create($rule)
      ->addContextDefinition('node', ContextDefinition::create('entity:node'))
      ->addContextDefinition('message', ContextDefinition::create('string'))
      ->addContextDefinition('type', ContextDefinition::create('string'))
      ->setContextValue('node', $node)
      ->setContextValue('message', 'Hello [node:uid:entity:name:value]!')
      ->setContextValue('type', 'status')
      ->execute();

    $messages = drupal_set_message();
    $this->assertEquals((string) $messages['status'][0], 'Hello klausi!');
  }

  /**
   * Tests that date formatting tokens on node fields get replaced.
   */
  public function testDateTokens() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    $node = $entity_type_manager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
        // Set the created date to the first second in 1970.
        'created' => 1,
      ]);

    // Configure a simple rule with one action.
    $action = $this->expressionManager->createInstance('rules_action',
      ContextConfig::create()
        ->map('message', 'message')
        ->map('type', 'type')
        ->process('message', 'rules_tokens')
        ->setConfigKey('action_id', 'rules_system_message')
        ->toArray()
    );

    $rule = $this->expressionManager->createRule()
      ->addExpressionObject($action);

    RulesComponent::create($rule)
      ->addContextDefinition('node', ContextDefinition::create('entity:node'))
      ->addContextDefinition('message', ContextDefinition::create('string'))
      ->addContextDefinition('type', ContextDefinition::create('string'))
      ->setContextValue('node', $node)
      ->setContextValue('message', 'The node was created in the year [node:created:custom:Y]')
      ->setContextValue('type', 'status')
      ->execute();

    $messages = drupal_set_message();
    $this->assertEquals((string) $messages['status'][0], 'The node was created in the year 1970');
  }

  /**
   * Tests that the data set action works on nodes.
   */
  public function testDataSet() {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    $node = $entity_type_manager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);

    // Configure a simple rule with one action.
    $action = $this->expressionManager->createInstance('rules_action',
      ContextConfig::create()
        ->setConfigKey('action_id', 'rules_data_set')
        ->map('data', 'node:title')
        ->map('value', 'new_title')
        ->toArray()
    );

    $rule = $this->expressionManager->createRule()
      ->addExpressionObject($action);

    RulesComponent::create($rule)
      ->addContextDefinition('node', ContextDefinition::create('entity:node'))
      ->addContextDefinition('new_title', ContextDefinition::create('string'))
      ->setContextValue('node', $node)
      ->setContextValue('new_title', 'new title')
      ->execute();

    $this->assertEquals('new title', $node->getTitle());
    $this->assertNotNull($node->id(), 'Node ID is set, which means that the node has been auto-saved.');
  }

}
