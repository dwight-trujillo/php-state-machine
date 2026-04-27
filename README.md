# PHP StateMachine

A powerful declarative state machine for PHP with transactional rollback, hierarchical states, and built-in Mermaid visualization.

## Installation

git clone https://github.com/dwight-trujillo/php-state-machine.git

## Quick Start

$workflow = new StateMachine('draft');
$workflow->addState('review')->addTransition('draft', 'review');
$workflow->transition('review');
