<?php

final class SprintBoardTaskContinueController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $response_type = $request->getStr('responseType', 'card');
    $newStatus = $request->getStr('newStatus');
    $order = $request->getStr('order', PhabricatorProjectColumn::DEFAULT_ORDER);
    $can_edit_status = $this->hasApplicationCapability(
      ManiphestEditStatusCapability::CAPABILITY);

    $parent_task = null;
    $template_id = null;

    if ($id) {
      $task = id(new ManiphestTaskQuery())
        ->setViewer($viewer)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->withIDs(array($id))
        ->needSubscriberPHIDs(true)
        ->needProjectPHIDs(true)
        ->executeOne();
      if (!$task) {
        return new Aphront404Response();
      }
    } else {
      return new Aphront404Response();
    }

    $transaction = new ManiphestTransaction();
    $transaction->setTransactionType('status');
    if ($newStatus == null) {
      switch ($task->getStatus()) {
        case 'open':
          $newStatus = 'inprogress';
          break;
        case 'inprogress':
          $newStatus = 'finished';
          break;
        case 'finished':
          $newStatus = 'delivered';
          break;
        case 'delivered':
          $newStatus = 'resolved';
          break;
        default:
          return new Aphront404Response();
      }
    }
    $transaction->setNewValue($newStatus);

    $transactions = [$transaction];

    if (!$task->getOwnerPHID() && $newStatus == 'inprogress') {
      // Closing an unassigned task. Assign the user as the owner of
      // this task.
      $assign = new ManiphestTransaction();
      $assign->setTransactionType(ManiphestTransaction::TYPE_OWNER);
      $assign->setNewValue($viewer->getPHID());
      $transactions[] = $assign;
    }

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnMissingFields(true);

    try {
      $editor->applyTransactions($task, $transactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($task_uri)
        ->setException($ex);
    }

    $errors = array();
    $e_title = true;

    $field_list = PhabricatorCustomField::getObjectFields(
      $task,
      PhabricatorCustomField::ROLE_EDIT);
    $field_list->setViewer($viewer);
    $field_list->readFieldsFromStorage($task);

    $aux_fields = $field_list->getFields();

    $v_space = $task->getSpacePHID();

    if ($request->isAjax()) {
      switch ($response_type) {
        case 'card':
          $owner = null;
          if ($task->getOwnerPHID()) {
            $owner = id(new PhabricatorHandleQuery())
              ->setViewer($viewer)
              ->withPHIDs(array($task->getOwnerPHID()))
              ->executeOne();
          }

          $tasks = id(new SprintBoardTaskCard())
            ->setViewer($viewer)
            ->setProject(null)
            ->setTask($task)
            ->setOwner($owner)
            ->setCanEdit(true)
            ->getItem();

          $column = id(new PhabricatorProjectColumnQuery())
            ->setViewer($viewer)
            ->withPHIDs(array($request->getStr('columnPHID')))
            ->executeOne();
          if (!$column) {
            return new Aphront404Response();
          }

          // re-load projects for accuracy as they are not re-loaded via
          // the editor
          $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
              $task->getPHID(),
              PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
          $task->attachProjectPHIDs($project_phids);
          $remove_from_board = false;
          if (!in_array($column->getProjectPHID(), $project_phids)) {
            $remove_from_board = true;
          }

          $positions = id(new PhabricatorProjectColumnPositionQuery())
            ->setViewer($viewer)
            ->withColumns(array($column))
            ->execute();
          $task_phids = mpull($positions, 'getObjectPHID');

          $column_tasks = id(new ManiphestTaskQuery())
            ->setViewer($viewer)
            ->withPHIDs($task_phids)
            ->execute();

          if ($order == PhabricatorProjectColumn::ORDER_NATURAL) {
            // TODO: This is a little bit awkward, because PHP and JS use
            // slightly different sort order parameters to achieve the same
            // effect. It would be good to unify this a bit at some point.
            $sort_map = array();
            foreach ($positions as $position) {
              $sort_map[$position->getObjectPHID()] = array(
                -$position->getSequence(),
                $position->getID(),
              );
            }
          } else {
            $sort_map = mpull(
              $column_tasks,
              'getPrioritySortVector',
              'getPHID');
          }

          $data = array(
            'sortMap' => $sort_map,
            'removeFromBoard' => $remove_from_board,
          );
          break;
        case 'task':
        default:
          $tasks = $this->renderSingleTask($task);
          $data = array();
          break;
      }
      return id(new AphrontAjaxResponse())->setContent(
        array(
          'tasks' => $tasks,
          'data' => $data,
        ));
    }

  }

  private function getSprintProjectforTask($viewer, $projects) {
    $project = null;

    if ($projects) {
      $query = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs($projects);
    } else {
      return null;
    }

    $projects = $query->execute();

    foreach ($projects as $project) {
      $sprintquery = id(new SprintQuery())
          ->setPHID($project->getPHID());
      if ($sprintquery->getIsSprint()) {
        return $project;
      }
    }

  }

}
