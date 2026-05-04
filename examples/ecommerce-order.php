<?php

/**
 * Example: E-Commerce Order Lifecycle
 * 
 * Demonstrates a complete order flow with states, transitions, guards, and rollback.
 */

require_once __DIR__ . '/../src/StateMachine.php';

// Simulated functions
function validateCart(array $items): bool { return count($items) > 0; }
function processPayment(array $payment): bool { return true; }
function sendReceipt(string $email): void { echo "Receipt sent to $email\n"; }
function notifyCarrier(string $tracking): void { echo "Carrier notified: $tracking\n"; }
function issueRefund(int $orderId): bool { echo "Refund issued for order #$orderId\n"; return true; }

// Create the order state machine
$order = new StateMachine('cart');

$order
    ->addState('cart')
    ->addState('checkout', [
        'entry' => fn($ctx) => print("Validating cart for order #{$ctx['order_id']}...\n")
    ])
    ->addState('payment_pending')
    ->addState('paid', [
        'entry' => fn($ctx) => sendReceipt($ctx['email'])
    ])
    ->addState('fulfilled')
    ->addState('shipped', [
        'entry' => fn($ctx) => notifyCarrier($ctx['tracking'])
    ])
    ->addState('delivered')
    ->addState('returned')
    ->addState('refunded')
    ->addState('cancelled')
    // Happy path transitions
    ->addTransition('cart', 'checkout', fn($ctx) => validateCart($ctx['items']))
    ->addTransition('checkout', 'payment_pending')
    ->addTransition('payment_pending', 'paid', fn($ctx) => processPayment($ctx['payment']))
    ->addTransition('paid', 'fulfilled')
    ->addTransition('fulfilled', 'shipped')
    ->addTransition('shipped', 'delivered')
    // Return and refund flow
    ->addTransition('delivered', 'returned', fn($ctx) => $ctx['within_return_window'] ?? false)
    ->addTransition('returned', 'refunded', fn($ctx) => issueRefund($ctx['order_id']))
    // Cancellation possible from multiple states
    ->addTransition('cart', 'cancelled')
    ->addTransition('checkout', 'cancelled')
    ->addTransition('payment_pending', 'cancelled')
    // Guards
    ->addGuard('refunded', fn($ctx) => ($ctx['user_verified'] ?? false) === true)
    // Audit hook
    ->onAfterTransition(function($from, $to, $ctx) {
        echo "[AUDIT] Order #{$ctx['order_id']}: $from → $to\n";
    });

// Execute the flow
echo "=== E-Commerce Order Flow ===\n\n";

$context = [
    'order_id' => 12345,
    'email' => 'customer@example.com',
    'items' => ['item1', 'item2'],
    'payment' => ['method' => 'credit_card'],
    'tracking' => 'TRACK-987654',
    'user_verified' => true
];

try {
    $order->transition('checkout', $context);
    echo "Current: {$order->getCurrentState()}\n\n";
    
    $order->transition('payment_pending', $context);
    echo "Current: {$order->getCurrentState()}\n\n";
    
    $order->transition('paid', $context);
    echo "Current: {$order->getCurrentState()}\n\n";
    
    $order->transition('fulfilled', $context);
    echo "Current: {$order->getCurrentState()}\n\n";
    
    $order->transition('shipped', $context);
    echo "Current: {$order->getCurrentState()}\n\n";
    
    $order->transition('delivered', $context);
    echo "Current: {$order->getCurrentState()}\n\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    $order->rollback();
}

// Show flow diagram
echo "\n=== Flow Diagram ===\n";
echo $order->visualizeFlow() . "\n";

// Show history
echo "\n=== Transition History ===\n";
foreach ($order->getHistory() as $entry) {
    echo date('H:i:s', (int)$entry['timestamp']) . " | {$entry['from']} → {$entry['to']}\n";
}