<?php

/**
 * StateMachine - A Declarative State Machine with Conditional Transitions, History & Rollback
 * 
 * @author dwight-trujillo
 * @license MIT
 */

class StateMachine {
    private string $currentState;
    private array $states = [];
    private array $transitions = [];
    private array $history = [];
    private array $guards = [];
    private array $beforeHooks = [];
    private array $afterHooks = [];
    private array $rollbackSnapshots = [];
    private array $children = [];
    
    public function __construct(string $initialState) {
        $this->currentState = $initialState;
        $this->states[$initialState] = [
            'data' => [],
            'allowedTransitions' => []
        ];
    }
    
    public function addState(string $state, array $config = []): self {
        $this->states[$state] = array_merge([
            'data' => [],
            'entry' => null,
            'exit' => null,
            'timeout' => null,
            'retryPolicy' => ['max' => 0, 'delay' => 0]
        ], $config);
        return $this;
    }
    
    // NOTA: Completa con todo el código de la clase aquí
}
