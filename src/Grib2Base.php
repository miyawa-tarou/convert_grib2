<?php

namespace MiyawaTarou\ConverGrib2;

use MiyawaTarou\ConverGrib2\Libs\ReadBin;

class Grib2Base
{
    protected $file;
    protected $data;
    protected $fileTime;
    protected $remainLength;
    protected $grid; // ループで都度上書きされるが最新の値が入る
    protected $valueDefine; // ループで都度上書きされるが最新の値が入る
    protected $useFlag;

    public function __construct($file)
    {
        $this->file = $file;
        $this->data = [];
        $this->fileTime = [];
        $this->remainLength = 0;
    }

    /**
     * @throws \Exception
     */
    public function convert()
    {
        $fh = fopen($this->file, 'r');
        if ($fh === false) {
            throw new \Exception('file open error. '. $this->file);
        }
        
        $this->read0Sec($fh);
        $this->read1Sec($fh);

        $this->data = ['time' => $this->fileTime];

        $data = [];
        while ($this->remainLength > 4) {
            $ret = $this->read3Sec($fh); // 2nd Sec is skip(included 3rd Sec)
            $this->read4Sec($fh, $ret); // Sec3か4からループされること前提
            $this->read5Sec($fh);
            $this->read6Sec($fh);
            $d = $this->read7Sec($fh);

            if ($d === []) {
                continue;
            }

            // 複数種類残すならここの処理の変更が必要
            $data[$this->valueDefine['forecastNum']] = [
                'grid' => $this->grid,
                'type' => $this->valueDefine,
                'data' => $d,
            ];
        }

        $r = fread($fh, 4);
        if ($r !== '7777') {
            throw new \Exception('invalid end of file section. ' . $this->file);
        }

        $this->data['data'] = $data;
    }

    /**
     * ある緯度経度から最寄りの格子の値を取得する
     * @param $lat
     * @param $lon
     * @return array|bool
     */
    public function getNearValue($lat, $lon)
    {
        // 本来はdata側を1/100000すべきだが、可読性などのため引数を10^6倍
        $lat = $lat * 1000000;
        $lon = $lon * 1000000;

        $val = [];
        foreach ($this->data['data'] as $forecast => $data) {
            if ($data['grid']['lat']['start'] < $lat || $data['grid']['lat']['end'] > $lat) { // 高緯度から始まる前提
                return false;
            }
            if ($data['grid']['lon']['start'] > $lon || $data['grid']['lon']['end'] < $lon) {
                return false;
            }

            $y = round(($data['grid']['lat']['start'] - $lat) / $data['grid']['lat']['diff']);
            $x = round(($lon - $data['grid']['lon']['start']) / $data['grid']['lon']['diff']);
            $val[$forecast] = isset($data['data'][$x][$y]) ? $data['data'][$x][$y] : null; // nullだと欠測っぽいが意図的にskipしている時があるので注意
        }
        return $val;
    }

    /**
     * Read Section 0(Confirming GRIB2 File)
     *
     * @param $fh
     * @throws \Exception
     */
    protected function read0Sec(&$fh)
    {
        $r = fread($fh, 4);
        if ($r !== 'GRIB') {
            throw new \Exception('this file is not GRIB file. '. $this->file);
        }
        fread($fh, 3); // skip
        $r = ReadBin::readInt($fh, 1);
        if ($r !== 2) {
            throw new \Exception('this file is not GRIB file. '. $this->file);
        }

        $totalLength = ReadBin::readInt($fh, 8);
        $this->remainLength = $totalLength - 16;
    }

    /**
     * Read Section 1(Time Setting)
     * @param $fh
     * @throws \Exception
     */
    protected function read1Sec(&$fh)
    {
        $secLength = ReadBin::readInt($fh, 4);
        if (ReadBin::readInt($fh, 1) !== 1) {
            throw new \Exception('1st Section is invalid. '. $this->file);
        }
        fread($fh, 7); // skip
        $this->fileTime = [ // UTC
            'year' => ReadBin::readInt($fh, 2),
            'month' => ReadBin::readInt($fh, 1),
            'day' => ReadBin::readInt($fh, 1),
            'hour' => ReadBin::readInt($fh, 1),
            'min' => ReadBin::readInt($fh, 1),
            'sec' => ReadBin::readInt($fh, 1),
        ];
        
        fread($fh, $secLength - 19);
        $this->remainLength -= $secLength;
    }

    /**
     * Read Section 3(Grid Setting)
     * @param $fh
     * @return mixed false:normal int:skip 3rd sec(but some byte have read)
     * @throws \Exception
     */
    protected function read3Sec(&$fh)
    {
        $secLength = ReadBin::readInt($fh, 4);
        $secNum = ReadBin::readInt($fh, 1);
        if ($secNum === 2) { // if 2nd Sec, Skip
            fread($fh, $secLength - 5);
            $this->remainLength -= $secLength;
            $secLength = ReadBin::readInt($fh, 4);
            $secNum = ReadBin::readInt($fh, 1);
        }

        if ($secNum !== 3) {
            if ($secNum === 4) {
                return $secLength;
            }
            throw new \Exception('3rd section is invalid.' . $this->file);
        }

        fread($fh, 1);
        $plotNum = ReadBin::readInt($fh, 4); // 格子点数
        fread($fh, 20);
        $lonPlotNum = ReadBin::readInt($fh, 4); // 経度方向の格子点数
        $latPlotNum = ReadBin::readInt($fh, 4); // 緯度方向の格子点数

        if ($latPlotNum * $lonPlotNum !== $plotNum) {
            throw new \Exception("invalid plot num[lat:{$latPlotNum}],lon[{$lonPlotNum}],total[{$plotNum}]");
        }
        fread($fh, 8);
        // 本来は10^-6した値がただしい
        $startLat = ReadBin::readSingedInt($fh, 4); // 開始緯度
        $startLon= ReadBin::readSingedInt($fh, 4); // 開始経度
        fread($fh, 1);
        $lastLat = ReadBin::readSingedInt($fh, 4); // 終了緯度
        $lastLon= ReadBin::readSingedInt($fh, 4); // 終了経度

        $diffLon = ReadBin::readSingedInt($fh, 4); // 経度間隔
        $diffLat= ReadBin::readSingedInt($fh, 4); // 緯度間隔

        if ($diffLat % 1000 === 333) {
            $diffLat = $diffLat + 0.333; // HACK:
        }
        if ($diffLon % 1000 === 333) {
            $diffLon = $diffLon + 0.333; // HACK:
        }

        if (($startLat - $diffLat * ($latPlotNum - 1)) != $lastLat) {
            throw new \Exception("invalid lat data");
        }
        if (($startLon + $diffLon * ($lonPlotNum - 1)) !== $lastLon) {
            throw new \Exception("invalid lon data");
        }

        $this->grid = [
            'num' => $plotNum,
            'lat' => ['num' => $latPlotNum, 'start' => $startLat, 'diff' => $diffLat, 'end' => $lastLat],
            'lon' => ['num' => $lonPlotNum, 'start' => $startLon, 'diff' => $diffLon, 'end' => $lastLon],
        ];

        fread($fh, 1);
        $this->remainLength -= $secLength;
        return false;
    }

    /**
     * @param $fh
     * @param $flag
     * @throws \Exception
     */
    protected function read4Sec(&$fh, $flag)
    {
        if ($flag) {
            $secLength = $flag;
        } else {
            $secLength = ReadBin::readInt($fh, 4);
            if (ReadBin::readInt($fh, 1) !== 4) {
                throw new \Exception('4th Section is invalid. '. $this->file);
            }
        }
        fread($fh, 4);

        $paramCategory = ReadBin::readInt($fh, 1);
        $paramNum = ReadBin::readInt($fh, 1);
        // 下二つは上の指定で満たせているので現時点では使っていない
        $typeData = ReadBin::readInt($fh, 1); // 0:解析,1:初期値,2:予報？
        $type = ReadBin::readInt($fh, 1); // 31:メソ,201:合成解析レーダー,153:竜巻発生確度,154:雷活動度

        fread($fh, 5);
        $forecastNum = ReadBin::readInt($fh, 4); // 予報時間

        $typeField = ReadBin::readInt($fh, 1); // 固定面の種類（1:地上,100:気圧面,103:地上からの特定高度）
        $typeFieldFactor = ReadBin::readInt($fh, 1); // 固定面の尺度因子
        $typeFieldNum = ReadBin::readInt($fh, 4); // 固定面の尺度付きの値（気圧面なら気圧が入る）

        // この辺でデータ使うか使わないか判定（PHPなので全データを入れるとだいぶしんどいので限るべき）
        $this->useFlag = static::isUseData($paramCategory, $paramNum);

        fread($fh, 6);
        if ($secLength > 34) {
            fread($fh, $secLength - 34);
        }

        $this->valueDefine = [
            'paramCategory' => $paramCategory,
            'paramNum'      => $paramNum,
            'typeData'      => $typeData,
            'type'          => $type,
            'forecastNum'  => $forecastNum,
            'typeField'     => $typeField,
            'typeFieldFactor' => $typeFieldFactor,
            'typeFieldNum'  => $typeFieldNum,
        ];

        $this->remainLength -= $secLength;
    }

    /**
     * データの値の入り方の定義
     * @param $fh
     * @throws \Exception
     */
    protected function read5Sec(&$fh)
    {
        $secLength = ReadBin::readInt($fh, 4);
        if (ReadBin::readInt($fh, 1) !== 5) {
            throw new \Exception('5th Section is invalid. '. $this->file);
        }
        fread($fh, 4);

        $calc = [];
        $templateNum = ReadBin::readInt($fh, 2);
        if ($templateNum === 0) {
            $calc['numR'] = ReadBin::readFloat($fh);
            $calc['numE'] = ReadBin::readSingedInt($fh, 2);
            $calc['numD'] = ReadBin::readSingedInt($fh, 2);
            $calc['bitNum'] = ReadBin::readInt($fh, 1);
            $calc['exp'] = pow(2, $calc['numE']);
            $calc['base'] = pow(10, $calc['numD']);
            fread($fh, $secLength - 20);
        } elseif ($templateNum === 200) { // run length compression
            $calc['bitNum'] = ReadBin::readInt($fh, 1);
            $calc['maxV'] = ReadBin::readInt($fh, 2);
            $calc['lngu'] = pow(2, $calc['bitNum']) - 1 - $calc['maxV'];
            fread($fh, $secLength - 14);
        } else {
            throw new \Exception('Unknown Template Type. num:' . $templateNum);
        }
        $calc['template'] = $templateNum;
        $this->valueDefine['calc'] = $calc;

        $this->remainLength -= $secLength;
    }

    /**
     * データの配置
     * @param $fh
     * @throws \Exception
     */
    protected function read6Sec(&$fh)
    {
        $secLength = ReadBin::readInt($fh, 4);
        if (ReadBin::readInt($fh, 1) !== 6) {
            throw new \Exception('6th Section is invalid. '. $this->file);
        }

        $bitMapping = [];
        $bitmapMode = ReadBin::readInt($fh, 1);
        if ($bitmapMode === 0) {
            $loop = $secLength - 6;
            if ($loop * 8 !== $this->grid['num']) {
                throw new \Exception('');
            }

            $x = 0;
            $y = 0;
            $bitMapping = [];
            $readBin = new ReadBin();
            for ($i = 0; $i < $loop * 8; $i++) {
                $int = $readBin->readIntBit($fh, 1);
                if ($int !== 0) { // use data
                    $bitMapping[] = [$x, $y];
                }
                $x++;
                if ($x >= $this->grid['lon']['num']) {
                    $x = 0;
                    $y++;
                }
            }
        } else {
            // 254は前のを使う、255はbitmapモードを使わない
            if ($secLength > 6) {
                fread($fh, $secLength - 6);
            }
        }

        $bitmap = [
            'mode' => $bitmapMode,
            'map' => $bitMapping,
        ];
        $this->valueDefine['bitmap'] = $bitmap;

        $this->remainLength -= $secLength;
    }

    /**
     * @param $fh
     * @return array
     * @throws \Exception
     */
    protected function read7Sec(&$fh)
    {
        $secLength = ReadBin::readInt($fh, 4);
        if (ReadBin::readInt($fh, 1) !== 7) {
            throw new \Exception('7th Section is invalid. '. $this->file);
        }

        // データ使わないならスキップ
        if (!$this->useFlag) {
            fread($fh, $secLength - 5);
            $this->remainLength -= $secLength;
            return [];
        }
        $binLength = $secLength - 5;

        $readBin = new ReadBin();
        $readBin->init();
        $x = 0;
        $y = 0;
        $readSecByte = 0;
        $readBit = 0;
        $data = [];
        for ($i = 0; $i < $this->grid['num']; $i++) {
            if (isset($nextVal)) {
                $int = $nextVal;
                unset($nextVal);
            } else {
                // バイト数的に最後まで読んでいたら終了（ランレングス圧縮のときのみ）
                if ($readSecByte >= $binLength) {
                    break;
                }
                // バイト数的に最後まで読んでいたら終了
                $readBit += $this->valueDefine['calc']['bitNum'];
                if ($readBit / 8 >= $binLength) {
                    break;
                }

                $int = $readBin->readIntBit($fh, $this->valueDefine['calc']['bitNum']);
                $readSecByte++;
            }

            if ($this->valueDefine['calc']['template'] === 0) {
                $val = ($this->valueDefine['calc']['numR'] + $int * $this->valueDefine['calc']['exp']) / $this->valueDefine['calc']['base'];

                if ($this->valueDefine['bitmap']['mode'] === 254 || $this->valueDefine['bitmap']['mode'] === 0) {
                    if (static::isSkipValue($val)) {
                        continue;
                    }
                    $data[$this->valueDefine['bitmap']['map'][$i][0]][$this->valueDefine['bitmap']['map'][$i][1]] = $val;
                } else {
                    if ($x >= $this->grid['lon']['num']) {
                        $x = 0;
                        $y++;
                    }
                    if (static::isSkipValue($val)) {
                        $x++;
                        continue;
                    }
                    $data[$x][$y] = $val;
                }
                $x++;
            } elseif ($this->valueDefine['calc']['template'] === 200) {
                $val = $int;
                $loopArray = [];
                while (true) {
                    if ($readSecByte >= $binLength) {
                        break 2;
                    }

                    $int = $readBin->readIntBit($fh, $this->valueDefine['calc']['bitNum']);
                    $readSecByte++;
                    // maxVを超える数はループ数。越えなければ値になるので次のループで使う
                    if ($int <= $this->valueDefine['calc']['maxV']) {
                        $nextVal = $int;
                        break;
                    }
                    $loopArray[] = $int;
                }
                $loop = 1;
                foreach ($loopArray as $k => $d) {
                    $loop += pow($this->valueDefine['calc']['lngu'], $k) * ($d - ($this->valueDefine['calc']['maxV'] + 1));
                }
                $i += $loop;

                for ($j = 0; $j < $loop; $j++) {
                    if (!static::isSkipValue($val)) {
                        $data[$x][$y] = $val;
                    }
                    $x++;
                    if ($x >= $this->grid['lon']['num']) {
                        $x = 0;
                        $y++;
                    }
                }
            }
        }

        $this->remainLength -= $secLength;
        return $data;
    }

    /**
     *
     *
     * この辺りは現段階では地上データのみで検証
     * 気圧面の場合はFieldの値も使うべき
     * isSkipValue()からもわかるように１つの面での１つの変数を前提としてる
     *
     * @param $paramCategory
     * @param $paramNum
     * @return bool
     */
    protected static function isUseData($paramCategory, $paramNum)
    {
        return $paramCategory === 3 && $paramNum === 1 ? true : false;
    }

    /**
     *
     * この辺りは現段階では１つのみにしている
     * 正直PHPなんかで動かす時点で、一つずつ実行したほうがまだましという思考で（クラスが増えるのがいけてないので、関数を代入とかでもいいかも）
     *
     * @param $val
     * @return bool
     */
    protected static function isSkipValue($val)
    {
        if ($val === 0) {
            return true;
        }
        return false;
    }
}
