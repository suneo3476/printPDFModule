<?php
//ini_set('display_errors',1);
include_once('japanese.php');

function extractAuthor($str){
    preg_match_all('/\s(\S+?).[0-9]/u', $str, $matches);
    if(isset($matches[1][0]))
        return $matches[1][0];
    else
        return 'missing author';
}
function extractDate($str){
    preg_match_all('/(\d{2,4}\D\d{1,2}\D\d{1,2}\D?).$/u', $str, $matches);
    if(isset($matches[1][0]))
        return $matches[1][0];
    else
        return 'missing date';
}
function extractTitle($str){
    if(preg_match('/^'.toUTF8('カテゴリ').':.+/u', $str, $matches)==1){
        return $str;
    }else{
        preg_match_all('/^(.+)\s/u', $str, $matches);
        if(isset($matches[1][0]))
            return $matches[1][0];
        else
            return 'missing title';
    }
}
function extractCategory($str){
    preg_match_all('/\[\[Category:(.+?)\]\]/u', $str, $matches);
    if(isset($matches[1]))
        return $matches[1];
    else
        return ['missing category'];
}
function extractBody($str){
    $str = mb_convert_kana($str,'s');
    $str = preg_replace('/<\/?blockquote>/u', toUTF8('　'), $str);
    $str = preg_replace('/\r\n|\r|\n{1,}/u', toUTF8('　'), $str);
    $str = preg_replace('/\[\[.+?\]\]/u', '', $str);
    $str = preg_replace('/<<.+?>>/u', '', $str);
    return $str;
}
function extractImagelink($str){
    mb_regex_encoding('UTF-8');
    preg_match_all('/\[\[File:(.+?)\|/u', $str, $matches_en);
    preg_match_all('/\[\['.toUTF8('ファイル').':(.+?)\|/u', $str, $matches_ja);
    return array_merge($matches_ja[1],$matches_en[1]);
}
function cutTitle($str){
    $len = mb_strlen($str, 'UTF-8');
    $let = 0;
    for ($i = 0; $i < $len; $i++) {
        $c = mb_substr($str, $i, 1, 'UTF-8');
        if (preg_match('/[\x20-\x7E]/u', $c)==1) {
            $let_delta = 1/5*3;
        }else {
            $let_delta = 1;
        }
//        echo '['.$c.':'.$let_delta.']';
        $let += $let_delta;
        if($let >= 24){
//            echo $let.':'.$str.'<br>';
//            echo '<br><br>';
            return mb_substr($str, 0, $i, 'UTF-8').toUTF8('…');
        }
    }
//    echo '<br><br>';
//    echo $let.':'.$str.'<br>';
    return $str;
}
function outputTitle($pdf,$title){
    $len = mb_strlen($title, 'SJIS');
    for ($i = 0; $i < $len; $i++) {
        $c = mb_substr($title, $i, 1, 'SJIS');
        if (preg_match('/[\x20-\x7E]/u', $c)) {
            $w = 6.35/5*3;
        }else {
            $w = 6.35;
        }
        $pdf->Cell($w, 18, $c, 0, 0, 'c');
    }
    return 1;
}
function outputBody($pdf,$body){
    $len = mb_strlen($body, 'SJIS');
    $let = 0;
    for ($i = 0; $i < $len; $i++) {
        $c = mb_substr($body, $i, 1, 'SJIS');
        if (preg_match('/^[a-zA-Z0-9,.:\"\?\!]$/u', $c)) {
            $w = 3.8 / 10 * 7;
            $let_delta = 1.0 / 10 * 7;
        } else {
            $w = 3.8;
            $let_delta = 1.0;
        }
        if ($let >= 43 && ($c == '、' || $c == '。' || $c == '」' || $c == '”')) {
            $pdf->Cell($w, 6, $c, 0, 0, 'c');
            $let += $let_delta;
        } else if ($let >= 44) {
            $pdf->Ln();
            $pdf->Cell($w, 6, $c, 0, 0, 'c');
            $let = 0;
        } else if ($c == '　') {
            $pdf->Ln();
            $let = 0;
        } else {
            $pdf->Cell($w, 6, $c, 0, 0, 'c');
            $let += $let_delta;
        }
    }
}
function confirmPartialBody($value){
    if(preg_match('/^'.toUTF8('カテゴリ').':.+/u', $value->{'title'})==1){
        return true;
    }else {
        foreach ($value->{'category'} as $key => $cat) {
            if ($cat == toUTF8("書きかけ") || $cat == toUTF8("扉")) {
                return true;
                break;
            }
        }
    }
    return false;
}

function run(){

    $cate_mem_api = 'http://media.cs.inf.shizuoka.ac.jp/api.php?format=json&action=query&list=categorymembers&cmlimit=max&cmtitle=Category:';
    $query_category = $_GET['category'];
    $cate_mem_call = $cate_mem_api . $query_category;
    $cate_mem_json = file_get_contents($cate_mem_call);
    $cate_mem_array = json_decode($cate_mem_json);

    $page = new stdClass();

    foreach ($cate_mem_array->{'query'}->{'categorymembers'} as $key => $value) {
        $cate_mem_pageid = $value->{'pageid'};
        $page_api = 'http://media.cs.inf.shizuoka.ac.jp/api.php?format=json&action=query&prop=revisions&rvprop=content&pageids=';
        $page_json = file_get_contents($page_api . $cate_mem_pageid);
        $page_array = json_decode($page_json);
        //    dp($page_array);
        $page->{$key} = new stdClass();
        $page->{$key}->{'full_title'} = $page_array->{'query'}->{'pages'}->{$cate_mem_pageid}->{'title'};
        $page->{$key}->{'author'} = extractAuthor($page->{$key}->{'full_title'});
        $page->{$key}->{'date'} = extractDate($page->{$key}->{'full_title'});
        $page->{$key}->{'title'} = extractTitle($page->{$key}->{'full_title'});
        $raw_body = $page_array->{'query'}->{'pages'}->{$cate_mem_pageid}->{'revisions'}[0]->{'*'};
        $page->{$key}->{'category'} = extractCategory($raw_body);
        $page->{$key}->{'imagelink'} = extractImagelink($raw_body);
        $page->{$key}->{'body'} = extractBody($raw_body);
    }

    $pdf = new PDF_Japanese('P', 'mm', 'A4');
    $pdf->setSourceFile('template.pdf');
    $pdf->AddSJIShwFont();
    $pdf->SetMargins(19, 16, 16);
    $pdf->SetAutoPageBreak(false, 16);

    /*make a PDF*/
    $api = 0;
    foreach ($page as $value) {
        if (confirmPartialBody($value)) {
            continue;
        }
        /*make a page*/
        $pdf->AddPage();
        $tplidx = $pdf->ImportPage(1);
        $pdf->useTemplate($tplidx);
        /*title*/
        $title = toSJIS(cutTitle($value->{'title'}));
        $pdf->SetXY(16, 12);
        $pdf->SetFont('SJIS-hw', '', 18);
        $pdf->SetTextColor(0);
        outputTitle($pdf,$title);
        /*qrcode*/

        $full_title = $value->{'full_title'};
        $api_url = 'http://' . $api . '.chart.apis.google.com/chart?chs=240x240&cht=qr&chl=';
        if (++$api > 9){ $api = 0; }
        $target_url = 'http://media.cs.inf.shizuoka.ac.jp/index.php/' . urlencode($full_title);
        $call_url = $api_url . $target_url;
        $img_path = 'img/' . toSJIS($full_title) . '.png';
        if(is_readable($img_path)==FALSE){
            $qrdata = file_get_contents($call_url);
            file_put_contents($img_path, $qrdata);
        }else {
            //mock-up
        }
        /** @noinspection PhpUndefinedConstantInspection */
        $pdf->Image($img_path, 172, 17, 21, 21, PNG);

        /*body*/
        $pdf->SetXY(19, 42);
        $pdf->SetFont('SJIS-hw', '', 11);
        $body = toSJIS($value->{'body'});
        outputBody($pdf,$body);
        /*category*/
        $pdf->SetXY(16, 267);
        $pdf->SetFont('SJIS-hw', '', 22);
        $pdf->Write(15, toSJIS($query_category));
        /*author & date*/
        $author = toSJIS($value->{'author'});
        $date = toSJIS($value->{'date'});
        $author_date = 'Author:' . $author . '  Date:' . $date;
        $pdf->SetXY(12, 282);
        $pdf->SetFont('SJIS-hw', '', 16);
        $pdf->SetTextColor(255 - 16);
        $pdf->Write(15, $author_date);
    }
    /*output a PDF*/
    $pdf->Close();
    $pdf->Output('mediacard-' . toSJIS($query_category) . '.pdf', "I");
}

run();

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