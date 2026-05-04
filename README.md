<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge">
  <img src="https://img.shields.io/badge/Status-Production%20Ready-brightgreen?style=for-the-badge">
  <img src="https://img.shields.io/badge/Dependencies-Zero-blue?style=for-the-badge">
</p>

<h1 align="center">PHP StateMachine</h1>

<p align="center">
  <strong>The most advanced declarative state machine engine ever built for PHP.</strong><br>
  Transactional rollback &bull; Hierarchical states &bull; Visual flow diagrams &bull; Zero dependencies
</p>

---

## What Makes This Different

| Capability | This Library | Symfony | Laravel | XState |
|---|---|---|---|---|
| Transactional Rollback | Yes | No | No | No |
| Snapshot Isolation | Yes | No | No | No |
| Hierarchical Child Machines | Yes | No | No | Yes |
| Guards + Conditions | Yes | No | No | Yes |
| Mermaid Visualization | Yes | Graphviz | No | Yes |
| Audit History | Yes | No | Yes | Yes |
| Retry Policies | Yes | No | No | No |
| Zero Dependencies | Yes | No | No | No |

### Transactional Rollback

```php
try {
    $pipeline->transition('payment_processing', $context);
    $pipeline->transition('inventory_reservation', $context);
    if ($inventoryUnavailable) {
        $pipeline->rollback();
        $pipeline->rollback();
    }
} catch (Exception $e) {
    while ($pipeline->canRollback()) {
        $pipeline->rollback();
    }
}
Quick Start
Installation
bash
git clone https://github.com/dwight-trujillo/php-state-machine.git
composer require dwight-trujillo/php-state-machine
Basic Usage
php
$workflow = new StateMachine('draft');

$workflow
    ->addState('draft')
    ->addState('review')
    ->addState('published', ['entry' => fn($ctx) => notify($ctx['user'])])
    ->addTransition('draft', 'review')
    ->addTransition('review', 'published')
    ->addGuard('published', fn($ctx) => $ctx['user']->isAdmin());

$workflow->transition('review', ['user' => $currentUser]);
echo $workflow->visualizeFlow();
Core Features
States with Lifecycle Hooks
php
$sm->addState('processing', [
    'entry' => fn($ctx) => startProcess($ctx),
    'exit' => fn($ctx) => cleanup($ctx),
    'timeout' => 300,
    'retryPolicy' => ['max' => 3, 'delay' => 2000]
]);
Guards vs Conditions
php
$sm->addGuard('published', fn($ctx) => $ctx['user']->role === 'admin');
$sm->addTransition('review', 'published', fn($ctx) => qualityCheck($ctx) > 0.95);
Hierarchical Machines
php
$paymentFlow = new StateMachine('idle');
$paymentFlow->addState('charging')->addState('completed');
$paymentFlow->addTransition('idle', 'charging')->addTransition('charging', 'completed');

$orderFlow = new StateMachine('cart');
$orderFlow->addChildState('checkout', $paymentFlow);
Real-World Example: E-Commerce
php
$order = new StateMachine('cart');

$order
    ->addState('cart')
    ->addState('checkout')
    ->addState('paid', ['entry' => fn($ctx) => sendReceipt($ctx['email'])])
    ->addState('shipped')
    ->addState('delivered')
    ->addState('returned')
    ->addState('refunded')
    ->addState('cancelled')
    ->addTransition('cart', 'checkout', fn($ctx) => count($ctx['items']) > 0)
    ->addTransition('checkout', 'paid')
    ->addTransition('paid', 'shipped')
    ->addTransition('shipped', 'delivered')
    ->addTransition('delivered', 'returned')
    ->addTransition('returned', 'refunded')
    ->addTransition('cart', 'cancelled')
    ->addTransition('checkout', 'cancelled')
    ->addGuard('refunded', fn($ctx) => $ctx['user']->isVerified());
Visualization
php
echo $order->visualizeFlow();
Output:

text
graph TD
    cart --> checkout
    checkout --> paid
    checkout --> cancelled
    paid --> shipped
    shipped --> delivered
    delivered --> returned
    returned --> refunded
    cart --> cancelled
    style checkout fill:#f96
API Reference
Method	Description
addState(name, config)	Add state with callbacks and policies
addTransition(from, to, condition)	Define valid transition
addGuard(state, callable)	Protect state entry
addChildState(parent, child)	Attach hierarchical child
transition(to, context)	Execute transition
rollback()	Undo last transition
visualizeFlow()	Generate Mermaid diagram
exportConfig()	Export config and history
License
MIT License - Copyright (c) 2025 Dwight Trujillo

<p align="center"> <strong>Built by <a href="https://github.com/dwight-trujillo">Dwight Trujillo</a></strong> </p> ```
