<?php
/************************************************************
 * DESIGNERS: SCROLL DOWN! (IGNORE ALL THIS STUFF AT THE TOP)
 ************************************************************/
defined('C5_EXECUTE') or die("Access Denied.");
use Concrete\Package\SuitonConfirmFormPack\Block\Stform\MiniSurvey;

$survey = $controller;
$miniSurvey = new MiniSurvey($b);
$miniSurvey->frontEndMode = true;

//Clean up variables from controller so html is easier to work with...
$bID = intval($bID);
$qsID = intval($survey->questionSetId);
$formAction = $view->action('submit_form').'#formblock'.$bID;

$questionsRS = $miniSurvey->loadQuestions($qsID, $bID);
$questions = array();
while ($questionRow = $questionsRS->fetchRow()) {
	$question = $questionRow;
	$question['input'] = $miniSurvey->loadInputType($questionRow, false);

	//Make type names common-sensical
	if ($questionRow['inputType'] == 'text') {
		$question['type'] = 'textarea';
	} else if ($questionRow['inputType'] == 'field') {
		$question['type'] = 'text';
	} else {
		$question['type'] = $questionRow['inputType'];
	}

	$question['labelFor'] = 'for="Question' . $questionRow['msqID'] . '"';
	$question['qname'] = 'Question'.$questionRow['msqID'];
	//Remove hardcoded style on textareas
	if ($question['type'] == 'textarea') {
		$question['input'] = str_replace('style="width:95%"', '', $question['input']);
	}

	$questions[] = $question;
}

//Prep thank-you message
$success = (\Request::request('surveySuccess') && \Request::request('qsid') == intval($qsID));
$thanksMsg = $survey->thankyouMsg;

//Collate all errors and put them into divs
$errorHeader = isset($formResponse) ? $formResponse : null;
$errors = isset($errors) && is_array($errors) ? $errors : array();
if (isset($invalidIP) && $invalidIP) {
	$errors[] = $invalidIP;
}
$errorDivs = '';
foreach ($errors as $error) {
	$errorDivs .= '<div class="error">'.$error."</div>\n"; //It's okay for this one thing to have the html here -- it can be identified in CSS via parent wrapper div (e.g. '.formblock .error')
}

//Prep captcha
$surveyBlockInfo = $miniSurvey->getMiniSurveyBlockInfoByQuestionId($qsID, $bID);
$captcha = $surveyBlockInfo['displayCaptcha'] ? Loader::helper('validation/captcha') : false;

/******************************************************************************
* DESIGNERS: CUSTOMIZE THE FORM HTML STARTING HERE...
*/?>

<div id="formblock<?php  echo $bID; ?>" class="ccm-block-type-form">
<form enctype="multipart/form-data" class="form-stacked miniSurveyView" id="miniSurveyView<?php  echo $bID; ?>" method="post" action="<?php  echo $formAction ?>">

	<?php  if ($success): ?>

		<div class="alert alert-success">
			<?php  echo h($thanksMsg); ?>
		</div>

	<?php  elseif ($errors): ?>

		<div class="alert alert-danger">
			<?php  echo $errorHeader; ?>
			<?php  echo $errorDivs; /* each error wrapped in <div class="error">...</div> */ ?>
		</div>

	<?php  endif; ?>


	<div class="fields">
		<?php  foreach ($questions as $question): ?>
			<div class="form-group field field-<?php  echo $question['type']; ?> <?php echo $errorDetails[$question['msqID']] ? 'has-error' : ''?>">
				<label class="control-label" <?php  echo $question['labelFor']; ?>>
					<?php  echo $question['question']; ?>
                    <?php if ($question['required']): ?>
                        <span class="text-muted small" style="font-weight: normal">*</span>
                    <?php  endif; ?>
				</label>

				<?php if($confirm == 'confirm_mode'): ?>
					<?php
					if($question['inputType'] == 'fileupload'){
						if(!is_null($files_data)){
							foreach($files_data as $key => $val){
								if($key == $question['qname']){
									echo '<div id="'.$question['qname'].'" class="form_confirm_file">'.$val['name'].'</div>';
									echo '<input id="hidden_name_'.$question['qname'].'" type="hidden" value="'.$val['name'].'" name="files['.$question['qname'].'][name]">';
									echo '<input id="hidden_name_tmp_name_'.$question['qname'].'" type="hidden" value="'.$val['tmp_name'].'" name="files['.$question['qname'].'][tmp_name]">';
								}
							}
						}else{
							if($question['addText']) echo '<p>'.nl2br(h($question['addText'])).'</p>' ;
							echo $question['input'];
						}
					}else{
						$q = preg_replace('/name="/', 'disabled="disabled" name="', $question['input']);
						$q = preg_replace('/id=".+?"/', 'id=""', $q);
						$q = preg_replace('/name=".+?"/', 'id=""', $q);
						echo '<div class="form_confirm">'.$q.'</div>';
						echo '<div class="form_entity">'.$question['input'].'</div>';
					}
					?>
				<?php else: ?>
					<?php
					if($question['inputType'] == 'fileupload'){
						if(!is_null($files_data)){
							foreach($files_data as $key => $val){
								if($key == $question['qname']){
									echo '<div id="'.$question['qname'].'" class="form_confirm_file">'.$val['name'].'</div>';
									echo '<input id="hidden_name_'.$question['qname'].'" type="hidden" value="'.$val['name'].'" name="files['.$question['qname'].'][name]">';
									echo '<input id="hidden_name_tmp_name_'.$question['qname'].'" type="hidden" value="'.$val['tmp_name'].'" name="files['.$question['qname'].'][tmp_name]">';
								}
							}
						}else{
							if($question['addText']) echo '<p>'.nl2br(h($question['addText'])).'</p>' ;
							echo $question['input'];
						}
					}else{
						if($question['addText']) echo '<p>'.nl2br(h($question['addText'])).'</p>' ;
						echo $question['input'];
					}?>
				<?php endif; ?>
			</div>
		<?php  endforeach; ?>
	</div><!-- .fields -->

	<?php  if ($captcha): ?>
		<div class="form-group captcha">
			<?php
			$captchaLabel = $captcha->label();
			if (!empty($captchaLabel)) {
				?>
				<label class="control-label"><?php echo $captchaLabel; ?></label>
				<?php
			}
			?>
			<div><?php  $captcha->display(); ?></div>
			<div><?php  $captcha->showInput(); ?></div>
		</div>
	<?php  endif; ?>

	<div class="form-actions">
		<?php if($confirm == 'confirm_mode'): ?>
			<a href="dummy" class="backbtn">訂正する</a>
			<input class="submit" type="submit" name="Submit" class="btn btn-primary" value="<?php  echo h(t($survey->submitText)); ?>" />
			<input class="hidden_status" type="hidden" value="sendok" name="post_type">
		<?php else: ?>
			<input type="submit" name="Submit" class="btn btn-primary" value="確認" />
			<input type="hidden" value="confirm" name="post_type">
		<?php endif; ?>
	</div>

	<input name="qsID" type="hidden" value="<?php  echo $qsID; ?>" />
	<input name="pURI" type="hidden" value="<?php  echo isset($pURI) ? $pURI : ''; ?>" />

</form>
</div><!-- .formblock -->
