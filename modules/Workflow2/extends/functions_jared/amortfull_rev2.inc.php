<?php

require_once '../functions/ppmt.inc.php';

if (!function_exists("wf_generate_table_amortfull")) {
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
     * @return string $result
     */
    function wf_generate_table_amortfull(
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
         * @var array    $months     Array that holds amortization schedule data by 1st, 2nd, 3rd, ..., nth month.
         * @var DateTime $lastDate   DateTime for later use.
         * @var float    $time_spent Float that holds the total execution time in seconds.
         * @var string   $result     String that defaults to 'Failed'.  Changes to 'Succeeded' if this function completes the MySQL try block.
         */
        $months = array();
        $lastDate = null;
        $time_spent = microtime(true);
        $result = 'Failed';

        // Call date_default_timezone_set() until datetime.timezone bug is addressed.
        date_default_timezone_set('America/Los_Angeles');

        // Adjust $monthly_billing_day to day of $first_payment_date.
        $monthly_billing_day = substr($first_payment_date, -2);

        // Set $first_payment_date to datetime for later use.
        $first_payment_date = strtotime($first_payment_date);

        if (strcmp($amort_ID, 'test') === 0) {
            $amort_ID = 55371;
        }

        // set db and table information
        $hostname = 'localhost';
        $username = 'srmadmin';
        $password = 'Fuck0ffPhunk!';
        $dbname = 'srm';

        // Set table name to `vtiger_amortfull_$amort_ID`.
        $amort_tb = 'vtiger_amortfull_' . $amort_ID;

        echo ($debug_output) ? 'Creating table ' . $amort_tb . PHP_EOL : null;

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
            $result = "Succeeded";
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        echo ($debug_output) ? 'Total execution time in seconds: ' . (microtime(true) - $time_spent) . PHP_EOL : null;

        return $result;
    }
}

// Values pulled from $amort_id of 55371
$amort_id = 'test';
$term = 240;
$APR = 5.99;
$loan = 127395;
$GST = 12.5;
$monthly_billing_day = '29';
$first_payment_date = '2016-03-29';
$debug_output = true;

$result = wf_generate_table_amortfull($amort_id, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $debug_output);

echo ($debug_output) ? 'Result: ' . $result . PHP_EOL : null;
