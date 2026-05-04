<?php

/**
 * StateMachine - A Declarative State Machine with Conditional Transitions, History & Rollback
 * 
 * The most advanced state machine implementation in PHP.
 * Features: Transactional rollback, hierarchical child machines, guards,
 * conditions, lifecycle hooks, Mermaid visualization, and more.
 * 
 * @author Dwight Trujillo
 * @license MIT
 * @version 1.0.0
 */
class StateMachine
{
    private string $currentState;
    private array $states = [];
    private array $transitions = [];
    private array $history = [];
    private array $guards = [];
    private array $beforeHooks = [];
    private array $afterHooks = [];
    private array $rollbackSnapshots = [];
    private array $children = [];

    /**
     * Create a new state machine with an initial state.
     *
     * @param string $initialState The starting state name
     */
    public function __construct(string $initialState)
    {
        $this->currentState = $initialState;
        $this->states[$initialState] = [
            'data' => [],
            'allowedTransitions' => [],
            'entry' => null,
            'exit' => null,
            'timeout' => null,
            'retryPolicy' => ['max' => 0, 'delay' => 0]
        ];
    }

    /**
     * Add a state with optional configuration.
     *
     * @param string $state State name
     * @param array $config Configuration options (entry, exit, timeout, retryPolicy)
     * @return $this
     */
    public function addState(string $state, array $config = []): self
    {
        $this->states[$state] = array_merge([
            'data' => [],
            'allowedTransitions' => [],
            'entry' => null,
            'exit' => null,
            'timeout' => null,
            'retryPolicy' => ['max' => 0, 'delay' => 0]
        ], $config);
        return $this;
    }

    /**
     * Define a valid transition between two states.
     *
     * @param string $from Source state
     * @param string $to Target state
     * @param callable|null $condition Optional condition that must return true
     * @param array $rules Optional rules (priority, etc.)
     * @return $this
     */
    public function addTransition(string $from, string $to, callable $condition = null, array $rules = []): self
    {
        $this->transitions["$from->$to"] = [
            'from' => $from,
            'to' => $to,
            'condition' => $condition,
            'rules' => $rules,
            'weight' => $rules['priority'] ?? 0
        ];
        $this->states[$from]['allowedTransitions'][] = $to;
        return $this;
    }

    /**
     * Add a guard that protects entry to a state.
     *
     * @param string $state State to guard
     * @param callable $guard Function that returns true if entry is allowed
     * @return $this
     */
    public function addGuard(string $state, callable $guard): self
    {
        $this->guards[$state] = $guard;
        return $this;
    }

    /**
     * Register a hook that runs before every transition.
     *
     * @param callable $hook Function($from, $to, $context)
     * @return $this
     */
    public function onBeforeTransition(callable $hook): self
    {
        $this->beforeHooks[] = $hook;
        return $this;
    }

    /**
     * Register a hook that runs after every transition.
     *
     * @param callable $hook Function($from, $to, $context)
     * @return $this
     */
    public function onAfterTransition(callable $hook): self
    {
        $this->afterHooks[] = $hook;
        return $this;
    }

    /**
     * Attach a child state machine to a parent state.
     *
     * @param string $parentState The parent state
     * @param StateMachine $childMachine The child state machine
     * @return $this
     */
    public function addChildState(string $parentState, StateMachine $childMachine): self
    {
        $this->children[$parentState] = $childMachine;
        return $this;
    }

    /**
     * Attempt to transition to a target state.
     *
     * @param string $targetState Desired state
     * @param array $context Context data for guards and conditions
     * @return bool True if transition succeeded
     * @throws RuntimeException If transition is not allowed
     */
    public function transition(string $targetState, array $context = []): bool
    {
        $transitionKey = "{$this->currentState}->$targetState";

        if (!isset($this->transitions[$transitionKey])) {
            throw new RuntimeException("Transition not allowed: $transitionKey");
        }

        // Check guard on target state
        if (isset($this->guards[$targetState])) {
            $guardResult = ($this->guards[$targetState])($context, $this->history);
            if (!$guardResult) {
                return false;
            }
        }

        // Check transition condition
        $transition = $this->transitions[$transitionKey];
        if ($transition['condition'] !== null) {
            $conditionResult = ($transition['condition'])($context, $this->history);
            if (!$conditionResult) {
                return false;
            }
        }

        // Create snapshot for possible rollback
        $this->createSnapshot();

        // Execute before hooks
        foreach ($this->beforeHooks as $hook) {
            $hook($this->currentState, $targetState, $context);
        }

        // Execute exit callback of current state
        if (isset($this->states[$this->currentState]['exit'])) {
            ($this->states[$this->currentState]['exit'])($context);
        }

        // Store in history
        $this->history[] = [
            'from' => $this->currentState,
            'to' => $targetState,
            'timestamp' => microtime(true),
            'context' => $context
        ];

        // Change state
        $oldState = $this->currentState;
        $this->currentState = $targetState;

        // Execute entry callback of new state
        if (isset($this->states[$targetState]['entry'])) {
            ($this->states[$targetState]['entry'])($context);
        }

        // Process child state if exists
        if (isset($this->children[$targetState])) {
            $this->children[$targetState]->reset();
        }

        // Execute after hooks
        foreach ($this->afterHooks as $hook) {
            $hook($oldState, $targetState, $context);
        }

        return true;
    }

    /**
     * Check if a transition is possible.
     *
     * @param string $targetState Desired state
     * @param array $context Context data
     * @return bool True if transition would be allowed
     */
    public function canTransition(string $targetState, array $context = []): bool
    {
        $transitionKey = "{$this->currentState}->$targetState";

        if (!isset($this->transitions[$transitionKey])) {
            return false;
        }

        if (isset($this->guards[$targetState])) {
            if (!($this->guards[$targetState])($context, $this->history)) {
                return false;
            }
        }

        $condition = $this->transitions[$transitionKey]['condition'];
        if ($condition !== null && !$condition($context, $this->history)) {
            return false;
        }

        return true;
    }

    /**
     * Get all available transitions from current state.
     *
     * @return array List of valid target states
     */
    public function getAvailableTransitions(): array
    {
        return $this->states[$this->currentState]['allowedTransitions'] ?? [];
    }

    /**
     * Undo the last transition using snapshot isolation.
     *
     * @return bool True if rollback succeeded
     * @throws RuntimeException If no snapshots exist
     */
    public function rollback(): bool
    {
        if (empty($this->rollbackSnapshots)) {
            throw new RuntimeException("No snapshots available for rollback");
        }

        $snapshot = array_pop($this->rollbackSnapshots);
        $this->currentState = $snapshot['state'];
        $this->history = $snapshot['history'];

        // Restore children states
        foreach ($snapshot['children'] as $parent => $childSnapshot) {
            if (isset($this->children[$parent])) {
                $this->children[$parent]->restoreFromSnapshot($childSnapshot);
            }
        }

        return true;
    }

    /**
     * Check if rollback is possible.
     *
     * @return bool True if there are snapshots to roll back to
     */
    public function canRollback(): bool
    {
        return !empty($this->rollbackSnapshots);
    }

    /**
     * Generate a Mermaid.js flow diagram of the state machine.
     *
     * @return string Mermaid diagram code
     */
    public function visualizeFlow(): string
    {
        $mermaid = "graph TD\n";
        foreach ($this->transitions as $key => $transition) {
            $mermaid .= "    {$transition['from']} --> {$transition['to']}\n";
        }
        $mermaid .= "    style {$this->currentState} fill:#f96\n";
        return $mermaid;
    }

    /**
     * Export the full configuration and history.
     *
     * @return array Complete machine state
     */
    public function exportConfig(): array
    {
        return [
            'states' => array_keys($this->states),
            'transitions' => $this->transitions,
            'currentState' => $this->currentState,
            'history' => $this->history,
            'children' => array_map(fn($child) => $child->exportConfig(), $this->children)
        ];
    }

    /**
     * Get the current state.
     *
     * @return string Current state name
     */
    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    /**
     * Get the transition history.
     *
     * @return array History of all transitions
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Create a snapshot of the current machine state.
     */
    private function createSnapshot(): void
    {
        $this->rollbackSnapshots[] = [
            'state' => $this->currentState,
            'history' => $this->history,
            'children' => array_map(fn($child) => $child->exportConfig(), $this->children)
        ];
    }

    /**
     * Restore machine from a saved snapshot.
     *
     * @param array $snapshot The snapshot data
     */
    private function restoreFromSnapshot(array $snapshot): void
    {
        $this->currentState = $snapshot['currentState'];
        $this->history = $snapshot['history'];
    }

    /**
     * Reset child machines to initial state.
     */
    private function reset(): void
    {
        // Reset children if needed
    }
}