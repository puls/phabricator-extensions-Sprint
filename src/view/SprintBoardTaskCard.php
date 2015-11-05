<?php

final class SprintBoardTaskCard extends Phobject {

  private $project;
  private $viewer;
  private $task;
  private $owner;
  private $canEdit;
  private $task_node_id;
  private $points;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setProject ($project) {
    $this->project = $project;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setTask(ManiphestTask $task) {
    $this->task = $task;
    return $this;
  }

  public function setNode($task_node_id) {
    $this->task_node_id = $task_node_id;
    return $this;
  }

  public function getTask() {
    return $this->task;
  }

  public function setOwner(PhabricatorObjectHandle $owner = null) {
    $this->owner = $owner;
    return $this;
  }
  public function getOwner() {
    return $this->owner;
  }

  public function setCanEdit($can_edit) {
    $this->canEdit = $can_edit;
    return $this;
  }

  public function getCanEdit() {
    return $this->canEdit;
  }

  private function getCardAttributes() {
      $pointslabel = 'Points:';
      $pointsvalue = phutil_tag(
          'dd',
          array(
              'class' => 'phui-card-list-value',
          ),
          array($this->points, ' '));

      $pointskey = phutil_tag(
          'dt',
          array(
              'class' => 'phui-card-list-key',
          ),
          array($pointslabel, ' '));
      $assigneelabel = 'Owner:';
      $ownername = $this->owner != null ? $this->owner->getFullName() : "Nobody";
      $assigneevalue = phutil_tag(
          'dd',
          array(
              'class' => 'phui-card-list-value',
          ),
          array($ownername, ' '));

      $assigneekey = phutil_tag(
          'dt',
          array(
              'class' => 'phui-card-list-key',
          ),
          array($assigneelabel, ' '));
      $statuslabel = 'Status:';
      $status = $this->task->getStatus();
      $statusvalue = phutil_tag(
          'dd',
          array(
              'class' => 'phui-card-list-value',
          ),
          array($status, ' '));

      $statuskey = phutil_tag(
          'dt',
          array(
              'class' => 'phui-card-list-key',
          ),
          array($statuslabel, ' '));
      return phutil_tag(
          'dl',
          array(
              'class' => 'phui-property-list-container',
          ),
          array(
              $pointskey,
              $pointsvalue,
              $assigneekey,
              $assigneevalue,
              $statuskey,
              $statusvalue
          ));
    }

  public function getItem() {
    require_celerity_resource('phui-workboard-view-css', 'sprint');

    $query = id(new SprintQuery())
        ->setProject($this->project)
        ->setViewer($this->viewer);
    $task = $this->getTask();
    $task_phid = $task->getPHID();
    $can_edit = $this->getCanEdit();
    $this->points = $query->getStoryPointsForTask($task_phid);


    $color_map = ManiphestTaskPriority::getColorMap();
    $bar_color = idx($color_map, $task->getPriority(), 'grey');

    if (!(is_null($this->owner))) {
      $ownerimage = $this->renderHandleIcon($this->owner);
    } else {
      $ownerimage = null;
    }

    $card = id(new PHUIObjectItemView())
      ->setObjectName('T'.$task->getID())
      ->setHeader($task->getTitle())
      ->setGrippable($can_edit)
      ->setHref('/T'.$task->getID())
      ->addSigil('project-card')
      ->setDisabled($task->isClosed())
      ->setImageIcon($ownerimage)
      ->setMetadata(
        array(
          'objectPHID' => $task_phid,
          'taskNodeID' => $this->task_node_id,
          'points' => $this->points,
        ))
      ->addAction(
            id(new PHUIListItemView())
                ->setName(pht('Edit'))
                ->setIcon('fa-pencil')
                ->addSigil('edit-project-card')
                ->setHref('/project/sprint/board/task/edit/'.$task->getID()
                    .'/'))
      ->setBarColor($bar_color)
      ->addAttribute($this->getCardAttributes());

    $continue_title = "";
    $continue_icon = "";
    switch ($task->getStatus()) {
      case "open":
        $continue_title = "Start";
        $continue_icon = "fa-play";
        break;
      case "inprogress":
        $continue_title = "Finish";
        $continue_icon = "fa-flag-checkered";
        break;
      case "finished":
        $continue_title = "Deliver";
        $continue_icon = "fa-truck";
        break;
      case "delivered":
        $continue_title = "Accept";
        $continue_icon = "fa-thumbs-up";
        break;
    }

    if ($continue_title != "") {
      $card->addAction(
        id(new PHUIListItemView())
            ->setName(pht($continue_title))
            ->setIcon($continue_icon)
            ->addSigil('continue-project-card')
            ->setHref('/project/sprint/board/task/continue/'.$task->getID().'/'));
    }

    if ($task->getStatus() == 'delivered') {
      $card->addAction(
        id(new PHUIListItemView())
            ->setName(pht("Reject"))
            ->setIcon('fa-thumbs-down')
            ->addSigil('reject-project-card')
            ->setHref('/project/sprint/board/task/continue/'.$task->getID().'/'));
    }

    return $card;
  }

  private function renderHandleIcon(PhabricatorObjectHandle $handle) {
    $ownername = $handle->getName();
    $ownerlink = '/p/'.$ownername.'/';
    $image_uri = 'background-image: url('.$handle->getImageURI().')';
    $sigil = 'has-tooltip';
    $meta  = array(
        'tip' => pht($ownername),
        'size' => 200,
        'align' => 'E',
    );
    $image = id(new SprintHandleIconView())
        ->addSigil($sigil)
        ->setMetadata($meta)
        ->setHref($ownerlink)
        ->setIconStyle($image_uri);

    return $image;
  }
}
