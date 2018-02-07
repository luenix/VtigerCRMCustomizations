<?php

if(!function_exists("wf_ipmt")) {
	function wf_ppmt($rate, $payno, $nper, $pv) {
	$f = new Financial;
	$rate = $rate/1200;
	$payno = $payno;
	$nper = $nper;
	$pv = $pv;
	$amount = $f->PPMT($rate, $payno, $nper, $pv);
	return $amount;
    }

}

?>