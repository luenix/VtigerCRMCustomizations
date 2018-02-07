<?php

if(!function_exists("wf_pmt")) {
    function wf_pmt($apr, $term, $loan) {
        if($apr != 1) {
             $apr = $apr / 1200;
             $amount = $apr * -$loan * pow((1 + $apr), $term) / (1 - pow((1 + $apr), $term));
        } else {
            $amount = 999;
        }
 
        return number_format($amount, 2); 
	} 
}
