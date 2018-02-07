<?php

require_once 'financial_class.php';
//include_once 'AmortizationSchedule_class.php';

if (!function_exists("wf_ppmt")) {
    /**
     * @param float $rate
     * @param int   $payno
     * @param int   $nper
     * @param float $pv
     *
     * @return mixed $amount
     */
    function wf_ppmt($rate, $payno, $nper, $pv)
    {
        $rate /= 1200;
        $f = new Financial;
        $amount = $f->PPMT($rate, $payno, $nper, $pv);

        return $amount;
    }
}

if (!function_exists("wf_ipmt")) {
    /**
     * @param float $rate
     * @param int   $payno
     * @param int   $nper
     * @param float $pv
     *
     * @return mixed  $amount
     */
    function wf_ipmt($rate, $payno, $nper, $pv)
    {
        $rate /= 1200;
        $f = new Financial;
        $amount = $f->IPMT($rate, $payno, $nper, $pv);

        return $amount;
    }
}

if (!function_exists("wf_pmt2")) {
    /**
     * Tweaked version of function wf_pmt from pmt.inc.php
     *
     * Changed to return non-formatted decimal $amount
     *
     * @param float $APR
     * @param int   $term
     * @param float $loan
     *
     * @return float|int $amount
     */
    function wf_pmt2($APR, $term, $loan)
    {
        $APR /= 1200;
        $amount = $APR * -$loan * pow((1 + $APR), $term) / (1 - pow((1 + $APR), $term));

        return $amount;
    }
}

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
     *                                    $results[1] is $amort_ID.
     *                                    $results[2] is the associative array $months with calculated amortization schedule data grouped by nth month of the term.
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
        $results[0] = 'Failed.  Function create_table_amortfull()';

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

        $results[0] = 'Succeeded';
        $results[1] = $amort_ID;
        $results[2] = $months;
        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $results;
    }
}

if (!function_exists("wf_create_table_amortfull_and_textfile")) {
    /**
     * Creates the amortization schedule for a given $amort_ID in a MySQL table and a text file.
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
     *                                    $results[1] is $amort_ID.
     *                                    $results[2] is the associative array $months with calculated amortization schedule data grouped by nth month of the term.
     *                                    $results[3] is the name of the text file created.
     */
    function wf_create_table_amortfull_and_textfile(
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
        $results[0] = 'Failed.  Function create_table_amortfull()';

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

        $file_dir = '/var/www/html/belizesrm/storage/amortfull_schedules/';
        $file_name = 'amort_schedule_' . $amort_ID . '.txt';
        $file_path = $file_dir . $file_name;
        $file_content =
            '+------------+------------------+-------------+-------------------+------------------+-------------+-------------+---------------+' . PHP_EOL .
            '| payment_no | payment_due_date | payment_due | payment_principle | payment_interest | payment_gst | new_balance | payment_total |' . PHP_EOL .
            '+------------+------------------+-------------+-------------------+------------------+-------------+-------------+---------------+' . PHP_EOL;

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

                $file_content .= '| ' . str_pad($months[$ii]['payment_no'], 10, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad($months[$ii]['payment_due_date'], 16, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad(number_format($months[$ii]['payment_due'], 2, '.', '')      , 11, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad(number_format($months[$ii]['payment_principle'], 2, '.', ''), 17, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad(number_format($months[$ii]['payment_interest'], 2, '.', '') , 16, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad(number_format($months[$ii]['payment_gst'], 2, '.', '')      , 11, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad(number_format($months[$ii]['new_balance'], 2, '.', '')      , 11, ' ', STR_PAD_LEFT) . ' ';
                $file_content .= '| ' . str_pad(number_format($months[$ii]['payment_total'], 2, '.', '')    , 13, ' ', STR_PAD_LEFT) . ' |' . PHP_EOL;
            } // END for ($ii = 1; $ii <= $term; $ii++)

            $DBH->commit();
            $DBH = null;
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        $file_content .= "+------------+------------------+-------------+-------------------+------------------+-------------+-------------+---------------+" . PHP_EOL . PHP_EOL;

        $results[0] = 'Succeeded';
        $results[1] = $amort_ID;
        $results[2] = $months;
        if (file_put_contents($file_path, $file_content)) {
            $results[3] = $file_path;
        } else {
            $results[3] = '';
        }
        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $results;
    }
}

if (!function_exists("wf_prepared_insert_amortfull_to_table_block_data")) {
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
    function wf_prepared_insert_amortfull_to_table_block_data(
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
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
            $result = 'Failed. (PDOException).';
            $DBH->rollBack();
            $DBH = null;

            return $result;
        }

        $result = 'Succeeded';
        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $result;
    }
}

if (!function_exists("wf_insert_amortfull_to_table_block_data")) {
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
    function wf_insert_amortfull_to_table_block_data(
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
            $query_table_block_data = '';
            $result = 'Failed. (6).';

            // Connect to MySQL DB `SRM`
            $DBH = new PDO('mysql:host=' . $hostname . ';dbname=' . $dbname . ';charset=utf8', $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $result = 'Failed. (7).';

            //$DBH->beginTransaction();

            // Generate full amortization schedule as $months[$ii] where $ii == nth month and count($months) == $term.
            // Execute $stmt for $months[$ii] at the end of each iteration.
            $nMonths = min(5, count($months));

            for ($ii = 1; $ii <= $nMonths; $ii++) {
                // Execute $stmt #1 for $months[$ii]['payment_no']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1709',
                    $months[$ii]['payment_no'],
                    $ii
                );

                $result = 'Failed. (8).';

                // Execute $stmt #2 for $months[$ii]['payment_due_date']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1711',
                    $months[$ii]['payment_due_date'],
                    $ii
                );

                $result = 'Failed. (9).';

                // Execute $stmt #3 for $months[$ii]['payment_due']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1713',
                    number_format($months[$ii]['payment_due'], 2, '.', ''),
                    $ii
                );

                $result = 'Failed. (10).';

                // Execute $stmt #4 for $months[$ii]['payment_principle']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1715',
                    number_format($months[$ii]['payment_principle'], 2, '.', ''),
                    $ii
                );

                $result = 'Failed. (11).';

                // Execute $stmt #5 for $months[$ii]['payment_interest']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1717',
                    number_format($months[$ii]['payment_interest'], 2, '.', ''),
                    $ii
                );

                $result = 'Failed. (12).';

                // Execute $stmt #6 for $months[$ii]['payment_gst']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1719',
                    number_format($months[$ii]['payment_gst'], 2, '.', ''),
                    $ii
                );

                $result = 'Failed. (13).';

                // Execute $stmt #7 for $months[$ii]['payment_total']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1723',
                    number_format($months[$ii]['payment_total'], 2, '.', ''),
                    $ii
                );

                $result = 'Failed. (14).';

                // Execute $stmt #7 for $months[$ii]['new_balance']
                $query_table_block_data = wf_build_query_for_table_block_data(
                    $query_table_block_data,
                    $table,
                    $amort_ID,
                    'cf_1911',
                    number_format($months[$ii]['new_balance'], 2, '.', ''),
                    $ii
                );

                $result = 'Failed. (15).';
            } // END for ($ii = 1; $ii <= $nMonths; $ii++)

            $result = 'Failed. (16).';

            if ($query_table_block_data !== '') {
                echo ($debug_output) ? $query_table_block_data . PHP_EOL : null;
                $DBH->query($query_table_block_data);
            }
            //$DBH->commit();
            $DBH = null;

            $result = 'Failed. (17).';
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
            $result = 'Failed. (PDOException).';
            //$DBH->rollBack();
            $DBH = null;

            return $result;
        }

        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        $result = 'Succeeded';

        return $result;
    }
}

if (!function_exists("wf_build_query_for_table_block_data")) {
    function wf_build_query_for_table_block_data(
        $query_in,
        $tb_name,
        $crmid,
        $fieldname,
        $value,
        $row
    ) {
        $query_out = '';
        if ($query_in == '') {
            //$query_out = "INSERT INTO `" . $tb_name . "` (blockid, crmid, fieldname, value, row) VALUES (6, " . $crmid . ", '" . $fieldname . ', ' . $value . ', ' . $row . ')';
            $query_out =
                "INSERT INTO `$tb_name` (blockid, crmid, fieldname, value, row) VALUES (6, $crmid, '$fieldname', '$value', $row)";
        } else {
            $query_out = $query_in . ",(6, $crmid, '$fieldname', '$value', $row)";
        }

        $result = 'Failed.  Function wf_build_query_for_table_block_data.';

        return $query_out;
    }
}
