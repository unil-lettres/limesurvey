 <?php
/**
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2019-2020 Denis Chenu <http://www.sondages.pro>
 * @copyright 2019-2024 UNIL | Université de Lausanne - Suisse <https://unil.ch/>
 * @license AGPL
 * @version 1.0.0
 */
 ?>
<li class='list-item organizer-question list-group-item' id="question-item-<?=$aQuestion['qid']?>">
    <div class="input-js hidden"><?php
        echo CHtml::textField("question[{$aQuestion['qid']}][qid]",$aQuestion['qid'],array('data-type'=>'question_qid','disabled'=>'disabled','id'=>"question_id_{$aQuestion['qid']}"));
        echo CHtml::textField("question[{$aQuestion['qid']}][order]",$aQuestion['question_order'],array('data-type'=>'question_order','id'=>"question_order_{$aQuestion['qid']}"));
        echo CHtml::textField("question[{$aQuestion['qid']}][gid]",$aQuestion['gid'],array('data-type'=>'question_gid','id'=>"question_group_{$aQuestion['qid']}"));
        echo CHtml::textField("question[{$aQuestion['qid']}][random_group]",$aQuestion['random_group'],array('data-type'=>'random_group','id'=>"question_randomgroup_{$aQuestion['qid']}"));
    ?>
    </div>
    <div class="question-block-wrapper align-items-center">
        <div class="question-block-element question-tool">
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
        </div>
        <div class="question-block-element question-text">
            <div class="organizer-element organizer-question-question"><?php echo $aQuestion['question']; ?></div>
        </div>
        <div class="question-block-element question-tool question-extra-tool">
            <!--<div class="organizer-element organizer-viewtext btn btn-secondary btn-sm" data-sr-tooltip=1><i class="fa fa-sort-desc" aria-hidden="true"></i><span class="sr-only"><?=$lang['Show all question text']?></span></div>-->
            <div class="organizer-element organizer-addinrandomgroup btn btn-warning btn-sm" data-relatedinput="question_randomgroup_<?=$aQuestion['qid']?>" data-sr-tooltip=1><i class="fa fa-plus" aria-hidden="true"></i><span class="sr-only"><?=$lang['Add in a randomization group']?></span></div>
        </div>
    </div>
</li>
