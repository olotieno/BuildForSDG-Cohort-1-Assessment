<?php
//header("Content-Type: text/xml;charset=utf-8");
header("Content-Type: application/xml");
include "estimator.php";


echo hhb_xml_encode(covid19ImpactEstimator($decoded));


function hhb_xml_encode(array $arr, string $name_for_numeric_keys = 'val'): string {

    if (empty ( $arr )) {
        return '';
    }
    $is_iterable_compat = function ($v): bool {
        return is_array ( $v ) || ($v instanceof \Traversable);
    };
    $isAssoc = function (array $arr): bool {
        if (array () === $arr)
            return false;
        return array_keys ( $arr ) !== range ( 0, count ( $arr ) - 1 );
    };

    $iterator = $arr;
    $domd = new DOMDocument("1.0");
    $root = $domd->createElement ( 'root' );
    foreach ( $iterator as $key => $val ) {

        $ele = $domd->createElement ( is_int ( $key ) ? $name_for_numeric_keys : $key );
        if (! empty ( $val ) || $val === '0') {
            if ($is_iterable_compat ( $val )) {
                $asoc = $isAssoc ( $val );
                $tmp = hhb_xml_encode ( $val, is_int ( $key ) ? $name_for_numeric_keys : $key );

                $tmp = @DOMDocument::loadXML (  $tmp  );
                foreach ( $tmp->getElementsByTagName ( "root" )->item ( 0 )->childNodes ?? [ ] as $tmp2 ) {
                    $tmp3 = $domd->importNode ( $tmp2, true );
                    if ($asoc) {
                        $ele->appendChild ( $tmp3 );
                    } else {
                        $root->appendChild ( $tmp3 );
                    }
                }

                unset ( $tmp, $tmp2, $tmp3 );
                if (! $asoc) {

                    continue;
                }
            } else {
                $ele->textContent = $val;
            }
        }
        $root->appendChild ( $ele );
    }
    $domd->preserveWhiteSpace = false;
    $domd->formatOutput = true;

    $ret = $domd->saveXML ( $root );
    return '<?xml version="1.0"?>' . $ret ;
}


$time2 = microtime(true);

$exe_time = "0".(int)($time2- $_SERVER["REQUEST_TIME_FLOAT"])* 1000;
$logMessage = $_SERVER['REQUEST_METHOD']. "\t\t".$_SERVER['REQUEST_URI']. "\t\t".http_response_code()."\t\t". $exe_time."ms";
file_put_contents('logs.txt', $logMessage."\n", FILE_APPEND | LOCK_EX);


