<?php

// TODO: コマンドライン引数に変える
$file = 'Z__C_RJTD_20160920060000_MSM_GPV_Rjp_Lsurf_FH00-15_grib2.bin';

$fh = fopen($file, 'r');
if ($fh === false) {
    throw new Exception('file oepn error. ' . $file);
}

{ // 0節
    $r = fread($fh, 4);
    if ($r !== 'GRIB') {
        throw new Exception('this file is not GRIB file.' . $file);
    }
    fread($fh, 3);
    $r = readInt($fh, 1);
    if ($r !== 2) {
        throw new Exception('this file is not GRIB "2" file.' . $file);
    }
    $totalLength = readInt($fh, 8);
    $remainingLength = $totalLength - 16;
}

{ // 1節
    $secLength = readInt($fh, 4);
    if (readInt($fh, 1) !== 1) {
        throw new Exception('1st section is invalid.' . $file);
    }
    fread($fh, 7);

    // 世界標準時での時間
    $year = readInt($fh, 2);
    $month = readInt($fh, 1);
    $day = readInt($fh, 1);
    $hour = readInt($fh, 1);
    $min = readInt($fh, 1);
    $sec = readInt($fh, 1);

    fread($fh, $secLength - 19);
    $remainingLength = $remainingLength - $secLength;
}
{ // 3節(2節はないはずで合っても無視）
    $secLength = readInt($fh, 4);
    $secNum = readInt($fh, 1);
    if ($secNum === 2) { // 2節ならスキップ
        fread($fh, $secLength - 5);
        $remainingLength = $remainingLength - $secLength;
        $secLength = readInt($fh, 4);
        $secNum = readInt($fh, 1);
    }
    if ($secNum !== 3) {
        throw new Exception('3rd section is invalid.' . $file);
    }

    fread($fh, 1);
    $plotNum = readInt($fh, 4); // 格子点数
    fread($fh, 20);
    $latPlotNum = readInt($fh, 4); // 緯度方向の格子点数
    $lonPlotNum = readInt($fh, 4); // 経度方向の格子点数

    if ($latPlotNum * $lonPlotNum !== $plotNum) {
        throw new Exception("invalid plot num[lat:{$latPlotNum}],lon[{$lonPlotNum}],total[{$plotNum}]");
    }
    fread($fh, 8);
    $startLat = readInt($fh, 4) * 0.000001; // 開始緯度
    $startLon= readInt($fh, 4) * 0.000001; // 開始経度
    fread($fh, 1);
    $lastLat = readInt($fh, 4) * 0.000001; // 終了緯度
    $lastLon= readInt($fh, 4) * 0.000001; // 終了経度
    $diffLat = readInt($fh, 4) * 0.000001; // 緯度間隔
    $diffLon= readInt($fh, 4) * 0.000001; // 経度間隔

    if (($startLat + $diffLat * $latPlotNum) === $lastLat) {
        throw new Exception("invalid lat data");
    }
    if (($startLon + $diffLon * $lonPlotNum) === $lastLon) {
        throw new Exception("invalid lon data");
    }
    fread($fh, 1);
    $remainingLength = $remainingLength - $secLength;
}

// 4-6
while ($remainingLength > 4) {

}


/**
 * バイトを読み込む整数を返す
 * @param $fh
 * @param $length
 * @return number
 */
function readInt($fh, $length) {
    return hexdec(unpack('H*', fread($fh, $length))[1]);
}