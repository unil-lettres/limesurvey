<?php
/**
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2019-2020 Denis Chenu <http://www.sondages.pro>
 * @copyright 2019-2024 UNIL | Université de Lausanne - Suisse <https://unil.ch/>
 * @license AGPL
 * @version 1.0.0
 */
echo CHtml::form($form['action'],"post", ["id" => "organizer-form"]);
?>
<h3 class="clearfix"><?php echo $lang['Organize question groups/questions']; ?>
  <div class='pull-right'>
    <?php
      echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
      echo " ";
      echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-secondary'));
      echo " ";
      echo CHtml::link(gT('Reset'),$form['reset'],array('class'=>'btn btn-danger'));
      echo " ";
      echo CHtml::link(gT('Close'),$form['close'],array('class'=>'btn btn-danger'));
    ?>
  </div>
</h3>
<div class="card card-body mb-3">
    <div class="d-flex gap-2 justify-content-end">
        <button type="button" id='organizer-collapse-all' class='btn btn-secondary'><i class='fa fa-compress' aria-hidden=true></i> <?php eT("Collapse all"); ?></button>
        <button type="button" id='organizer-expand-all' class='btn btn-secondary'><i class='fa fa-expand' aria-hidden=true></i> <?php eT("Expand all"); ?></button>
        <button type="button" id='organizer-expand-groups' class='btn btn-secondary'><i class='fa fa-expand' aria-hidden=true></i> <?php eT("Expand questions groups"); ?></button>
    </div>
</div>
<ol class="organizer-group-list list-unstyled">
<?php foreach ($aGroups as $aGroup) { ?>
    <li class="organizer-group card mt-4" data-level='group'>
        <div class="card-header mb-0">
            <div class="input-js hidden"><?php
                //~ echo CHtml::textField("group[{$aGroup['gid']}]['gid']",$aGroup['gid'],array('data-type'=>'group_gid'));
                echo CHtml::textField("group[{$aGroup['gid']}][gid]",$aGroup['gid'],array('data-type'=>'group_gid','disabled'=>'disabled','id'=>"group_gid_{$aGroup['gid']}"));
                echo CHtml::textField("group[{$aGroup['gid']}][order]",$aGroup['group_order'],array('data-type'=>'group_order','id'=>"group_order_{$aGroup['gid']}"));
            ?>
            </div>
            <div class="organizer-group-name">
                <div class="organizer-handle btn btn-info btn-sm" data-sr-tooltip=1><i class="fa fa-arrows" aria-hidden="true"></i><span class="sr-only"><?=$lang['Move group']?></span></div>
                <div class="organizer-element organizer-relevance">[<?=$aGroup['grelevance']?>]</div>
                <?php
                    $link = array(
                        'questionGroupsAdministration/view',
                        'surveyid'=>$aGroup['sid'],'gid'=>$aGroup['gid']
                    );
                    echo CHtml::link(
                        CHtml::encode($aGroup['name']),
                        $link,
                        array('class'=>"organizer-element organizer-group-title")
                    );
                ?>
                <small class="organizer-group-description"><?php echo $aGroup['description']?></small>
                <button class="btn btn-secondary btn-sm pull-right"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#group-body-<?php echo $aGroup['gid']; ?>"
                    aria-expanded="true" aria-controls="group-body-<?php echo $aGroup['gid']; ?>">
                    <i class="fa fa-chevron-down" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="show collapse in" id="group-body-<?php echo $aGroup['gid']; ?>">
            <ol class="organizer-question-list list-group list-group-flush" id="organizer-question-list-<?php echo $aGroup['gid']; ?>" data-group-id="<?php echo $aGroup['gid']; ?>">
            <?php foreach ($aGroup['questions'] as $aQuestion) { ?>
                <?php if($aQuestion['random_group']) { ?>
                    <?php
                        $aRandomGroup = $aRandomGroups[$aQuestion['random_group']];
                    ?>
                    <li class='list-item organizer-random-group list-group-item card' data-related-groupid="<?=$aRandomGroup['id']?>" data-randomgroup-name="<?=CHtml::encode($aRandomGroup['name'])?>">
                        <div class="input-js hidden">
                        <?php
                            echo CHtml::textField("randomgroupCopy[id]",$aRandomGroup['id'],array('data-type'=>'randomgroup_id','disabled'=>'disabled','id'=>false));
                            echo CHtml::textField("randomgroupCopy[gid]",$aQuestion['gid'],array('data-type'=>'question_gid','disabled'=>'disabled','id'=>false));
                            echo CHtml::textField("randomgroupCopy[order]",$aQuestion['question_order'],array('data-type'=>'question_order','disabled'=>'disabled','id'=>false));
                        ?>
                        </div>
                        <div class="organizer-element organizer-handle btn btn-info btn-sm"  data-sr-tooltip=1><i class="fa fa-arrows" aria-hidden="true"></i><span class="sr-only"><?=$lang['Move randomization group']?></span></div>
                        <div class="organizer-element organizer-random-group-title badge bg-warning"><?php echo CHtml::encode($aRandomGroups[$aQuestion['random_group']]['name']); ?></div>
                        <div class="organizer-element organizer-random-group-name"><?php printf($lang['Randomization group: %s'],CHtml::encode($aRandomGroups[$aQuestion['random_group']]['name'])); ?></div>
                        <?php if ( !$aRandomGroup['view'] ) { ?>
                            <button class="btn btn-secondary btn-sm pull-right btn-collapse-random-group"
                                id="button-question-inrandom-<?=$aRandomGroup['id']?>"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#list-question-inrandom-<?=$aRandomGroup['id']?>"
                                aria-expanded="false" aria-controls="#list-question-inrandom-<?=$aRandomGroup['id']?>">
                                <i class="fa fa-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="organizer-element organizer-random-group-count me-2 btn btn-secondary btn-sm pull-right"
                                id="organizer-random-group-count-question-inrandom-<?=$aRandomGroup['id']?>"
                            >
                                <?php
                                echo CHtml::tag("span",array('class'=>"hidden show-question-in-random-group",'data-count-groupid'=>$aRandomGroup['id']),trim($aRandomGroup['maxshown']));
                                $maxshown = $aRandomGroup['maxshown'] ? $aRandomGroup['maxshown'] : sprintf($lang['%s (all)'],count($aRandomGroup['questions']));
                                printf($lang['%s question(s) in group, show %s question(s)'],
                                    CHtml::tag('span',array('class'=>'count-question-in-random-group'),count($aRandomGroup['questions'])),
                                    CHtml::tag('strong',array('class'=>'show-final-question-in-random-group','data-count-groupid'=>$aRandomGroup['id'],'data-count-groupname'=>$aRandomGroups[$aQuestion['random_group']]['name']),$maxshown)
                                )?>
                            </div>
                            <ol id="list-question-inrandom-<?=$aRandomGroup['id']?>" class="card collapse organizer-question-list organizer-random-question-list list-unstyled my-2" data-randomgroup-id="<?php echo $aRandomGroup['id']; ?>">
                                <?php foreach ($aRandomGroup['questions'] as $aQuestion) { ?>
                                    <li class='list-item organizer-question list-group-item d-flex align-items-center gap-2' data-question-id="<?=$aQuestion['qid']?>">
                                        <div class="organizer-element organizer-handle btn btn-info btn-sm" data-sr-tooltip=1><i class="fa fa-arrows" aria-hidden="true"></i><span class="sr-only"><?=$lang['Move question']?></span></div>
                                        <?php
                                            $link = array(
                                                'questionAdministration/view',
                                                'surveyid'=>$aQuestion['sid'],'gid'=>$aQuestion['gid'],'qid'=>$aQuestion['qid']
                                            );
                                            echo CHtml::link(
                                                $aQuestion['title'],
                                                $link,
                                                array('class'=>"organizer-element organizer-question-title badge bg-secondary")
                                            );
                                        ?>
                                        <div class="organizer-element organizer-relevance">[<?=$aQuestion['relevance']?>]</div>
                                        <div class="organizer-element organizer-question-question"><?php echo $aQuestion['question']; ?></div>
                                    </li>
                                <?php } ?>
                            </ol>

                        <?php $aRandomGroups[$aQuestion['random_group']]['view'] = true; ?>
                        <?php } ?>
                    </li>
                <?php } else {
                    Yii::app()->getController()->renderPartial("{$pluginClass}.views.elements.question_organizer",array("aQuestion"=>$aQuestion,'lang'=>$lang));
                } ?>
            <?php } ?>
            </ol>
        </div>
    </li>
<?php } ?>
</ol>
<?php
/* Do hidden randomization groups for submit of questions */
?>
<div class="hidden">
    <ul>
    <?php foreach($aRandomGroups as $id=>$aRandomGroup) { ?>
        <li id="<?=$aRandomGroup['id']?>" class='list-item organizer-random-group list-group-item' data-related-groupid="<?=$aRandomGroup['id']?>" data-randomgroup-name="<?=CHtml::encode($aRandomGroup['name'])?>">
            <?php
            ?>
            <div class="input-js hidden">
            <?php
                echo CHtml::textField("randomgroupCopy[id]",$aRandomGroup['id'],array('data-type'=>'randomgroup_id','disabled'=>'disabled','id'=>false));
                echo CHtml::textField("randomgroupCopy[gid]",'',array('data-type'=>'question_gid','disabled'=>'disabled','id'=>false));
                echo CHtml::textField("randomgroupCopy[order]",'',array('data-type'=>'question_order','disabled'=>'disabled','id'=>false));
            ?>
            </div>
            <div class="organizer-element organizer-handle btn btn-info btn-sm" data-sr-tooltip=1><i class="fa fa-arrows" aria-hidden="true"></i><span class="sr-only"><?=$lang['Move randomization group']?></span></div>
            <div class="organizer-element organizer-random-group-title badge bg-warning"><?php echo CHtml::encode($aRandomGroup['name']); ?></div>
            <div class="organizer-element organizer-random-group-name"><?php printf($lang['Randomization group: %s'],CHtml::encode($aRandomGroup['name'])); ?></div>
            <ol class="organizer-question-list organizer-random-question-list list-unstyled list-group" data-randomgroup-id="<?php echo $aRandomGroup['id']; ?>">
                <?php foreach ($aRandomGroup['questions'] as $aQuestion) {
                    Yii::app()->getController()->renderPartial("{$pluginClass}.views.elements.question_organizer",array("aQuestion"=>$aQuestion,'lang'=>$lang));
                } ?>
            </ol>
        </li>
    <?php } ?>
    </ul>
</div>
<div class='row'>
  <div class='text-center submit-buttons'>
    <?php
      echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
      echo " ";
      echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-secondary'));
      //~ echo " ";
      //~ echo CHtml::link(gT('Reset'),$form['reset'],array('class'=>'btn btn-danger'));
      echo " ";
      echo CHtml::link(gT('Close'),$form['close'],array('class'=>'btn btn-danger'));
    ?>
  </div>
</div>
<!-- Modal for new group -->
<div class="modal fade" id="add-randomgroup-modal" tabindex="-1" role="dialog" aria-labelledby="add-randomgroup-modal-title">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="add-randomgroup-modal-title"><?=$lang['Add in a randomization group']?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=$lang['Close']?>"></button>
      </div>
      <div class="modal-body form-inline">
          <div class="form-group">
            <label for="new-randomgroup-name" class="control-label"><?=$lang['New randomization group name']?></label>
            <input type="text" class="form-control" id="new-randomgroup-name" name="new-randomgroup-name">
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=$lang['Close']?></button>
        <?php
            echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Create group and save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary btn-addrandomgroup'));
        ?>
      </div>
    </div>
  </div>
</div>
<!-- Modal for count in group -->
<div class="modal fade" id="count-randomgroup-modal" tabindex="-1" role="dialog" aria-labelledby="count-randomgroup-modal-title">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title" id="count-randomgroup-modal-title"><?=$lang['Number of question(s) to show']?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=$lang['Close']?>"></button>
      </div>
      <div class="modal-body form-inline">
          <div class="form-group">
            <label class="control-label"><?php printf($lang['Number of question(s) to show in %s'],"<span id='count-randomgroup-modal-name'></span>") ?></label>
            <?php
                echo CHtml::hiddenField('count-randomgroup-name-input','',array('id'=>'count-randomgroup-name-input'));
                echo CHtml::numberField('count-randomgroup-count-input','',array('id'=>'count-randomgroup-count-input','class'=>'form-control','placeholder' => $lang['all'], 'min'=>1,'step'=>1));
            ?>
            </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=$lang['Close']?></button>
        <?php
            echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.$lang['Save number of questions'],array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary btn-countrandomgroup'));
        ?>
      </div>
    </div>
  </div>
</div>
</form>
<script>
    organizeSurvey.init();
</script>
<?php
//~ Yii::app()->getClientScript()->registerScript("OrganizerInit","organizeSurvey.init()",CClientScript::POS_END);
?>

