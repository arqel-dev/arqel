/**
 * `@arqel/workflow` — React surface for the Arqel Workflow PHP package.
 *
 * Exports the `<StateTransition>` apresentational component plus its
 * type contracts. Apps that wire fields through `@arqel/ui`'s
 * `FieldRegistry` should also import `@arqel/workflow/register` once
 * at boot to install the lazy entry under the component name
 * `arqel/workflow/StateTransition`.
 */

export {
  default,
  STATE_TRANSITION_EVENT,
  StateTransition,
  type StateTransitionCurrentState,
  type StateTransitionEntry,
  type StateTransitionEventDetail,
  type StateTransitionFieldProps,
  type StateTransitionHistoryEntry,
  type StateTransitionProps,
  type StateTransitionRecord,
} from './StateTransition.js';

export {
  buildMermaidSource,
  slugifyStateId,
  type WorkflowDefinitionShape,
  WorkflowVisualizer,
  type WorkflowVisualizerDirection,
  type WorkflowVisualizerProps,
  type WorkflowVisualizerStateShape,
  type WorkflowVisualizerTransitionShape,
} from './WorkflowVisualizer.js';
