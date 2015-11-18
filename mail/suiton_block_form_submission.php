<?php
defined('C5_EXECUTE') or die("Access Denied.");
/* ===============================================
$submittedDataの順番はブロック内の順番が採用される
=============================================== */
$submittedData='';
foreach($questionAnswerPairs as $questionAnswerPair){
	$submittedData .= $questionAnswerPair['question']."\r\n".$questionAnswerPair['answerDisplay']."\r\n"."\r\n";
}
$formDisplayUrl=URL::to('/dashboard/reports/suitonforms') . '?qsid='.$questionSetId;

$body = t("

%s

%s

%s

To view all of this form's submissions, visit %s

", $adminMessBefore,$submittedData, $adminMessAfter,$formDisplayUrl);