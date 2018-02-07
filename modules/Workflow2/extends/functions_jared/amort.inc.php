<?php

require_once '../functions/ppmt.inc.php';

if (!function_exists("wf_create_table_amortfull")) {
    /**
     * Creates the amortization schedule for a given $amort_ID.
     *
     * 1.  For every month in $term, calculates and sets 8 data points.
     * 2.  Drops table `vtiger_amort_$amort_ID` if it exists.
     * 3.  Creates table `vtiger_amort_$amort_ID` to spec.
     * 4.  Iterates through the array $months and pushes individual MySQL INSERT queries.
     *
     * @param int    $amort_ID            The record ID to pull financial data from.
     * @param int    $term                The term in number of months.
     * @param float  $APR                 The Annual Percentage Rate.
     * @param float  $loan                The initial loan.
     * @param float  $GST                 The Belizean General Sales Tax, currently 12.5% on 2016-05-27.
     * @param int    $monthly_billing_day The monthly payment due date.  Eventually overrides $first_payment_date effect.
     * @param string $first_payment_date  The initial payment date.  Day of $first_payment_date currently overrides $monthly_billing_day.
     * @param bool   $debug_output        The toggle for debug output to console.
     *
     * @return array $results             The array returned upon completion of this function.
     *                                    $results[0] is either 'Succesful' or 'Failed'.
     *                                    $results[1] is the associative array $months with calculated amortization schedule data grouped by nth month of the term.
     */
    function wf_create_table_amortfull(
        $amort_ID,
        $term,
        $APR,
        $loan,
        $GST,
        $monthly_billing_day,
        $first_payment_date,
        $debug_output = false
    ) {
        /**
         * @var float    $time_spent Float that holds the total execution time in seconds.
         * @var array    $months     Array that holds amortization schedule data by 1st, 2nd, 3rd, ..., nth month.
         * @var DateTime $lastDate   DateTime for later use.
         * @var string   $results    [0] String that defaults to 'Failed'.  Changes to 'Succeeded' upon completion.
         */
        $time_spent = microtime(true);
        $months = array();
        $lastDate = null;
        $results[0] = 'Failed';

        // Call date_default_timezone_set() until datetime.timezone bug is addressed.
        date_default_timezone_set('America/Los_Angeles');

        // Adjust $monthly_billing_day to day of $first_payment_date.
        $monthly_billing_day = substr($first_payment_date, -2);

        // Set $first_payment_date to datetime for later use.
        $first_payment_date = strtotime($first_payment_date);

        // set db and table information
        $hostname = 'localhost';
        $username = 'srmadmin';
        $password = 'Fuck0ffPhunk!';
        $dbname = 'srm';

        // Set table name to `vtiger_amortfull_$amort_ID`.
        $amort_tb = 'vtiger_amortfull_' . $amort_ID;

        echo ($debug_output) ? 'Creating table `' . $amort_tb . '`.' . PHP_EOL : null;

        try {
            // Connect to MySQL DB `SRM`
            $DBH = new PDO('mysql:host=' . $hostname . ';dbname=' . $dbname . ';charset=utf8', $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // DROP TABLE IF EXISTS `vtiger_amortfull_$amort_ID`
            $stmt = $DBH->prepare('DROP TABLE IF EXISTS `' . $amort_tb . '`');
            $DBH->beginTransaction();
            $stmt->execute();
            $DBH->commit();

            // CREATE TABLE `vtiger_amortfull_$amort_ID`
            $stmt = $DBH->prepare(
                'CREATE TABLE `' . $amort_tb . '` (
                    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    payment_no INT(5) DEFAULT NULL,
                    payment_due_date DATE DEFAULT NULL,
                    payment_due DECIMAL(10,2) DEFAULT NULL,
                    payment_principle DECIMAL(10,2) DEFAULT NULL,
                    payment_interest DECIMAL(10,2) DEFAULT NULL,
                    payment_gst DECIMAL(10,2) DEFAULT NULL,
                    new_balance DECIMAL(10,2) DEFAULT NULL,
                    payment_total DECIMAL(10,2) DEFAULT NULL
                )
                ENGINE=InnoDB
                AUTO_INCREMENT=1
                DEFAULT CHARSET=utf8'
            );
            $DBH->beginTransaction();
            $stmt->execute();
            $DBH->commit();

            // Prepare INSERT statement $stmt
            $stmt = $DBH->prepare(
                'INSERT INTO `' . $amort_tb . '` (payment_no, payment_due_date, payment_due, payment_principle, payment_interest, payment_gst, new_balance, payment_total)
                VALUES (:payment_no, :payment_due_date, :payment_due, :payment_principle, :payment_interest, :payment_gst, :new_balance, :payment_total)'
            );
            $DBH->beginTransaction();

            // Generate full amortization schedule as $months[$ii] where $ii == nth month and count($months) == $term.
            // Execute $stmt for $months[$ii] at the end of each iteration.
            for ($ii = 1; $ii <= $term; $ii++) {
                // payment_no
                $months[$ii]['payment_no'] = $ii;

                // payment_due_date
                $lastDate = strtotime('last day of +' . ($ii - 1) . 'month', $first_payment_date);
                if (date('d', $lastDate) > $monthly_billing_day) {
                    $months[$ii]['payment_due_date'] = substr(date('Y-m-d', $lastDate), 0, 8) . $monthly_billing_day;
                } else {
                    $months[$ii]['payment_due_date'] = date('Y-m-d', $lastDate);
                }

                // payment_due
                $months[$ii]['payment_due'] = wf_pmt2($APR, $term, $loan);

                // payment_principle
                $months[$ii]['payment_principle'] = wf_ppmt($APR, $ii, $term, -$loan);

                // payment_interest
                $months[$ii]['payment_interest'] = wf_ipmt($APR, $ii, $term, -$loan);

                // payment_gst
                $months[$ii]['payment_gst'] = $months[$ii]['payment_principle'] * ($GST / 100);

                // new_balance
                if ($ii !== 1) {
                    $months[$ii]['new_balance'] = $months[$ii - 1]['new_balance'] - $months[$ii]['payment_principle'];
                } else {
                    $months[$ii]['new_balance'] = $loan - $months[$ii]['payment_principle'];
                }

                // payment_total
                $months[$ii]['payment_total'] = $months[$ii]['payment_interest'] + $months[$ii]['payment_principle'] + $months[$ii]['payment_gst'];

                // Execute $stmt for $months[$ii].
                $stmt->execute(array(
                    ':payment_no'        => $months[$ii]['payment_no'],
                    ':payment_due_date'  => $months[$ii]['payment_due_date'],
                    ':payment_due'       => number_format($months[$ii]['payment_due'], 2, '.', ''),
                    ':payment_principle' => number_format($months[$ii]['payment_principle'], 2, '.', ''),
                    ':payment_interest'  => number_format($months[$ii]['payment_interest'], 2, '.', ''),
                    ':payment_gst'       => number_format($months[$ii]['payment_gst'], 2, '.', ''),
                    ':new_balance'       => number_format($months[$ii]['new_balance'], 2, '.', ''),
                    ':payment_total'     => number_format($months[$ii]['payment_total'], 2, '.', '')
                ));
            } // END for ($ii = 1; $ii <= $term; $ii++)

            $DBH->commit();
            $DBH = null;
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        $results[0] = "Succeeded";
        $results[1] = $amort_ID;
        $results[2] = $months;
        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $results;
    }
}

if (!function_exists("wf_insert_amortfull_to_table_block_data")) {
    /**
     * Updates the MySQL table `table_block_data` with data calculated by wf_create_table_amortfull().
     *
     * @param array &$amortfull   The array returned by wf_create_table_amortfull():
     *                            &$amortfull[0] set to either 'Failed' or 'Succeeded',
     *                            &$amortfull[1] set to $amort_ID.
     *                            &$amortfull[2] set to array $months with 8 associative elements.
     * @param bool  $debug_output The toggle for debug output to console.
     *
     * @return string $result   String that defaults to 'Failed'.  Changes to 'Succeeded' upon completion.
     */
    function wf_insert_amortfull_to_table_block_data(
        array &$amortfull = null,
        $debug_output = false
    ) {
        /**
         * @var float  $time_spent Float that holds the total execution time in seconds.
         * @var string $result     String that defaults to 'Failed'.  Changes to 'Succeeded' upon completion.
         */
        $time_spent = microtime(true);

        if ($amortfull === null) {
            $result = 'Failed.  Expected $amortfull array.' . PHP_EOL;
        } elseif (!isset($amortfull[0]) || strcmp($amortfull[0], 'Succeeded') !== 0) {
            $result = 'Failed.  Expected $amortfull[0] to be (string) "Succeeded".' . PHP_EOL;
        } elseif (!isset($amortfull[1]) || strcmp(gettype($amortfull[1]), 'integer') !== 0) {
            $result = 'Failed.  Expected $amortfull[1] to be (int) $amort_ID.' . PHP_EOL;
        } elseif (count($amortfull[2][1]) !== 8 || strcmp(gettype($amortfull[2]), 'array') !== 0) {
            $result = 'Failed.  Expected $amortfull[2] to be (array) $months with 8 elements.' . PHP_EOL;
        } else {
            $result = 'Succeeded';
        }

        if (strcmp($result, 'Succeeded') !== 0) {
            return $result;
        }

        $amort_ID = $amortfull[1];
        $months = $amortfull[2];
        $table = 'table_block_data';

        // set db and table information
        $hostname = 'localhost';
        $username = 'srmadmin';
        $password = 'Fuck0ffPhunk!';
        $dbname = 'srm';

        echo ($debug_output) ? 'Inserting into table `' . $table . '`.' . PHP_EOL : null;

        try {
            // Connect to MySQL DB `SRM`
            $DBH = new PDO('mysql:host=' . $hostname . ';dbname=' . $dbname . ';charset=utf8', $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare INSERT statement $stmt
            $stmt = $DBH->prepare(
                'INSERT INTO `' . $table . '` (blockid, crmid, fieldname, value, row)
                VALUES (6, :crmid, :fieldname, :value, :row)'
            );
            $DBH->beginTransaction();

            // Generate full amortization schedule as $months[$ii] where $ii == nth month and count($months) == $term.
            // Execute $stmt for $months[$ii] at the end of each iteration.
            $nMonths = min(5, count($months));
            for ($ii = 1; $ii <= $nMonths; $ii++) {
                // Execute $stmt #1 for $months[$ii]['payment_no']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1709',
                    ':value'     => $months[$ii]['payment_no'],
                    ':row'       => $ii
                ));

                // Execute $stmt #2 for $months[$ii]['payment_due_date']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1711',
                    ':value'     => $months[$ii]['payment_due_date'],
                    ':row'       => $ii
                ));

                // Execute $stmt #3 for $months[$ii]['payment_due']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1713',
                    ':value'     => number_format($months[$ii]['payment_due'], 2, '.', ''),
                    ':row'       => $ii
                ));

                // Execute $stmt #4 for $months[$ii]['payment_principle']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1715',
                    ':value'     => number_format($months[$ii]['payment_principle'], 2, '.', ''),
                    ':row'       => $ii
                ));

                // Execute $stmt #5 for $months[$ii]['payment_interest']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1717',
                    ':value'     => number_format($months[$ii]['payment_interest'], 2, '.', ''),
                    ':row'       => $ii
                ));

                // Execute $stmt #6 for $months[$ii]['payment_gst']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1719',
                    ':value'     => number_format($months[$ii]['payment_gst'], 2, '.', ''),
                    ':row'       => $ii
                ));

                // Execute $stmt #7 for $months[$ii]['payment_total']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1723',
                    ':value'     => number_format($months[$ii]['payment_total'], 2, '.', ''),
                    ':row'       => $ii
                ));

                // Execute $stmt #7 for $months[$ii]['new_balance']
                $stmt->execute(array(
                    ':crmid'     => $amort_ID,
                    ':fieldname' => 'cf_1911',
                    ':value'     => number_format($months[$ii]['new_balance'], 2, '.', ''),
                    ':row'       => $ii
                ));
            } // END for ($ii = 1; $ii <= $nMonths; $ii++)

            $DBH->commit();
            $DBH = null;
            $result = "Succeeded";
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $result;
    }
}

if (!function_exists("wf_insert_amortfull_to_table_block_data2")) {
    /**
     * Updates the MySQL table `table_block_data` with data calculated by wf_create_table_amortfull().
     *
     * @param string $amortfull_result   The string returned by wf_create_table_amortfull()[0].
     *                                   $amortfull_result is either 'Succesful' or 'Failed'.
     * @param int    $amortfull_amort_ID The record ID returned by wf_create_table_amortfull()[1].
     * @param array  $amortfull_months   The array returned by wf_create_table_amortfull()[2].
     *                                   $amortfull_months is an array with 8 associative elements.
     *
     * @param bool   $debug_output       The toggle for debug output to console.
     *
     * @return string $result   String that defaults to 'Failed'.  Changes to 'Succeeded' upon completion.
     */
    function wf_insert_amortfull_to_table_block_data2(
        $amortfull_result = null,
        $amortfull_amort_ID = null,
        array $amortfull_months = null,
        $debug_output = false
    ) {
        /**
         * @var float  $time_spent Float that holds the total execution time in seconds.
         * @var string $result     String that defaults to 'Failed'.  Changes to 'Succeeded' upon completion.
         */
        $time_spent = microtime(true);

        if ($amortfull_result === null) {
            $result = 'Failed. (1) null value: $amortfull_result.' . PHP_EOL;
        } elseif ($amortfull_amort_ID === null) {
            $result = 'Failed. (2) null value: $amortfull_amort_ID.' . PHP_EOL;
        } elseif ($amortfull_months === null) {
            $result = 'Failed. (3) null value: $amortfull_months.' . PHP_EOL;
        } elseif (strcmp($amortfull_result, 'Succeeded') !== 0) {
            $result = 'Failed. (4) Expected $amortfull_result to be "Succeeded".' . PHP_EOL;
        } elseif (count($amortfull_months[1]) !== 8) {
            //$result = 'Failed. (5) Expected $amortfull_months to be (array) with 8 keys.' . PHP_EOL;
            $result = 'Failed. (5) Got ' . count($amortfull_months[1]) . ' keys.' . PHP_EOL;
        } else {
            $result = 'Parameters checked out.';
        }

        if (strcmp($result, 'Parameters checked out.') !== 0) {
            return $result;
        } else {
            $result = 'Failed. (0) Function wf_insert_amortfull_to_table_block_data.';
        }

        $amort_ID = (int)$amortfull_amort_ID;
        $months = $amortfull_months;
        $table = 'table_block_data';

        // set db and table information
        $hostname = 'localhost';
        $username = 'srmadmin';
        $password = 'Fuck0ffPhunk!';
        $dbname = 'srm';

        echo ($debug_output) ? 'Inserting into table `' . $table . '`.' . PHP_EOL : null;

        try {
            function MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                PDO $MySQL_DBH,
                $tb_name,
                $crmid,
                $fieldname,
                $value,
                $row) {
                $MySQL_DBH->query(
                    'INSERT INTO `' . $tb_name . '` (blockid, crmid, fieldname, value, row)
                    VALUES (6, $crmid, $fieldname, $value, $row)'
                );
            }

            // Connect to MySQL DB `SRM`
            $DBH = new PDO('mysql:host=' . $hostname . ';dbname=' . $dbname . ';charset=utf8', $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Prepare INSERT statement $stmt
            $stmt = $DBH->prepare(
                'INSERT INTO `' . $table . '` (blockid, crmid, fieldname, value, row)
                VALUES (6, :crmid, :fieldname, :value, :row)'
            );

            $DBH->beginTransaction();

            // Generate full amortization schedule as $months[$ii] where $ii == nth month and count($months) == $term.
            // Execute $stmt for $months[$ii] at the end of each iteration.
            $nMonths = min(5, count($months));

            for ($ii = 1; $ii <= $nMonths; $ii++) {
                // Execute $stmt #1 for $months[$ii]['payment_no']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1709',
                    $months[$ii]['payment_no'],
                    $ii
                );

                // Execute $stmt #2 for $months[$ii]['payment_due_date']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1711',
                    $months[$ii]['payment_due_date'],
                    $ii
                );

                // Execute $stmt #3 for $months[$ii]['payment_due']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1713',
                    number_format($months[$ii]['payment_due'], 2, '.', ''),
                    $ii
                );

                // Execute $stmt #4 for $months[$ii]['payment_principle']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1715',
                    number_format($months[$ii]['payment_principle'], 2, '.', ''),
                    $ii
                );

                // Execute $stmt #5 for $months[$ii]['payment_interest']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1717',
                    number_format($months[$ii]['payment_interest'], 2, '.', ''),
                    $ii
                );

                // Execute $stmt #6 for $months[$ii]['payment_gst']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1719',
                    number_format($months[$ii]['payment_gst'], 2, '.', ''),
                    $ii
                );

                // Execute $stmt #7 for $months[$ii]['payment_total']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1723',
                    number_format($months[$ii]['payment_total'], 2, '.', ''),
                    $ii
                );

                // Execute $stmt #7 for $months[$ii]['new_balance']
                MySQL_INSERT_wf_insert_amortfull_to_table_block_data2(
                    $DBH,
                    $table,
                    $amort_ID,
                    'cf_1911',
                    number_format($months[$ii]['new_balance'], 2, '.', ''),
                    $ii
                );
            } // END for ($ii = 1; $ii <= $nMonths; $ii++)

            $DBH->commit();
            $DBH = null;
            $result = 'Succeeded';
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $result;
    }
}

// Values pulled from $amort_id of 57735
$amort_ID = 57735;
$term = 240;
$APR = 5.99;
$loan = 127395;
$GST = 12.5;
$monthly_billing_day = '30';
$first_payment_date = '2016-09-30';
$debug_output = true;

//echo ($debug_output) ? 'Calling wf_generate_table_amortfull($amort_id, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $debug_output)' . PHP_EOL : null;

//$results = &wf_create_table_amortfull($amort_id, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $debug_output);

//echo ($debug_output) ? 'Result: ' . $results[0] . PHP_EOL : null;

echo ($debug_output) ? PHP_EOL . 'Calling wf_update_table_block_data($results, $debug_output)' . PHP_EOL : null;

$result = wf_insert_amortfull_to_table_block_data(wf_create_table_amortfull($amort_ID, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $debug_output), $debug_output);

echo ($debug_output) ? 'Result: ' . $result . PHP_EOL : null;