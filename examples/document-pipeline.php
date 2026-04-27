<?php

/**
 * Example: Document Processing Pipeline
 * 
 * This demonstrates a real-world document processing workflow
 * with validation, OCR processing, and approval
 */

require_once __DIR__ . '/../src/StateMachine.php';

// Helper functions (simuladas)
function log(string $message) { echo "[LOG] $message\n"; }
function isValidDocument($doc) { return true; }
function notifyApproval($ctx) { echo "✓ Document approved!\n"; }
function dataConfidence($ctx) { return 0.98; }
function ocrHasError() { return false; }

$pipeline = new StateMachine('received');

$pipeline->addState('received', [
    'entry' => fn($ctx) => log("Document received: {$ctx['doc']}")
])
->addState('validating')
->addState('ocr_processing', [
    'retryPolicy' => ['max' => 3, 'delay' => 5000]
])
->addState('extracting_data')
->addState('approved', [
    'entry' => fn($ctx) => notifyApproval($ctx)
])
->addState('rejected')
->addTransition('received', 'validating')
->addTransition('validating', 'ocr_processing', 
    fn($ctx) => isValidDocument($ctx['doc']))
->addTransition('validating', 'rejected',
    fn($ctx) => !isValidDocument($ctx['doc']))
->addTransition('ocr_processing', 'extracting_data')
->addTransition('extracting_data', 'approved')
->addGuard('approved', fn($ctx, $history) => 
    dataConfidence($ctx) > 0.95);

// Usage with rollback
try {
    $document = ['id' => 123, 'name' => 'contract.pdf'];
    
    $pipeline->transition('validating', ['doc' => $document]);
    echo "Current state: {->exportConfig()['currentState']}\n";
    
    $pipeline->transition('ocr_processing', ['doc' => $document]);
    echo "Current state: {->exportConfig()['currentState']}\n";
    
    if (ocrHasError()) {
        $pipeline->rollback();
        echo "Rollback executed!\n";
    }
    
    $pipeline->transition('extracting_data', ['doc' => $document]);
    
    echo "\n" . $pipeline->visualizeFlow() . "\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    $pipeline->rollback();
}
