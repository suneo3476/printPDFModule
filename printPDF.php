<?php
ini_set('display_errors', 1);
include_once('japanese.php');

$cate_mem_api = 'http://media.cs.inf.shizuoka.ac.jp/api.php?format=json&action=query&list=categorymembers&cmlimit=max&cmtitle=Category:';
$category = $_GET['category'];
$cate_mem_call = $cate_mem_api.$category;
$cate_mem_json = file_get_contents($cate_mem_call);
$cate_mem_array = json_decode($cate_mem_json);

function extractAuthor($str){
	preg_match_all('/\s(\S+?).[0-9]/u', $str, $matches);
	return $matches[1][0];
}
function extractDate($str){
	preg_match_all('/([0-9]{2,4}\.[0-9]{1,2}\.[0-9]{1,2}).$/u', $str, $matches);
	return $matches[1][0];
}
function extractTitle($str){
	preg_match_all('/^(\S+)\s/u', $str, $matches);
	return $matches[1][0];
}
function formatBody($str){
	$str = mb_convert_kana($str,'s');
	$str = preg_replace('/\r\n|\r|\n{2,}/u', toUTF8('　'), $str);
	$str = preg_replace('/\[\[.+?\]\]/u', '', $str);
	$str = preg_replace('/<<.+?>>/u', '', $str);
	return $str;
}
function parseBody($pdf, $str){
	$e = 'SJIS';
	$l = mb_strlen($str, $e);
	if($l > 900){
		$h = 7;
	}else if($l > 800){
		$h = 8;
	}else if($l > 700){
		$h = 9;
	}
	for($i = 0; $i < $l; $i++) {
		$c = mb_substr($str, $i, 1, $e);
		if($c=='　'){
			$pdf->Ln();
			$pdf->Write($h,"　");
		}else{
			$pdf->Write($h,$c);
		}
	}
	return;
}

foreach($cate_mem_array->{'query'}->{'categorymembers'} as $key => $value){
	$cate_mem_pageid = $value->{'pageid'};
	$page_api = 'http://media.cs.inf.shizuoka.ac.jp/api.php?format=json&action=query&prop=revisions&rvprop=content&pageids=';
	$page_json = file_get_contents($page_api.$cate_mem_pageid);
	$page_array = json_decode($page_json);
	$page->{$key}->{'full_title'} = $page_array->{'query'}->{'pages'}->{$cate_mem_pageid}->{'title'};
	$page->{$key}->{'author'} = extractAuthor($page->{$key}->{'full_title'});
	$page->{$key}->{'date'} = extractDate($page->{$key}->{'full_title'});
	$page->{$key}->{'title'} = extractTitle($page->{$key}->{'full_title'});
	$page->{$key}->{'body'} = formatBody($page_array->{'query'}->{'pages'}->{$cate_mem_pageid}->{'revisions'}[0]->{'*'});
}

$pdf = new PDF_Japanese('P', 'mm', 'A4');
$pageno = $pdf->setSourceFile('template.pdf');

$pdf->AddSJIShwFont();

$pdf->SetMargins(16,16,16);
$pdf->SetAutoPageBreak(false,16);

function cutTitle($str){
	if(mb_strlen($str)>17)
		return mb_substr($str,0,17)."..";
	else
		return $str;
}

$api = 0;
foreach($page as $value){
	$pdf->AddPage();
	$tplidx = $pdf->ImportPage(1);
	$pdf->useTemplate($tplidx);

	$title = toSJIS(cutTitle($value->{'title'}));
	$pdf->SetXY(16,12);
	$pdf->SetFont('SJIS-hw', '', 24);
	$pdf->SetTextColor(0);
	$pdf->Write(15,$title);

	$full_title = $value->{'full_title'};
	$api_url = 'http://'.$api.'.chart.apis.google.com/chart?chs=240x240&cht=qr&chl=';
	$target_url = 'http://media.cs.inf.shizuoka.ac.jp/index.php/'.urlencode($full_title);
	$call_url = $api_url.$target_url;
	$qrdata = file_get_contents($call_url);
	file_put_contents('img/'.toSJIS($full_title).'.png', $qrdata);
	$pdf->Image('img/'.toSJIS($full_title).'.png', 172,17, 21,21, PNG);
	if(++$api>9){
		$api = 0;
	}

	$body = toSJIS($value->{'body'});
	$pdf->SetXY(20,42);
	$pdf->SetFont('SJIS-hw', '', 14);
	parseBody($pdf, $body);

	$pdf->SetXY(16,267);
	$pdf->SetFont('SJIS-hw', '', 22);
	$pdf->Write(15,toSJIS($category));

	$author = toSJIS($value->{'author'});
	$date = toSJIS($value->{'date'});
	$author_date = 'Author:'.$author.'　Date:'.$date;
	$pdf->SetXY(12,282);
	$pdf->SetFont('SJIS-hw', '', 16);
	$pdf->SetTextColor(255-16);
	$pdf->Write(15,$author_date);
}
$pdf->Output('mediacard-'.toSJIS($category).'.pdf', "I");

$pdf->Close();

function toSJIS($in_ConvStr,$in_BaseEncode = 'UTF-8'){
    return (mb_convert_encoding($in_ConvStr, "SJIS", $in_BaseEncode));
}
function toUTF8($in_ConvStr,$in_BaseEncode = 'SJIS'){
    return (mb_convert_encoding($in_ConvStr, "UTF-8", $in_BaseEncode));
}
?>