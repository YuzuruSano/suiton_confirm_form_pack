<?php
namespace Concrete\Package\SuitonConfirmFormPack\Block\Stform;

use \Concrete\Core\Block\BlockController;
use \Concrete\Core\Block\BlockType\BlockType;
use Core;
use Database;
use User;
use Page;
use Package;
use UserInfo;
use Exception;
use FileImporter;
use FileSet;
use File;
use Config;
use \Concrete\Core\File\Version;

defined('C5_EXECUTE') or die("Access Denied.");

class Controller extends BlockController
{
    public $btTable = 'btFormSuitonForm';
    public $btQuestionsTablename = 'btFormQuestionsSuitonForm';
    public $btAnswerSetTablename = 'btFormAnswerSetSuitonForm';
    public $btAnswersTablename = 'btFormAnswersSuitonForm';
    public $btInterfaceWidth = '420';
    public $btInterfaceHeight = '430';
    public $thankyouMsg = '';
    public $submitText = '';
    public $noSubmitFormRedirect = 0;
    protected $btCacheBlockRecord = false;
    protected $btExportTables = array('btFormSuitonForm', 'btFormQuestionsSuitonForm');
    protected $btExportPageColumns = array('redirectCID');
    protected $lastAnswerSetId = 0;
    protected $btName = "Suiton Confirm Form";

    /**
     * Used for localization. If we want to localize the name/description we have to include this.
     *
     * @return string
     */
    public function getBlockTypeDescription()
    {
        return t("Form block with confirm flow.");
    }

    public function getBlockTypeName()
    {
        return t("Suiton Confirm Form");
    }

    public function getJavaScriptStrings()
    {
        return array(
            'delete-question' => t('Are you sure you want to delete this question?'),
            'form-name' => t('Your form must have a name.'),
            'complete-required' => t('Please complete all required fields.'),
            'ajax-error' => t('AJAX Error.'),
            'form-min-1' => t('Please add at least one question to your form.'),
        );
    }

    protected function importAdditionalData($b, $blockNode)
    {
        if (isset($blockNode->data)) {
            foreach ($blockNode->data as $data) {
                if ($data['table'] != $this->getBlockTypeDatabaseTable()) {
                    $table = (string) $data['table'];
                    if (isset($data->record)) {
                        foreach ($data->record as $record) {
                            $aar = new \Concrete\Core\Legacy\BlockRecord($table);
                            $aar->bID = $b->getBlockID();
                            foreach ($record->children() as $node) {
                                $nodeName = $node->getName();
                                $aar->{$nodeName} = (string) $node;
                            }
                            if ($table == 'btFormQuestionsSuitonForm') {
                                $db = Database::connection();
                                $aar->questionSetId = $db->GetOne('select questionSetId from btFormSuitonForm where bID = ?', array($b->getBlockID()));
                            }
                            $aar->Replace();
                        }
                    }
                }
            }
        }
    }

    public function __construct($b = null)
    {
        parent::__construct($b);
        //$this->bID = intval($this->_bID);
        if (is_string($this->thankyouMsg) && !strlen($this->thankyouMsg)) {
            $this->thankyouMsg = $this->getDefaultThankYouMsg();
        }
        if (is_string($this->submitText) && !strlen($this->submitText)) {
            $this->submitText = $this->getDefaultSubmitText();
        }
    }


    /**
     * Internal helper function.
     */
    private function viewRequiresJqueryUI()
    {
        $whereInputTypes = "inputType = 'date' OR inputType = 'datetime'";
        $sql = "SELECT COUNT(*) FROM {$this->btQuestionsTablename} WHERE questionSetID = ? AND bID = ? AND ({$whereInputTypes})";
        $vals = array(intval($this->questionSetId), intval($this->bID));
        $JQUIFieldCount = Database::connection()->GetOne($sql, $vals);

        return (bool) $JQUIFieldCount;
    }

    // we are not using registerViewAssets because this block doesn't support caching
    // and we have some block record things we need to check.
    public function view()
    {
        if ($this->viewRequiresJqueryUI()) {
            $this->requireAsset('css', 'jquery/ui');
            $this->requireAsset('javascript', 'jquery/ui');
        }
        $this->requireAsset('css', 'core/frontend/errors');
        if ($this->displayCaptcha) {
            $this->requireAsset('css', 'core/frontend/captcha');
        }
        $this->requireAsset('suitosha');
    }
    public function getDefaultThankYouMsg()
    {
        return t("Thanks!");
    }

    public function getDefaultSubmitText()
    {
        return 'Submit';
    }

    /**
     * Form add or edit submit
     * (run after the duplicate method on first block edit of new page version).
     */
    public function save($data = array())
    {
        if (!$data || count($data) == 0) {
            $data = $_POST;
        }
        $data += array(
            'qsID' => null,
            'oldQsID' => null,
            'questions' => array(),
        );

        $b = $this->getBlockObject();
        $c = $b->getBlockCollectionObject();

        $db = Database::connection();
        if (intval($this->bID) > 0) {
            $q = "select count(*) as total from {$this->btTable} where bID = ".intval($this->bID);
            $total = $db->getOne($q);
        } else {
            $total = 0;
        }

        if (isset($_POST['qsID']) && $_POST['qsID']) {
            $data['qsID'] = $_POST['qsID'];
        }
        if (!$data['qsID']) {
            $data['qsID'] = time();
        }
        if (!$data['oldQsID']) {
            $data['oldQsID'] = $data['qsID'];
        }
        $data['bID'] = intval($this->bID);

        if (!empty($data['redirectCID'])) {
            $data['redirect'] = 1;
        } else {
            $data['redirect'] = 0;
            $data['redirectCID'] = 0;
        }

        if (empty($data['addFilesToSet'])) {
            $data['addFilesToSet'] = 0;
        }

        if (!isset($data['surveyName'])) {
            $data['surveyName'] = '';
        }

        if (!isset($data['submitText'])) {
            $data['submitText'] = '';
        }

        if (!isset($data['notifyMeOnSubmission'])) {
            $data['notifyMeOnSubmission'] = 0;
        }

        if (!isset($data['thankyouMsg'])) {
            $data['thankyouMsg'] = '';
        }

        if (!isset($data['titleInForm'])) {
            $data['titleInForm'] = '';
        }

        if (!isset($data['titleInFormKey'])) {
            $data['titleInFormKey'] = '';
        }

        if (!isset($data['clientSubject'])) {
            $data['clientSubject'] = '';
        }

        if (!isset($data['clientMessBefore'])) {
            $data['clientMessBefore'] = '';
        }

        if (!isset($data['clientMessAfter'])) {
            $data['clientMessAfter'] = '';
        }

        if (!isset($data['adminSubject'])) {
            $data['adminSubject'] = '';
        }

        if (!isset($data['adminMessBefore'])) {
            $data['adminMessBefore'] = '';
        }

        if (!isset($data['adminMessAfter'])) {
            $data['adminMessAfter'] = '';
        }

        if (!isset($data['displayCaptcha'])) {
            $data['displayCaptcha'] = 0;
        }

        $v = array($data['qsID'], $data['surveyName'], $data['submitText'], intval($data['notifyMeOnSubmission']), $data['recipientEmail'], $data['thankyouMsg'],$data['titleInForm'],$data['titleInFormKey'],$data['clientSubject'],$data['adminSubject'], $data['clientMessBefore'],$data['clientMessAfter'],$data['adminMessBefore'],$data['adminMessAfter'],intval($data['displayCaptcha']), intval($data['redirectCID']), intval($data['addFilesToSet']), intval($this->bID));

        //is it new?
        if (intval($total) == 0) {
            $q = "insert into {$this->btTable} (questionSetId, surveyName, submitText, notifyMeOnSubmission, recipientEmail, thankyouMsg,titleInForm,titleInFormKey,clientSubject,adminSubject, clientMessBefore,clientMessAfter,adminMessBefore,adminMessAfter,displayCaptcha, redirectCID, addFilesToSet, bID) values (?,?,?,?,?,?,?,?,?, ?, ?, ?, ?, ?, ?, ?, ?,?)";
        } else {
            $v[] = $data['qsID'];
            $q = "update {$this->btTable} set questionSetId = ?, surveyName=?, submitText=?, notifyMeOnSubmission=?, recipientEmail=?, thankyouMsg=?,titleInForm=?,titleInFormKey=?,clientSubject=?,adminSubject=?, clientMessBefore=?,clientMessAfter=?,adminMessBefore=?,adminMessAfter=?,displayCaptcha=?, redirectCID=?, addFilesToSet=? where bID = ? AND questionSetId= ?";
        }

        $rs = $db->query($q, $v);

        //Add Questions (for programmatically creating forms, such as during the site install)
        if (count($data['questions']) > 0) {
            $miniSurvey = new MiniSurvey();
            foreach ($data['questions'] as $questionData) {
                $miniSurvey->addEditQuestion($questionData, 0);
            }
        }

        $this->questionVersioning($data);

        return true;
    }

    /**
     * Ties the new or edited questions to the new block number.
     * New and edited questions are temporarily given bID=0, until the block is saved... painfully complicated.
     *
     * @param array $data
     */
    protected function questionVersioning($data = array())
    {
        $data += array(
            'ignoreQuestionIDs' => '',
            'pendingDeleteIDs' => '',
        );
        $db = Database::connection();
        $oldBID = intval($data['bID']);

        //if this block is being edited a second time, remove edited questions with the current bID that are pending replacement
        //if( intval($oldBID) == intval($this->bID) ){
            $vals = array(intval($data['oldQsID']));
        $pendingQuestions = $db->getAll('SELECT msqID FROM btFormQuestionsSuitonForm WHERE bID=0 && questionSetId=?', $vals);
        foreach ($pendingQuestions as $pendingQuestion) {
            $vals = array(intval($this->bID), intval($pendingQuestion['msqID']));
            $db->query('DELETE FROM btFormQuestionsSuitonForm WHERE bID=? AND msqID=?', $vals);
        }
        //}

        //assign any new questions the new block id
        $vals = array(intval($data['bID']), intval($data['qsID']), intval($data['oldQsID']));
        $rs = $db->query('UPDATE btFormQuestionsSuitonForm SET bID=?, questionSetId=? WHERE bID=0 && questionSetId=?', $vals);

        //These are deleted or edited questions.  (edited questions have already been created with the new bID).
        $ignoreQuestionIDsDirty = explode(',', $data['ignoreQuestionIDs']);
        $ignoreQuestionIDs = array(0);
        foreach ($ignoreQuestionIDsDirty as $msqID) {
            $ignoreQuestionIDs[] = intval($msqID);
        }
        $ignoreQuestionIDstr = implode(',', $ignoreQuestionIDs);

        //remove any questions that are pending deletion, that already have this current bID
        $pendingDeleteQIDsDirty = explode(',', $data['pendingDeleteIDs']);
        $pendingDeleteQIDs = array();
        foreach ($pendingDeleteQIDsDirty as $msqID) {
            $pendingDeleteQIDs[] = intval($msqID);
        }
        $vals = array($this->bID, intval($data['qsID']));
        $pendingDeleteQIDs = implode(',', $pendingDeleteQIDs);
        $unchangedQuestions = $db->query('DELETE FROM btFormQuestionsSuitonForm WHERE bID=? AND questionSetId=? AND msqID IN ('.$pendingDeleteQIDs.')', $vals);
    }

    /**
     * Duplicate will run when copying a page with a block, or editing a block for the first time within a page version (before the save).
     */
    public function duplicate($newBID)
    {
        $b = $this->getBlockObject();
        $c = $b->getBlockCollectionObject();

        $db = Database::connection();
        $v = array($this->bID);
        $q = "select * from {$this->btTable} where bID = ? LIMIT 1";
        $r = $db->query($q, $v);
        $row = $r->fetchRow();

        //if the same block exists in multiple collections with the same questionSetID
        if (count($row) > 0) {
            $oldQuestionSetId = $row['questionSetId'];

            //It should only generate a new question set id if the block is copied to a new page,
            //otherwise it will loose all of its answer sets (from all the people who've used the form on this page)
            $questionSetCIDs = $db->getCol("SELECT distinct cID FROM {$this->btTable} AS f, CollectionVersionBlocks AS cvb ".
                        "WHERE f.bID=cvb.bID AND questionSetId=".intval($row['questionSetId']));

            //this question set id is used on other pages, so make a new one for this page block
            if (count($questionSetCIDs) > 1 || !in_array($c->cID, $questionSetCIDs)) {
                $newQuestionSetId = time();
                $_POST['qsID'] = $newQuestionSetId;
            } else {
                //otherwise the question set id stays the same
                $newQuestionSetId = $row['questionSetId'];
            }

            //duplicate survey block record
            //with a new Block ID and a new Question
            $v = array($newQuestionSetId,$row['surveyName'],$row['submitText'], $newBID,$row['thankyouMsg'],$data['titleInForm'],$data['titleInFormKey'],$row['clientSubject'],$row['adminSubject'],$row['clientMessBefore'],$row['clientMessAfter'],$row['adminMessBefore'],$row['adminMessAfter'],intval($row['notifyMeOnSubmission']),$row['recipientEmail'],$row['displayCaptcha'], $row['addFilesToSet']);
            $q = "insert into {$this->btTable} ( questionSetId, surveyName, submitText, bID,thankyouMsg,titleInForm,titleInFormKey,clientSubject,adminSubject,clientMessBefore,clientMessAfter,adminMessBefore,adminMessAfter,notifyMeOnSubmission,recipientEmail,displayCaptcha,addFilesToSet) values (?,?,?,?,?,?,?,?,?, ?, ?, ?, ?, ?, ?, ?,?)";
            $result = $db->Execute($q, $v);

            $rs = $db->query("SELECT * FROM {$this->btQuestionsTablename} WHERE questionSetId=$oldQuestionSetId AND bID=".intval($this->bID));
            while ($row = $rs->fetchRow()) {
                $v = array($newQuestionSetId,intval($row['msqID']), intval($newBID), $row['question'], $row['addText'],$row['inputType'],$row['options'],$row['position'],$row['width'],$row['height'],$row['required'],$row['defaultDate']);
                $sql = "INSERT INTO {$this->btQuestionsTablename} (questionSetId,msqID,bID,question,addText,inputType,options,position,width,height,required,defaultDate) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $db->Execute($sql, $v);
            }

            return $newQuestionSetId;
        }

        return 0;
    }

    /**
     * Users submits the completed survey.
     *
     * @param int $bID
     */
    public function action_submit_form($bID = false)
    {
        if ($this->bID != $bID) {
            return false;
        }

        $ip = Core::make('helper/validation/ip');
        $this->view();

        if ($ip->isBanned()) {
            $this->set('invalidIP', $ip->getErrorMessage());

            return;
        }

        $txt = Core::make('helper/text');
        $db = Database::connection();


        //question set id
        $qsID = intval($_POST['qsID']);
        if ($qsID == 0) {
            throw new Exception(t("Oops, something is wrong with the form you posted (it doesn't have a question set id)."));
        }

        //get all questions for this question set
        $rows = $db->GetArray("SELECT * FROM {$this->btQuestionsTablename} WHERE questionSetId=? AND bID=? order by position asc, msqID", array($qsID, intval($this->bID)));

        $errorDetails = array();

        // check captcha if activated
        if ($this->displayCaptcha) {
            $captcha = Core::make('helper/validation/captcha');
            if (!$captcha->check()) {
                $errors['captcha'] = t("Incorrect captcha code");
                $_REQUEST['ccmCaptchaCode'] = '';
            }
        }

        //checked required fields
        foreach ($rows as $row) {
            if ($row['inputType'] == 'datetime') {
                if (!isset($datetime)) {
                    $datetime = Core::make('helper/form/date_time');
                }
                $translated = $datetime->translate('Question'.$row['msqID']);
                if ($translated) {
                    $_POST['Question'.$row['msqID']] = $translated;
                }
            }
            if (intval($row['required']) == 1) {
                $notCompleted = 0;
                if ($row['inputType'] == 'email') {
                    if (!Core::make('helper/validation/strings')->email($_POST['Question' . $row['msqID']])) {
                        $errors['emails'] = t('You must enter a valid email address.');
                        $errorDetails[$row['msqID']]['emails'] = $errors['emails'];
                    }
                }
                if ($row['inputType'] == 'checkboxlist') {
                    $answerFound = 0;
                    foreach ($_POST as $key => $val) {
                        if (strstr($key, 'Question'.$row['msqID'].'_') && strlen($val)) {
                            $answerFound = 1;
                        }
                    }
                    if (!$answerFound) {
                        $notCompleted = 1;
                    }
                } elseif ($row['inputType'] == 'fileupload') {//$_POST['files']〜は確認画面に遷移後発生する
                    if ((!isset($_FILES['Question'.$row['msqID']]) || !is_uploaded_file($_FILES['Question'.$row['msqID']]['tmp_name'])) && (!isset($_POST['files']['Question'.$row['msqID']]['tmp_name']) || !file_exists($_POST['files']['Question'.$row['msqID']]['tmp_name']))) {
                        $notCompleted = 1;
                    }
                } elseif (!strlen(trim($_POST['Question'.$row['msqID']]))) {
                    $notCompleted = 1;
                }
                if ($notCompleted) {
                    $errors['CompleteRequired'] = t("Complete required fields *");
                    $errorDetails[$row['msqID']]['CompleteRequired'] = $errors['CompleteRequired'];
                }
            }
        }

        if (count($errors)) {
            $this->set('formResponse', t('Please correct the following errors:'));
            $this->set('errors', $errors);
            $this->set('errorDetails', $errorDetails);
        } elseif(isset($_POST['post_type']) && $_POST['post_type'] == 'confirm'){
            /* ===============================================
            確認画面以降のファイルアップロード処理を追加
            =============================================== */
            // ファイルアップロードフォームからの初回アップロードを確認画面のviewに渡す
            // viewではhiddenで扱う
            if($_FILES){
                $this->clean_tmp_dir();
                $now = date('YmdHis');
                // ファイルインフォデータベースを開く
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                foreach($_FILES as $key => $val){
                    if(!$val['tmp_name']) continue;
                    $files[$key]['name'] = $val['name'];
                    // アップロードされたファイルが画像かどうかチェック
                    list($mime,$ext) = explode('/',finfo_file($finfo, $val['tmp_name']));
                    if($mime!='image') $err[] = 'ファイル{$key} は画像を選択してください';
                    if($mime!='image') unset($files[$key]);
                    if($mime!='image') continue;
                    // 仮ディレクトリへファイルをアップロード
                    copy($val['tmp_name'] , $this->get_tmp_dir().$now.'_'.$key.$ext);
                    $files[$key]['tmp_name'] =  $this->get_tmp_dir().$now.'_'.$key.$ext;
                }
                finfo_close($finfo);
            }else{
                //確認画面からファイルを入れ替えせず、再度確認画面に進んだ場合
                //確認画面から送信に進んだ場合
                if(is_array($_POST['files'])){
                    $files = $_POST['files'];
                }else{
                    $files = null;
                }
            }
            //ファイル関連データをviewへ
            $this->set('files_data', $files);
            //確認ステータスをviewへ
            $this->set('confirm', 'confirm_mode');
        }else { //no form errors
            $tmpFileIds = array();
            foreach ($rows as $row) {
                if ($row['inputType'] != 'fileupload') {
                    continue;
                }

                $questionName = 'Question'.$row['msqID'];

                if (!intval($row['required']) &&
                    (
                    !isset($_FILES[$questionName]['tmp_name']) || !is_uploaded_file($_FILES[$questionName]['tmp_name'])
                    )
                    &&
                    (
                     !isset($_POST['files'][$questionName]['tmp_name']) || !file_exists($_POST['files'][$questionName]['tmp_name'])
                    )
                ) {
                    continue;
                }

                $fi = new FileImporter();
                //$_POST['files']〜は確認画面に遷移後発生する
                if(isset($_POST['files'][$questionName]['tmp_name']) || is_uploaded_file($_POST['files'][$questionName]['tmp_name'])){
                    $tmp_name = $_POST['files'][$questionName]['tmp_name'];
                    $name = $_POST['files'][$questionName]['name'];
                }else{
                    $tmp_name = $_FILES[$questionName]['tmp_name'];
                    $name = $_FILES[$questionName]['name'];
                }
                //ファイルマネージャにインポート
                $resp = $fi->import($tmp_name , $name);

                if (!($resp instanceof Version)) {
                    switch ($resp) {
                        case FileImporter::E_FILE_INVALID_EXTENSION:
                            $errors['fileupload'] = t('Invalid file extension.');
                            $errorDetails[$row['msqID']]['fileupload'] = $errors['fileupload'];
                            break;
                        case FileImporter::E_FILE_INVALID:
                            $errors['fileupload'] = t('Invalid file.');
                            $errorDetails[$row['msqID']]['fileupload'] = $errors['fileupload'];
                            break;
                    }
                } else {
                    $tmpFileIds[intval($row['msqID'])] = $resp->getFileID();
                    if (intval($this->addFilesToSet)) {
                        $fs = new FileSet();
                        $fs = $fs->getByID($this->addFilesToSet);
                        if ($fs->getFileSetID()) {
                            $fs->addFileToSet($resp);
                        }
                    }
                }
            }
            //一時ファイルを空に
            $this->clean_tmp_dir();

            //save main survey record
            $u = new User();
            $uID = 0;
            if ($u->isRegistered()) {
                $uID = $u->getUserID();
            }
            $q = "insert into {$this->btAnswerSetTablename} (questionSetId, uID) values (?,?)";
            $db->query($q, array($qsID, $uID));
            $answerSetID = $db->Insert_ID();
            $this->lastAnswerSetId = $answerSetID;

            $questionAnswerPairs = array();

            if (Config::get('concrete.email.form_block.address') && strstr(Config::get('concrete.email.form_block.address'), '@')) {
                $formFormEmailAddress = Config::get('concrete.email.form_block.address');
            } else {
                $adminUserInfo = UserInfo::getByID(USER_SUPER_ID);
                $formFormEmailAddress = $adminUserInfo->getUserEmail();
            }
            $replyToEmailAddress = $formFormEmailAddress;
            //loop through each question and get the answers
            foreach ($rows as $row) {
                //save each answer
                $answerDisplay = '';
                if ($row['inputType'] == 'checkboxlist') {
                    $answer = array();
                    $answerLong = "";
                    $keys = array_keys($_POST);
                    foreach ($keys as $key) {
                        if (strpos($key, 'Question'.$row['msqID'].'_') === 0) {
                            $answer[] = $txt->sanitize($_POST[$key]);
                        }
                    }
                } elseif ($row['inputType'] == 'text') {
                    $answerLong = $txt->sanitize($_POST['Question'.$row['msqID']]);
                    $answer = '';
                } elseif ($row['inputType'] == 'fileupload') {
                    $answerLong = "";
                    $answer = intval($tmpFileIds[intval($row['msqID'])]);
                    if ($answer > 0) {
                        $answerDisplay = File::getByID($answer)->getVersion()->getDownloadURL();
                    } else {
                        $answerDisplay = t('No file specified');
                    }
                } elseif ($row['inputType'] == 'url') {
                    $answerLong = "";
                    $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
                } elseif ($row['inputType'] == 'email') {
                    $answerLong = "";
                    $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
                    if (!empty($row['options'])) {
                        $settings = unserialize($row['options']);
                        if (is_array($settings) && array_key_exists('send_notification_from', $settings) && $settings['send_notification_from'] == 1) {
                            $email = $txt->email($answer);
                            if (!empty($email)) {
                                $replyToEmailAddress = $email;
                            }
                        }
                    }
                } elseif ($row['inputType'] == 'telephone') {
                    $answerLong = "";
                    $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
                } else {
                    $answerLong = "";
                    $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
                }

                if (is_array($answer)) {
                    $answer = implode(',', $answer);
                }

                $questionAnswerPairs[$row['msqID']]['question'] = $row['question'];
                $questionAnswerPairs[$row['msqID']]['answer'] = $txt->sanitize($answer.$answerLong);
                $questionAnswerPairs[$row['msqID']]['answerDisplay'] = strlen($answerDisplay) ? $answerDisplay : $questionAnswerPairs[$row['msqID']]['answer'];

                $v = array($row['msqID'],$answerSetID,$answer,$answerLong);
                $q = "insert into {$this->btAnswersTablename} (msqID,asID,answer,answerLong) values (?,?,?,?)";
                $db->query($q, $v);
            }
            $foundSpam = false;

            $submittedData = '';
            foreach ($questionAnswerPairs as $questionAnswerPair) {
                $submittedData .= $questionAnswerPair['question']."\r\n".$questionAnswerPair['answer']."\r\n"."\r\n";
            }
            $antispam = Core::make('helper/validation/antispam');
            if (!$antispam->check($submittedData, 'form_block')) {
                // found to be spam. We remove it
                $foundSpam = true;
                $q = "delete from {$this->btAnswerSetTablename} where asID = ?";
                $v = array($this->lastAnswerSetId);
                $db->Execute($q, $v);
                $db->Execute("delete from {$this->btAnswersTablename} where asID = ?", array($this->lastAnswerSetId));
            }

            if (intval($this->notifyMeOnSubmission) > 0 && !$foundSpam) {
                if (Config::get('concrete.email.form_block.address') && strstr(Config::get('concrete.email.form_block.address'), '@')) {
                    $formFormEmailAddress = Config::get('concrete.email.form_block.address');
                } else {
                    $adminUserInfo = UserInfo::getByID(USER_SUPER_ID);
                    $formFormEmailAddress = $adminUserInfo->getUserEmail();
                }

                $mh = Core::make('helper/mail');
                /* ===============================================
                管理者用メール
                =============================================== */
                $mh->to($this->recipientEmail);
                $mh->from($formFormEmailAddress);
                $mh->replyto($replyToEmailAddress);
                $mh->addParameter('formName', $this->surveyName);
                $mh->addParameter('questionSetId', $this->questionSetId);
                $mh->addParameter('questionAnswerPairs', $questionAnswerPairs);
                $mh->addParameter('adminMessBefore', $this->adminMessBefore);
                $mh->addParameter('adminMessAfter', $this->adminMessAfter);
                $mh->load('suiton_block_form_submission','suiton_confirm_form_pack');//application/mail の該当ファイルでメッセージ内容変更可
                if($this->adminSubject){
                    $mh->setSubject(sprintf($this->adminSubject, $this->surveyName));//件名編集
                }else{
                    $mh->setSubject(t('%s Form Submission', $this->surveyName));//件名編集
                }
                //echo $mh->body.'<br>';
                @$mh->sendMail();
                /* ===============================================
                クライアント向けメール
                =============================================== */
                if($replyToEmailAddress != $formFormEmailAddress){
                    $mh->reset();
                    $mh->to( $replyToEmailAddress );
                    $mh->from( $this->recipientEmail );
                    $mh->addParameter('formName', $this->surveyName);
                    $mh->addParameter('questionSetId', $this->questionSetId);
                    $mh->addParameter('questionAnswerPairs', $questionAnswerPairs);
                    $mh->addParameter('titleInForm', $this->titleInForm);
                    $mh->addParameter('titleInFormKey', $this->titleInFormKey);
                    $mh->addParameter('clientMessBefore', $this->clientMessBefore);
                    $mh->addParameter('clientMessAfter', $this->clientMessAfter);
                    $mh->load('suiton_block_form_confirm','suiton_confirm_form_pack');//application/mail の該当ファイルでメッセージ内容変更可
                    if($this->clientSubject){
                        $mh->setSubject(sprintf($this->clientSubject, $this->surveyName).":".time());//件名編集
                    }else{
                        $mh->setSubject(t('Thank you for Submission to %s', $this->surveyName).":".time());//件名編集
                    }
                    //echo $mh->body.'<br>';
                    @$mh->sendMail();
                }
            }

            if (!$this->noSubmitFormRedirect) {
                if ($this->redirectCID > 0) {
                    $pg = Page::getByID($this->redirectCID);
                    if (is_object($pg) && $pg->cID) {
                        $this->redirect($pg->getCollectionPath());
                    }
                }
                $c = Page::getCurrentPage();
                header("Location: ".Core::make('helper/navigation')->getLinkToCollection($c, true)."?surveySuccess=1&qsid=".$this->questionSetId."#formblock".$this->bID);
                exit;
            }
        }
    }

    public function delete()
    {
        $db = Database::connection();

        $deleteData['questionsIDs'] = array();
        $deleteData['strandedAnswerSetIDs'] = array();

        $miniSurvey = new MiniSurvey();
        $info = $miniSurvey->getMiniSurveyBlockInfo($this->bID);

        //get all answer sets
        $q = "SELECT asID FROM {$this->btAnswerSetTablename} WHERE questionSetId = ".intval($info['questionSetId']);
        $answerSetsRS = $db->query($q);

        //delete the questions
        $deleteData['questionsIDs'] = $db->getAll("SELECT qID FROM {$this->btQuestionsTablename} WHERE questionSetId = ".intval($info['questionSetId']).' AND bID='.intval($this->bID));
        foreach ($deleteData['questionsIDs'] as $questionData) {
            $db->query("DELETE FROM {$this->btQuestionsTablename} WHERE qID=".intval($questionData['qID']));
        }

        //delete left over answers
        $strandedAnswerIDs = $db->getAll('SELECT fa.aID FROM `btFormAnswersSuitonForm` AS fa LEFT JOIN btFormQuestionsSuitonForm as fq ON fq.msqID=fa.msqID WHERE fq.msqID IS NULL');
        foreach ($strandedAnswerIDs as $strandedAnswer) {
            $db->query('DELETE FROM `btFormAnswersSuitonForm` WHERE aID='.intval($strandedAnswer['aID']));
        }

        //delete the left over answer sets
        $deleteData['strandedAnswerSetIDs'] = $db->getAll('SELECT aset.asID FROM btFormAnswerSetSuitonForm AS aset LEFT JOIN btFormAnswersSuitonForm AS fa ON aset.asID=fa.asID WHERE fa.asID IS NULL');
        foreach ($deleteData['strandedAnswerSetIDs'] as $strandedAnswerSetIDs) {
            $db->query('DELETE FROM btFormAnswerSetSuitonForm WHERE asID='.intval($strandedAnswerSetIDs['asID']));
        }

        //delete the form block
        $q = "delete from {$this->btTable} where bID = '{$this->bID}'";
        $r = $db->query($q);

        parent::delete();

        return $deleteData;
    }

    /**
     * 一時保存ファイルの取得
     *
     *
     */
    protected function get_tmp_dir(){
        return $this->get_pkg_path().'/tmp/';
    }
    /**
     * パッケージディレクトリのパス取得
     *
     *
     */
    protected function get_pkg_path(){
        $b = $this->getBlockObject();
        $pkgID = $b->getPackageID();
        $package = Package::getByID($pkgID);

        return $pkgpath = $package->getPackagePath();
    }
    /**
     * 一時保存ディクトリを空にする
     *
     *
     */
    protected function clean_tmp_dir() {
        $dir = $this->get_tmp_dir();
        if ( $dirHandle = opendir ($dir)) {
            while ( false !== ( $fileName = readdir ( $dirHandle ) ) ) {
                if ( $fileName != "." && $fileName != ".." && $fileName != ".gitkeep" ) {
                    unlink ( $dir.$fileName );
                }
            }
            closedir ( $dirHandle );
        }
    }
}
