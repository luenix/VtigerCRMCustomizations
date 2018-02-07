<?php

include('../functions/ppmt.inc.php');

/*
 * Values simulating Amortization record 57735
 * Link: http://srm.discoverbelize.com/index.php?module=Amortization&view=Detail&record=57735
 */
$amort_ID = 57735;
$term = 240;
$APR = 5.99;
$loan = 127395;
$GST = 12.5;
$monthly_billing_day = 30;
$first_payment_date = '2016-09-30';
$debug_output = false;

$results = wf_create_table_amortfull_and_textfile($amort_ID, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $debug_output);
echo 'Results[0]: ' . $results[0] . PHP_EOL;
echo 'Results[1]: ' . $results[1] . PHP_EOL;
echo 'Results[2]: ' . $results[2] . PHP_EOL;
echo 'Results[3]: ' . $results[3] . PHP_EOL;

