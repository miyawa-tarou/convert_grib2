<?php

namespace MiyawaTarou\ConverGrib2\Libs;

/**
 * Class ReadBin
 * @package MiyawaTarou\ConverGrib2\Libs
 */
class ReadBin
{
    private $data = [];

    public function init()
    {
        $this->data = [];
    }

    /**
     * bit単位でデータを読む
     * 新しいファイルを読む際にはinit()で初期化すること
     *
     * @param $fh
     * @param $length
     * @return float|int
     */
    public function readIntBit(&$fh, $length)
    {
        $res = [];
        for ($i = 0; $i < $length; $i++) {
            if (count($this->data) === 0) {
                $r = fread($fh, 1);
                $bit = sprintf("%08b", unpack("C*", $r)[1]);
                $this->data = array_merge($this->data, str_split($bit));
            }
            $res[] = array_shift($this->data);
        }
        $bin = implode($res);
        return bindec($bin);
    }

    /**
     * バイトを読み込む整数を返す
     * @param $fh
     * @param $length
     * @return number
     */
    public static function readInt(&$fh, $length)
    {
        return hexdec(unpack('H*', fread($fh, $length))[1]);
    }

    /**
     * 符号付整数
     * @param $fh
     * @param $length
     * @return number
     */
    public static function readSingedInt(&$fh, $length)
    {
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
    public static function readFloat(&$fh)
    {
        $data = fread($fh, 4);
        $v = hexdec(bin2hex($data));
        $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
        $exp = ($v >> 23 & 0xFF) - 127;
        return $x * pow(2, $exp - 23);
    }
}
