<?php
defined('C5_EXECUTE') or die("Access Denied.");
$submittedData='';
foreach($questionAnswerPairs as $questionAnswerPair){
	$submittedData .= $questionAnswerPair['question']."\r\n".$questionAnswerPair['answerDisplay']."\r\n"."\r\n";

	if($questionAnswerPair['question'] == $titleInFormKey){
		$firstAdd = $questionAnswerPair['answerDisplay'];
	}
}

$body = sprintf($titleInForm,$firstAdd);
$body .= t("

%s

%s

%s

", $clientMessBefore,$submittedData, $clientMessAfter);