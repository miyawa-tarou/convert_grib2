<?php
require_once __DIR__ . '/../vendor/autoload.php';

//$file = __DIR__ . '/Z__C_RJTD_20180326000000_GWM_GPV_Rgl_Gll0p5deg_FD0000-0312_grib2.bin';
$file = __DIR__ . '/Z__C_RJTD_20180326000000_GSM_GPV_Rjp_Lsurf_FD0000-0312_grib2.bin';


try {
    $grib2 = new \MiyawaTarou\ConverGrib2\Grib2\GsmPrecipitation($file);
    $grib2->convert();
    $v = $grib2->getNearValue('35.658581', '139.745433');
    var_dump($v);
} catch (Exception $e) {
    var_dump($e->getMessage());
    var_dump($e->getLine());
    var_dump($e->getTrace());
}
