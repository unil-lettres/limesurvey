<?php
/**
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2019-2021 Denis Chenu <http://www.sondages.pro>
 * @copyright 2019-2021 UNIL | Université de Lausanne - Suisse <https://unil.ch/>
 * @license AGPL
 * @version 0.6.6
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class organizeSurvey extends PluginBase
{
    static protected $description = 'A new way to organize survey.';
    static protected $name = 'organizeSurvey';

    protected $storage = 'DbStorage';

    private $debug=false;

    CONST ASSET_VERSION=3;

    protected $settings = array(
        "defaultRandgroupMaxquestion"=>array(
            "type"=>'select',
            'label'=>'Number of question shown in random group by default.',
            'help'=>'The default is set when create new group, this was for random group added via question editor or imported too. To set all : you can always set value to an empty string.',
            'default' => 'all',
            'options'=>array(
                'one'=>'One',
                'all'=>'All',
            ),
      ),
    );

    public function init()
    {
        $this->subscribe('beforeToolsMenuRender');
        /* Menu maganement */
        $this->subscribe('beforeActivate');
        $this->subscribe('beforeDeactivate');
        /* Add the package */

        /* Group count in question settings */
        $this->subscribe('setVariableExpressionEnd','hideExtraQuestionsOnSurvey');
    }

    /**
     * Hide the extra questions when EM start
     */
    public function hideExtraQuestionsOnSurvey()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $surveyId = $this->getEvent()->get('surveyId');
        $aQuestionRandomizationCount = $this->_getQuestionRandomizationCount($surveyId);
        if(empty($aQuestionRandomizationCount)) {
            return;
        }
        $aQidHiddenQuestionsByRandGroup = array();
        $aQidVisibleQuestionsByRandGroup = array();
        $knownVars = $this->getEvent()->get('knownVars');
        $questionSeq2relevance = $this->getEvent()->get('questionSeq2relevance');

        foreach($knownVars as $column => $knownVar) {
            if(!isset($knownVar['qid'])) {
                continue;
            }
            $qid = $knownVar['qid'];
            $randomGroup = $this->_getRandomizationAttribute($knownVar['qid']);
            if(empty($randomGroup)) {
                continue;
            }
            $maxCount = $this->_getRandgroupMaxquestion($randomGroup,$aQuestionRandomizationCount);
            if($maxCount === "") {
                continue;
            }
            $maxCount = intval($maxCount);
            if($maxCount == 0) {
                $this->log("Invalid value for max questions to show for $randomGroup",'warning');
                continue;
            }
            /* We are in a randomgroup, and show some question only */
            if(!isset($aQidHiddenQuestionsByRandGroup[$randomGroup])) {
                $aQidHiddenQuestionsByRandGroup[$randomGroup] = array();
            }
            if(!isset($aQidVisibleQuestionsByRandGroup[$randomGroup])) {
                $aQidVisibleQuestionsByRandGroup[$randomGroup] = array();
            }
            if(in_array($qid,$aQidHiddenQuestionsByRandGroup[$randomGroup])) {
                // Sub question already hidden
                $knownVars[$column]['hidden'] = "1";
                continue;
            }
            if(in_array($qid,$aQidVisibleQuestionsByRandGroup[$randomGroup])) {
                // Sub question already not hidden
                continue;
            }
            if(count($aQidVisibleQuestionsByRandGroup[$randomGroup]) >= $maxCount) {
                $knownVars[$column]['hidden'] = "1";
                if (isset($knownVars[$column]['qseq'])) {
                    $questionSeq2relevance[$knownVars[$column]['qseq']]['hidden'] = true;
                }
                $aQidHiddenQuestionsByRandGroup[$randomGroup][] = $qid;
            } else {
                $aQidVisibleQuestionsByRandGroup[$randomGroup][] = $qid;
            }
        }

        $this->getEvent()->set('knownVars',$knownVars);
        $this->getEvent()->set('questionSeq2relevance',$questionSeq2relevance);
    }
    public function beforeActivate()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        /* Find if menu already exist */
        $oSurveymenuEntries = \SurveymenuEntries::model()->find("name = :name",array(":name"=>get_class($this)));
        if(empty($oSurveymenuEntries)) {
            $parentMenu = 1;
            $order = 100;
            $listSurveyPagesMenuEntries = \SurveymenuEntries::model()->find("name = :name",array(":name"=>'listSurveyPages'));
            if($listSurveyPagesMenuEntries) {
                $parentMenu = $listSurveyPagesMenuEntries->menu_id;
                $order = $listSurveyPagesMenuEntries->ordering+1;
            }
            /* Unable to translate it currently … */
            $aNewMenu = array(
                'name' => get_class($this),
                'language' => 'en-US',
                'title' => "Organizer",
                'menu_title' => "Organizer",
                'menu_description' => "Organize your survey",
                'menu_icon' => 'indent',
                'menu_icon_type' => 'fontawesome',
                'menu_link' => 'admin/pluginhelper/sa/sidebody', // 'plugins/direct/plugin/responseListAndManage'
                'manualParams' => array(
                    'plugin' => get_class($this),
                    'method' => 'actionSettings',
                ),
                'permission' => 'surveycontent',
                'permission_grade' => 'update',
                'pjaxed' => false,
                'addSurveyId' => true,
                'addQuestionGroupId' => false,
                'addQuestionId' => false,
                'linkExternal' => false,
                'hideOnSurveyState' => false,
                'ordering' => $order,
            );
            $iMenu = \SurveymenuEntries::staticAddMenuEntry($parentMenu,$aNewMenu);
            $oSurveymenuEntries = \SurveymenuEntries::model()->findByPk($iMenu);
            if($oSurveymenuEntries) {
                $oSurveymenuEntries->ordering = $order;
                $oSurveymenuEntries->name = get_class($this); // SurveymenuEntries::staticAddMenuEntry cut name, then reset
                $oSurveymenuEntries->save();
                \SurveymenuEntries::reorderMenu($parentMenu);
            }
        }
        /* For translation */
        $menuTitle = $this->_translate("Organizer");
        $menuDescription = $this->_translate("Organize your survey");
    }

    /** Delete menu when deactivate **/
    public function beforeDeactivate()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        \SurveymenuEntries::model()->deleteAll("name = :name",array(":name"=>get_class($this)));
    }

    /**
     * see beforeToolsMenuRender event
     * @deprecated ? See https://bugs.limesurvey.org/view.php?id=15476
     * @return void
     */
    public function beforeToolsMenuRender()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        $aMenuItem = array(
            'label' => $this->_translate('Organizer'),
            'iconClass' => 'fa fa-indent',
            'href' => Yii::app()->createUrl(
                'admin/pluginhelper',
                array(
                    'sa' => 'sidebody',
                    'plugin' => get_class($this),
                    'method' => 'actionSettings',
                    'surveyId' => $surveyId
                )
            ),
        );
        $menuItem = new \LimeSurvey\Menu\MenuItem($aMenuItem);
        $event->append('menuItems', array($menuItem));

    }

    /**
     * The main function
     * @param int $surveyId Survey id
     * @return void (redirect)
     */
    public function actionSaveSettings($surveyId)
    {
        if(empty(App()->getRequest()->getPost('save'.get_class($this)))) {
            throw new CHttpException(400);
        }
        $oSurvey=Survey::model()->findByPk($surveyId);
        if(!$oSurvey) {
            throw new CHttpException(404,gT("This survey does not seem to exist."));
        }
        if(!Permission::model()->hasSurveyPermission($surveyId,'surveysettings','update')){
            throw new CHttpException(403);
        }

        /* Order of groups */
        $groups = Yii::app()->getRequest()->getPost('group',array());
        if(!empty($groups)) {
            foreach($groups as $gid=>$aGroup) {
                $oGroup = QuestionGroup::model()->find("sid = :sid and gid = :gid",array(":sid"=>$surveyId, ":gid" => $gid));
                if($oGroup) {
                    $oGroup->group_order = intval($aGroup['order']);
                }
            }
        }
        /* Order of questions */
        $questions = Yii::app()->getRequest()->getPost('question',array());
        if(!empty($questions)) {
            foreach($questions as $qid=>$aQuestion) {
                $oQuestion = \Question::model()->find("sid = :sid and qid = :qid and parent_qid = 0",array(":sid"=>$surveyId, ":qid" => $qid));
                $gid = $aQuestion['gid'];
                if($oQuestion) {
                    /* Check related gid … */
                    $oGroup = \QuestionGroup::model()->find("sid = :sid and gid = :gid",array(":sid"=>$surveyId, ":gid" => $gid));
                    if(empty($oGroup)) {
                        Yii::app()->setFlashMessage(sprintf($this->_translate("Invalid group for question %s"),$oQuestion->qid),'warning');
                    } else {
                        $oQuestion->gid = $aQuestion['gid'];
                        $oQuestion->question_order = intval($aQuestion['order']);
                        $oQuestion->save();
                        $this->_setQuestionAttribute($oQuestion->qid,'random_group',trim($aQuestion['random_group']));
                    }
                }
            }
        }
        /* Group question count as json encoded in Plugin Survey Settings */
        $setGroupName = Yii::app()->getRequest()->getPost('count-randomgroup-name-input',"");
        $setGroupCount = Yii::app()->getRequest()->getPost('count-randomgroup-count-input');
        $aQuestionRandomizationCount = $this->_getQuestionRandomizationCount($surveyId);
        if ($setGroupName && !is_null($setGroupCount)) {
            if( $setGroupCount !== "") {
                $setGroupCount = intval($setGroupCount);
            }
            $aQuestionRandomizationCount[$setGroupName] = $setGroupCount;
        }

        $this->set("question-randomization-count",$aQuestionRandomizationCount,'Survey',$surveyId);
        if(App()->getRequest()->getPost('save'.get_class($this)) == 'redirect') {
            $redirectUrl = Yii::app()->createUrl('surveyAdministration/view',array('surveyid' => $surveyId));
            Yii::app()->getRequest()->redirect($redirectUrl);
        }
        $redirectUrl = Yii::app()->createUrl('admin/pluginhelper/sa/sidebody',array('plugin' => get_class($this),'method' => 'actionSettings','surveyId' => $surveyId));
        Yii::app()->getRequest()->redirect($redirectUrl,true,303);
    }
    /**
     * The main function
     * @param int $surveyId Survey id
     * @return string
     */
    public function actionSettings($surveyId)
    {
        $oSurvey=Survey::model()->findByPk($surveyId);
        if(!$oSurvey) {
            throw new CHttpException(404,gT("This survey does not seem to exist."));
        }
        if(!Permission::model()->hasSurveyPermission($surveyId,'surveysettings','update')){
            throw new CHttpException(403);
        }
        $aData = $this->_organizeFormData($surveyId);
        $aData['pluginClass']=get_class($this);
        $aData['surveyId']=$surveyId;
        $aData['lang'] = $this->_getLanguageData();
        $aData['form'] = array(
            'action' => Yii::app()->createUrl('admin/pluginhelper/sa/sidebody',array('plugin' => get_class($this),'method' => 'actionSaveSettings','surveyId' => $surveyId)),
            'reset' => Yii::app()->createUrl('admin/pluginhelper/sa/sidebody',array('plugin' => get_class($this),'method' => 'actionSettings','surveyId' => $surveyId)),
            'close' => Yii::app()->createUrl('surveyAdministration/view',array('surveyid' => $surveyId)),
        );
        //~ App()->getClientScript()->registerCssFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/organizeSurvey.css'));
        $this->_registerNeededPackage();
        $content = $this->renderPartial('organizer', $aData, true);
        return $content;
    }

    /**
     * Get the current attribute random_group
     * @param integer $qid
     * @return string
     */
    private function _getRandomizationAttribute($qid) 
    {
        $oQuestionRandomGroup = \QuestionAttribute::model()->find('qid = :qid and attribute = :attribute',array(':qid'=>$qid,':attribute'=>'random_group'));
        if(empty($oQuestionRandomGroup)) {
            return "";
        }
        return $oQuestionRandomGroup->value;

    }
    /**
     * Get the current settings for number of group
     * @param integer $surveyId
     * @return array
     */
    private function _getQuestionRandomizationCount($surveyId)
    {
        $aQuestionRandomizationCount = $this->get("question-randomization-count",'Survey',$surveyId,array());
        if(is_string($aQuestionRandomizationCount)) {
            $aQuestionRandomizationCount = json_decode($aQuestionRandomizationCount,1);
        }
        return $aQuestionRandomizationCount;
    }

    /**
     * return the current randgroupMaxquestion
     * @param string $randomgroup
     * @param array
     * @return string|int
     */
    private function _getRandgroupMaxquestion($randomgroup, $aQuestionRandomizationCount)
    {
        $defaultSettingRandgroupMaxquestion = $this->get('defaultRandgroupMaxquestion',null,null,$this->settings['defaultRandgroupMaxquestion']['default']);
        $default = "";
        if ($defaultSettingRandgroupMaxquestion == "one") {
            $default = 1;
        }
        if (empty($randomgroup)) {
            return $default;
        }
        if (!isset($aQuestionRandomizationCount[$randomgroup])) {
            return $default;
        }
        if (is_null($aQuestionRandomizationCount[$randomgroup])) {
            return $default;
        }
        return $aQuestionRandomizationCount[$randomgroup];
    }
    /**
     * Asset version reset
     * @return void
     */
    private function _assetVersionUpdate()
    {
        $currentDbAssetVersion = $this->get('assetVersion',null,null,0);
        if($currentDbAssetVersion < self::ASSET_VERSION) {
            Yii::setPathOfAlias(get_class($this),dirname(__FILE__));
            $path = Yii::getPathOfAlias(get_class($this).'.vendor.sortable');
            AssetVersion::incrementAssetVersion($path);
            $path = Yii::getPathOfAlias(get_class($this).'.assets');
            AssetVersion::incrementAssetVersion($path);
            $this->get('assetVersion',self::ASSET_VERSION);
        }
    }
    /**
     * Translation replacer
     * @param string $string to b translated
     * @param string $sEscapeMode default to unescaped
     * @param string $sLanguage for translation , default to current Yii lang
     * @return string translated string
     */
    private function _translate($string, $sEscapeMode = 'unescaped', $sLanguage = null)
    {
        return $this->gT($string, $sEscapeMode, $sLanguage);
    }

    private function _getLanguageData()
    {
        return array(
            'Organize question groups/questions' => gT("Organize question groups/questions"),
            'Randomization group: %s' => $this->_translate("Randomization group: %s"),
            '%s question(s) in group, show %s question(s)' => $this->_translate("%s question(s) in group, show %s question(s)"),
            '%s (all)' => $this->_translate("%s (all)"),
            'Move group' => $this->_translate("Move group"),
            'Move question' => $this->_translate("Move question"),
            'Move randomization group' => $this->_translate("Move randomization group"),
            'Add in a randomization group' => $this->_translate("Add in a randomization group"),
            'New randomization group name' => $this->_translate("New randomization group name"),
            'Close' => $this->_translate("Close"),
            'Create group and save' => $this->_translate("Create group and save"),
            'Show all question text' => $this->_translate("View all question text"),
            'Number of question(s) to show' => $this->_translate("Number of question(s) to show"),
            'Number of question(s) to show in %s' => $this->_translate("Number of question(s) to show in %s"),
            'Save number of questions' =>  $this->_translate("Save number of questions"),
            'all' =>  $this->_translate("all"),
            'Collapse all' =>  $this->_translate("Collapse all"),
            'Expand all' =>  $this->_translate("Expand all"),
            'Expand questions groups' =>  $this->_translate("Expand questions groups"),
        );
    }
    private function _organizeFormData($surveyId)
    {
        $survey = Survey::model()->findByPk($surveyId);
        $aData = [];
        $aData['title_bar']['title'] = $survey->currentLanguageSettings->surveyls_title." (".gT("ID").":".$surveyId.")";

        // Prepare data for the view
        $aQuestionRandomizationCount = $this->_getQuestionRandomizationCount($surveyId);
        $language = Survey::model()->findByPk($surveyId)->language;
        \LimeExpressionManager::StartSurvey($surveyId, 'survey');
        \LimeExpressionManager::SetPreviewMode('organizer');
        \LimeExpressionManager::StartProcessingPage(true, Yii::app()->baseUrl);
        $oGroups = \QuestionGroup::model()->findAll(array(
            'condition'=> 'sid = :sid',
            'order' => 'group_order asc',
            'params'=> array(':sid' => $surveyId)
        ));
        $aGroups = array();
        $aRandomGroups = array();
        $iCountRandomGroups = 0;

        foreach($oGroups as $oGroup) {
            $oGoupLang = \QuestionGroupL10n::model()->find('gid = :gid and language = :language',array(':gid'=>$oGroup->gid,':language'=>$language));
            \LimeExpressionManager::StartProcessingGroup($oGroup->gid, false, $surveyId);
            $aGroups[$oGroup->gid] = $oGroup->attributes;
            \LimeExpressionManager::ProcessString($oGoupLang->group_name);
            $aGroups[$oGroup->gid]['name'] = \viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
            \LimeExpressionManager::ProcessString($oGoupLang->description);
            $aGroups[$oGroup->gid]['description'] = \viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
            $aGroups[$oGroup->gid]['grelevance'] = trim($aGroups[$oGroup->gid]['grelevance']) === "" ? "1" : strval($aGroups[$oGroup->gid]['grelevance']);
            if($aGroups[$oGroup->gid]['grelevance'] !== "1") {
                \LimeExpressionManager::ProcessString("{".$oGroup->grelevance."}");
                $aGroups[$oGroup->gid]['grelevance'] = \viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
            }
            $aGroups[$oGroup->gid]['questions'] = array();
            /* The questions of this group */
            $oQuestions = \Question::model()->findAll(array(
                'condition'=> 'sid = :sid and gid = :gid and parent_qid = 0',
                'order' => 'question_order asc',
                'params'=> array(':sid' => $surveyId,':gid' => $oGroup->gid)
            ));
            foreach($oQuestions as $oQuestion) {
                $oQuestionLang = \QuestionL10n::model()->find('qid = :qid and language = :language',array(':qid'=>$oQuestion->qid,':language'=>$language));
                $aQuestion = $oQuestion->attributes;
                \LimeExpressionManager::ProcessString($oQuestionLang->question,$oQuestion->qid);
                $aQuestion['question'] = \viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
                \LimeExpressionManager::ProcessString($oQuestionLang->help,$oQuestion->qid);
                $aQuestion['help'] = \viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
                $aQuestion['relevance'] = $aQuestion['relevance'] === "" ? "1" : $aQuestion['relevance'];
                if($aQuestion['relevance'] != "1") {
                    \LimeExpressionManager::ProcessString("{".$oQuestion->relevance."}");
                    $aQuestion['relevance'] = \viewHelper::stripTagsEM(LimeExpressionManager::GetLastPrettyPrintExpression());
                }
                
                $oQuestionHidden = \QuestionAttribute::model()->find('qid = :qid and attribute = :attribute',array(':qid'=>$oQuestion->qid,':attribute'=>'hidden'));
                $aQuestion['hidden'] = (!empty($oQuestionHidden) && !$oQuestionHidden->value);
                $oQuestionRandomGroup = \QuestionAttribute::model()->find('qid = :qid and attribute = :attribute',array(':qid'=>$oQuestion->qid,':attribute'=>'random_group'));
                $aQuestion['random_group'] = !empty($oQuestionRandomGroup->value) ? $oQuestionRandomGroup->value : "";
                $aQuestion['rand_group_maxquestion'] = $this->_getRandgroupMaxquestion($aQuestion['random_group'],$aQuestionRandomizationCount);
                if($aQuestion['random_group']) {
                    if(!array_key_exists($aQuestion['random_group'],$aRandomGroups)) {
                        $iCountRandomGroups++;
                        $aRandomGroups[$aQuestion['random_group']] = array(
                            'id' => 'random_group_'.$iCountRandomGroups,
                            'view' => false,
                            'name' => $aQuestion['random_group'],
                            'questions'=>array(),
                            'maxshown'=>$aQuestion['rand_group_maxquestion'],
                        );
                    }
                    $aRandomGroups[$aQuestion['random_group']]['questions'][$oQuestion->qid] = $aQuestion;
                }
                $aGroups[$oGroup->gid]['questions'][$oQuestion->qid] = $aQuestion;
            }
        }
        $aData['aRandomGroups'] = $aRandomGroups;
        $aData['aGroups'] = $aGroups;
        return $aData;
    }

    /**
     * A quick helper for saving question attribute (no need language)
     * @param $qid integer question id
     * @param $attribute string attribute
     * @param $value string value
     * @param $default default value (set to null)
     * @return boolean
     */
    private function _setQuestionAttribute($qid,$attribute,$value,$default = "")
    {

        if($value === $default) {
            \QuestionAttribute::model()->deleteAll("qid = :qid and attribute = :attribute",array(":qid"=>$qid,":attribute"=>$attribute));
            return true;
        }
        $oQuestionAttribute = \QuestionAttribute::model()->find("qid = :qid and attribute = :attribute",array(":qid"=>$qid,":attribute"=>$attribute));
        if(empty($oQuestionAttribute)) {
            $oQuestionAttribute = new \QuestionAttribute;
            $oQuestionAttribute->qid = $qid;
            $oQuestionAttribute->attribute = $attribute;
        }
        $oQuestionAttribute->value = $value;

        return $oQuestionAttribute->save();
    }
    /**
     * Register css and j as package (for priotity)
     * @return void
     */
    private function _registerNeededPackage()
    {
        Yii::setPathOfAlias(get_class($this),dirname(__FILE__));
        $min = (App()->getConfig('debug')) ? '.min' : '';
        if(!Yii::app()->clientScript->hasPackage('organizeSurvey-sortable')) {
            Yii::app()->clientScript->addPackage('organizeSurvey-sortable', array(
                'basePath'    => get_class($this).'.vendor.sortable',
                'js'          => array('Sortable'.$min.'.js'),
                //~ 'depends'      =>array('jquery'),
            ));
        }
        if(!Yii::app()->clientScript->hasPackage('organizeSurvey')) {
            Yii::app()->clientScript->addPackage('organizeSurvey', array(
                'basePath'    => get_class($this).'.assets',
                'js'          => array('organizeSurvey.js'),
                'css'          => array('organizeSurvey.css'),
                'depends'      =>array(
                    'jquery',
                    //~ 'bootstrap',
                    'organizeSurvey-sortable'
                ),
            ));
        }
        Yii::app()->getClientScript()->registerPackage('organizeSurvey');
    }
}
