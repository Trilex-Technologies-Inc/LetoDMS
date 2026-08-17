Workflows in LetoDMS
====================

LetoDMS supports approval and review of documents for a long time.
In many cases this is sufficient for a simple workflow management.
Nevertheless there was growing demand for a more powerful document
workflow. Since version 4.0.0 LetoDMS allows to define arbitrary
workflows for each document content. In order to understand how
workflows in LetoDMS work, one has to understand how a workflow is modelled.
Let's start with a definition of some terms.

workflow: a list of document states and transitions. A workflow starts
  in a preset initial state and traverses along the transitions into other
  states until no more transitions are possible.

state: the current status of a document (actually a document content)
  A state can be for example 'rejected', 'approved', 'waiting for qm'.
  Document jump from state to state when transitions are fired.
  States are the nodes of a graph.

transition: a transition is the change from one state to a new state
  Transitions are the edges of a graph. A transition can only be
  triggered by a given list of users and groupѕ, when a defined action
  is run. Such an action can be 'approve', 'revise', 'reject', etc.
  transitions may need more than one trigger to fire, e.g. if several
  users have to approve a document.

trigger a transition: a user runs an action on the document which possibly
  changes the state. Internally this is identical to triggering a transition.
  Such a trigger may or may not change the statei of the document,
  because there could
  be other users which also have to trigger the transition.
  After each trigger of an transition it will
  be checked whether all conditions are met to change the state.
  Triggers are currently only implemented for user interaction, but
  other triggers could be added.

action: the actual operation run on the document. Each transition has
  an action which when run, triggers the transition. Actions have just a name.

sub workflow: The modelling of a workflow is identical to a regular
  workflow. Any workflow can be used as a sub workflow. Branching into
  a sub workflow is only possible if the current state is equal to the
  initial state of the sub workflow and the user is allowed to trigger
  the next transition in the current workflow.

A workflow and a sub workflow are just a list
of transitions and an initial state. There is no principal difference
between the two and they are equally modelled. Starting from an initial state
there are a number of possible transitions ending in a new state. Each
transition can only be triggered if the user has the right to do so.

A workflow can be assigned to a document just like any other attribute
if the user has right access. Once a workflow is assigned the document
will be in the initial state of the workflow. As long as the workflow
has not left its initial state, it can be removed from the document by
any users with write permission on the document. Once it has left the
initial state it cannot be removed without rewinding the workflow to
its initial state. Rewinding the workflow will remove the log of triggered
transitions and set the document status on the initial state of the
workflow. Rewinding can only be done by administrators.

The purpose of sub workflows is to replace a transition with more
states in between. Such a case can happen, if approval or rejection
of a document is put in charge of a group of persons, e.g. a department.
If the department head decides to set up its own workflow within his
department, he can run a sub workflow. During the lifetime of the sub
workflow, the former workflow (parent workflow) will be paused. Sub workflows
can only be started if the current document state is equal to the initial
state of the sub workflow.
In order to return to the parent workflow two conditions must be met:
1. the state of the document must be a valid state in the parent workflow
2. the person initiating the return to the parent workflow must be allowed
   to trigger the transition which was replaced by the sub workflow
The second condition requires all end states in the sub workflow, also
being a state in the parent workflow. Currently this is not checked before
entering the sub workflow.

A workflow that was accidently added to a document can be removed
as long as it is still in the initial state of the workflow. Once
a transition into a consecutive state has happemed the workflow cannot
be removed anymore. In such a case the administrator has to rewind
the workflow, which removes all triggers including the users comments
and resets the initial state. The same procedure is true for sub workflows
as well. Once a sub workflow has started it can only be left as long
as it is in its initial state or has reached a state in the parent
workflow. Leaving a workflow inbetween will required to rewind it to
the begining and dismiss all transitions done so far.

