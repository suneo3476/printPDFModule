<?php
ini_set('display_erros',1);
include_once('japanese.php');

$cate_mem_api = 'http://media.cs.inf.shizuoka.ac.jp/api.php?format=json&action=query&list=categorymembers&cmlimit=max&cmtitle=Category:';
$query_category = $_GET['category'];
$cate_mem_call = $cate_mem_api.$query_category;
$cate_mem_json = file_get_contents($cate_mem_call);
$cate_mem_array = json_decode($cate_mem_json);

function extractAuthor($str){
	preg_match_all('/\s(\S+?).[0-9]/u', $str, $matches);
	return $matches[1][0];
}
function extractDate($str){
	preg_match_all('/(\d{2,4}\D\d{1,2}\D\d{1,2}\D?).$/u', $str, $matches);
//	dp($matches);
	return $matches[1][0];
}
function extractTitle($str){
	preg_match_all('/^(\S+)\s/u', $str, $matches);
	return $matches[1][0];
}
function extractCategory($str){
	preg_match_all('/\[\[Category:(.+?)\]\]/u', $str, $matches);
	return $matches[1];
}
function extractBody($str){
	$str = mb_convert_kana($str,'s');
	$str = preg_replace('/<\/?blockquote>/u', toUTF8('　'), $str);
	$str = preg_replace('/\r\n|\r|\n{2,}/u', toUTF8('　'), $str);
	$str = preg_replace('/\[\[.+?\]\]/u', '', $str);
	$str = preg_replace('/<<.+?>>/u', '', $str);
	return $str;
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
	$raw_body = $page_array->{'query'}->{'pages'}->{$cate_mem_pageid}->{'revisions'}[0]->{'*'};
	$page->{$key}->{'category'} = extractCategory($raw_body);
	$page->{$key}->{'body'} = extractBody($raw_body);
}

$pdf = new PDF_Japanese('P', 'mm', 'A4');
$pageno = $pdf->setSourceFile('template.pdf');

$pdf->AddSJIShwFont();

$pdf->SetMargins(16,16,16);
$pdf->SetAutoPageBreak(false,16);

function cutTitle($str){
	if(mb_strlen($str)>25)
		return mb_substr($str,0,20)."..";
	else
		return $str;
}

$api = 0;
foreach($page as $value){
	/*non-perfect is cont'd*/
	$contd_flag = false;
	foreach($value->{'category'} as $key => $cat){
		if($cat == toUTF8("書きかけ") || $cat == toUTF8("扉")){
			$contd_flag = true;
		}
	}
	if($contd_flag == true){
		continue;
	}

	$pdf->AddPage();
	$tplidx = $pdf->ImportPage(1);
	$pdf->useTemplate($tplidx);

	$title = toSJIS(cutTitle($value->{'title'}));
	$pdf->SetXY(16,12);
	$pdf->SetFont('SJIS-hw', '', 18);
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

	$pdf->SetXY(20,42);
	$pdf->SetFont('SJIS-hw', '', 11);
	$body = toSJIS($value->{'body'});
	$len = mb_strlen($body, 'SJIS');
	$let = 0;
	for($i = 0; $i < $len; $i++) {
		$c = mb_substr($body, $i, 1, 'SJIS');
		if(preg_match("/^[a-zA-Z0-9,.:\"\?\!]$/u", $c)){
			$w = 3.8/2;
			$let_delta = 1.0/2;
		}else{
			$w = 3.8;
			$let_delta = 1.0;
		}
		if($let>=43 && ($c=='、' || $c=='。' || $c=='」' || $c=='”')){
			$pdf->Cell($w, 6, $c, 0, 0);
			$let += $let_delta;
		}else if($let>=44){
			$pdf->Ln();
			$pdf->Cell($w, 6, $c, 0, 0);
			$let = 0;
		}else if($c=='　'){
			$pdf->Ln();
			$pdf->Cell($w, 6, "", 0, 0);
			$let = 0;
		}else{
			$pdf->Cell($w, 6, $c, 0, 0);
			$let += $let_delta;
		}
	}

	$pdf->SetXY(16,267);
	$pdf->SetFont('SJIS-hw', '', 22);
	$pdf->Write(15,toSJIS($query_category));

	$author = toSJIS($value->{'author'});
	$date = toSJIS($value->{'date'});
	$author_date = 'Author:'.$author.'  Date:'.$date;
	$pdf->SetXY(12,282);
	$pdf->SetFont('SJIS-hw', '', 16);
	$pdf->SetTextColor(255-16);
	$pdf->Write(15,$author_date);
}
$pdf->Output('mediacard-'.toSJIS($query_category).'.pdf', "I");

$pdf->Close();
function toSJIS($in_ConvStr,$in_BaseEncode = 'UTF-8'){
    return (mb_convert_encoding($in_ConvStr, "SJIS", $in_BaseEncode));
}
function toUTF8($in_ConvStr,$in_BaseEncode = 'SJIS'){
    return (mb_convert_encoding($in_ConvStr, "UTF-8", $in_BaseEncode));
}
function mb_str_split($text){
	return preg_split("//u", $text, -1, PREG_SPLIT_NO_EMPTY);
}
/*Debug Print*/
function dp($text){
	echo '<pre>';
	print_r($text);
	echo '</pre>';
}
/*get type and encoding of string*/
function typeenc($str){
	echo $str.":".gettype($str).":".mb_detect_encoding($str)."<br>";
}
?>