<?php

/**
 * 1kmメッシュ合成レーダー:http://www.data.jma.go.jp/add/suishin/jyouhou/pdf/162.pdf
 * MSM:http://www.data.jma.go.jp/add/suishin/jyouhou/pdf/205.pdf
 */

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
    $r = readBin::readInt($fh, 1);
    if ($r !== 2) {
        throw new Exception('this file is not GRIB "2" file.' . $file);
    }
    $totalLength = readBin::readInt($fh, 8);
    $remainingLength = $totalLength - 16;
}

{ // 1節
    $secLength = readBin::readInt($fh, 4);
    if (readBin::readInt($fh, 1) !== 1) {
        throw new Exception('1st section is invalid.' . $file);
    }
    fread($fh, 7);

    // 世界標準時での時間
    $year = readBin::readInt($fh, 2);
    $month = readBin::readInt($fh, 1);
    $day = readBin::readInt($fh, 1);
    $hour = readBin::readInt($fh, 1);
    $min = readBin::readInt($fh, 1);
    $sec = readBin::readInt($fh, 1);

    fread($fh, $secLength - 19);
    $remainingLength = $remainingLength - $secLength;
}
{ // 3節(2節はないはずで合っても無視）
    $secLength = readBin::readInt($fh, 4);
    $secNum = readBin::readInt($fh, 1);
    if ($secNum === 2) { // 2節ならスキップ
        fread($fh, $secLength - 5);
        $remainingLength = $remainingLength - $secLength;
        $secLength = readBin::readInt($fh, 4);
        $secNum = readBin::readInt($fh, 1);
    }
    if ($secNum !== 3) {
        throw new Exception('3rd section is invalid.' . $file);
    }

    fread($fh, 1);
    $plotNum = readBin::readInt($fh, 4); // 格子点数
    fread($fh, 20);
    $lonPlotNum = readBin::readInt($fh, 4); // 緯度方向の格子点数
    $latPlotNum = readBin::readInt($fh, 4); // 経度方向の格子点数

    if ($latPlotNum * $lonPlotNum !== $plotNum) {
        throw new Exception("invalid plot num[lat:{$latPlotNum}],lon[{$lonPlotNum}],total[{$plotNum}]");
    }
    fread($fh, 8);
    // 本来は10^-6した値がただしい
    $startLat = readBin::readInt($fh, 4); // 開始緯度
    $startLon= readBin::readInt($fh, 4); // 開始経度
    fread($fh, 1);
    $lastLat = readBin::readInt($fh, 4); // 終了緯度
    $lastLon= readBin::readInt($fh, 4); // 終了経度
    $diffLon = readBin::readInt($fh, 4); // 緯度間隔
    $diffLat= readBin::readInt($fh, 4); // 経度間隔

    if (($startLat - $diffLat * ($latPlotNum - 1)) != $lastLat) {
        throw new Exception("invalid lat data");
    }
    if (($startLon + $diffLon * ($lonPlotNum - 1)) !== $lastLon) {
        throw new Exception("invalid lon data");
    }
    fread($fh, 1);
    $remainingLength = $remainingLength - $secLength;
}

// 4-7（ここは周回する）
while ($remainingLength > 4) {
    { // 4節
        $secLength = readBin::readInt($fh, 4);
        if (readBin::readInt($fh, 1) !== 4) {
            throw new Exception('4th section is invalid.' . $file);
        }
        fread($fh, 4);

        // ここで必要なものだけを取得するようにする
        $paramCategory = readBin::readInt($fh, 1); // パラメータカテゴリー
        $paramNum = readBin::readInt($fh, 1); // パラメータ番号
        $typeData = readBin::readInt($fh, 1); // 0:解析,1:初期値,2:予報？
        $type = readBin::readInt($fh, 1); // 31:メソ,201:合成解析レーダー

        $useFlag = false;
        // この場合は降水量
        if ($paramCategory === 1 && $paramNum ===8) {
            $useFlag = true;
        }

        fread($fh, 5);

        $forecastNum = readBin::readInt($fh, 4); // 予報時間
        var_dump($forecastNum);
        $typeField = readBin::readInt($fh, 1); // 固定面の種類（1:地上,100:気圧面,103:地上からの特定高度）
        $typeFieldHoge = readBin::readInt($fh, 1); // 固定面の尺度因子
        $typeFieldNum = readBin::readInt($fh, 4); // 固定面の尺度付きの値（気圧面なら気圧）

        fread($fh, 6);

        if ($secLength > 34) {
            fread($fh, $secLength - 34); // とりあえず意味が分からないのでスキップ
        }
        $remainingLength = $remainingLength - $secLength;
    }
    { // 5節
        $secLength = readBin::readInt($fh, 4);
        if (readBin::readInt($fh, 1) !== 5) {
            throw new Exception('5th section is invalid.' . $file);
        }
        fread($fh, 4); // 資料点数
        $templateNum = readBin::readInt($fh, 2); // テンプレート番号
        if ($templateNum === 0) {
            //$numR = hexdec(unpack('H*', fread($fh, 4))[1]);


            $numR = readBin::readFloat($fh);
            $numE = readBin::readSingedInt($fh, 2);
            $numD = readBin::readSingedInt($fh, 2);
            $bitNum = readBin::readInt($fh, 1);
            fread($fh, $secLength - 20);
        } elseif ($templateNum === 200) {
            $bitNum = readBin::readInt($fh, 1);
            fread($fh, $secLength - 12);
        } else {
            throw new Exception('Unknown template number.' . $templateNum);
        }
        $remainingLength = $remainingLength - $secLength;
    }
    { // 6節
        $secLength = readBin::readInt($fh, 4);
        if (readBin::readInt($fh, 1) !== 6) {
            throw new Exception('6th section is invalid.' . $file);
        }
        // スキップ
        fread($fh, $secLength - 5);
        $remainingLength = $remainingLength - $secLength;
    }
    { // 7節
        $secLength = readBin::readInt($fh, 4);
        if (readBin::readInt($fh, 1) !== 7) {
            throw new Exception('7th section is invalid.' . $file);
        }

        if (!$useFlag) {
            fread($fh, $secLength - 5);
            $remainingLength = $remainingLength - $secLength;
            continue;
        }

        $exp = pow(2, $numE);
        $base = pow(10, $numD);
        $readBin = new readBin();
        $readBin->init();
        for ($i = 0; $i < $plotNum; $i++) {
            $int = $readBin->readIntBit($fh, $bitNum);
            if ($int === 0) {
                var_dump($int);
            } else {
                $num = ($numR + $int * $exp) / $base;
                var_dump($num);
            }
        }
        $remainingLength = $remainingLength - $secLength;
        var_dump($remainingLength);
    }
}

$r = fread($fh, 4);
if ($r !== '7777') {
    throw new Exception('invalid end of file section.' . $file);
}



class readBin
{
    /**
     * バイトを読み込む整数を返す
     * @param $fh
     * @param $length
     * @return number
     */
    public static function readInt(&$fh, $length) {
        return hexdec(unpack('H*', fread($fh, $length))[1]);
    }

    /**
     * 符号付整数
     * @param $fh
     * @param $length
     * @return number
     */
    public static function readSingedInt(&$fh, $length) {
        $r = fread($fh, $length);
        $bit = '';
        foreach (unpack("C*", $r) as $a) {
            $bit .= sprintf("%08b", $a);
        }

        $sign = substr($bit, 0, 1);
        $bit = substr($bit, 1);
        if ($sign === '1') {
            return - bindec($bit);
        } else {
            return bindec($bit);
        }
    }

    /**
     * 浮動小数点（あってるのかわからん
     * @param $fh
     * @return int
     */
    public static function readFloat(&$fh) {
        $data = fread($fh, 4);
        $v = hexdec(bin2hex($data));
        $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
        $exp = ($v >> 23 & 0xFF) - 127;
        return $x * pow(2, $exp - 23);
    }

    private $data = [];
    public function init()
    {
        $this->data = [];
    }
    public function readIntBit(&$fh, $length) {
        $res = [];
        for ($i = 0; $i < $length; $i++) {
            if (count($this->data) === 0) {
                $r = fread($fh, 1); // 1bitずつ読めないので1バイトずつよむ
                $bit = sprintf("%08b", unpack("C*", $r)[1]);
                $this->data = array_merge($this->data, str_split($bit));
            }
            $res[] = array_shift($this->data);
        }
        $bin = implode($res);
        return bindec($bin);
    }
}
