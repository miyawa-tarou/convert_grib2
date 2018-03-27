<?php

namespace MiyawaTarou\ConverGrib2\Grib2;

use MiyawaTarou\ConverGrib2\Grib2Base;

/**
 * GSM/MSMの降水量を取得する
 * http://www.data.jma.go.jp/add/suishin/jyouhou/pdf/316.pdf
 * GSMは積算/MSMは単時間
 *
 * Class GsmPrecipitation
 * @package MiyawaTarou\ConverGrib2\Grib2
 */
class GsmPrecipitation extends Grib2Base
{

    /**
     * @param $paramCategory
     * @param $paramNum
     * @return bool
     */
    protected static function isUseData($paramCategory, $paramNum)
    {
        return $paramCategory === 1 && $paramNum === 8 ? true : false;
    }
}
