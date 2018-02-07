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
     * @param string $first_payment_date  The initial payment date.
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
         * @var array  $months     Array that holds amortization schedule data by 1st, 2nd, 3rd, ..., nth month
         * @var string $result     String that defaults to 'Failed'.  Changes to 'Succeeded' if this function completes the MySQL try block.
         * @var float  $time_spent Float that holds the total execution time in seconds.
         */
        $months = array();
        $result = 'Failed';
        if ($debug_output) {
            $time_spent = microtime(true);
        }

        // Call date_default_timezone_set() until datetime.timezone bug is addressed.
        date_default_timezone_set('America/Los_Angeles');

        // Call setlocale() to use $X,XXX.XX formatting later in conjunction with money_format()
        setlocale(LC_MONETARY, 'en_US');

        if ($amort_ID == 'test') {
            $amort_ID = 55371;
        }

        // calculate all fields store in matrix
        for ($ii = 1; $ii <= $term; $ii++) {
            // payment_no
            $months[$ii]['payment_no'] = $ii;

            // payment_due_date
            $lastDate = strtotime("last day of +" . ($ii - 1) . "month", strtotime($first_payment_date));
            if (date("d", $lastDate) > substr($first_payment_date, -2)) {
                $months[$ii]['payment_due_date'] = substr(date("Y-m-d", $lastDate), 0, 8) . substr($first_payment_date, -2);
            } else {
                $months[$ii]['payment_due_date'] = date("Y-m-d", $lastDate);
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
            if ($ii == 1) {
                $months[$ii]['new_balance'] = $loan - $months[$ii]['payment_principle'];
            } else {
                $months[$ii]['new_balance'] = $months[$ii - 1]['new_balance'] - $months[$ii]['payment_principle'];
            }

            // payment_total
            $months[$ii]['payment_total'] = $months[$ii]['payment_interest'] + $months[$ii]['payment_principle'] + $months[$ii]['payment_gst'];
        } // END for ($i = 1; $i <= $term; $i++)

        // set db and table information
        $hostname = "localhost";
        $username = "srmadmin";
        $password = "Fuck0ffPhunk!";
        $dbname = "srm";

        // Set table name to `vtiger_amortfull_$amort_ID`.
        $amort_tb = "vtiger_amortfull_" . $amort_ID;

        echo ($debug_output) ? 'Creating table ' . $amort_tb . PHP_EOL : null;

        try {
            $DBH = new PDO("mysql:host=$hostname;dbname=$dbname;charset=utf8", $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Drop table if it exists
            $stmt = $DBH->prepare('DROP TABLE IF EXISTS `' . $amort_tb . '`');
            $stmt->execute();

            // Create table
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
            $stmt->execute();

            // Create a prepared INSERT statement to be used below
            $stmt = $DBH->prepare(
                'INSERT INTO `' . $amort_tb . '` (payment_no, payment_due_date, payment_due, payment_principle, payment_interest, payment_gst, new_balance, payment_total)
                VALUES (:payment_no, :payment_due_date, :payment_due, :payment_principle, :payment_interest, :payment_gst, :new_balance, :payment_total)'
            );

            // Iterate through array $months, creating an INSERT statement for each month
            for ($ii = 1; $ii <= count($months); $ii++) {
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
            } // END for ($i = 1; $i <= count($months); $i++)

            $result = "Succeeded";
        } catch (PDOException $e) {
            echo ($debug_output) ? $e->getMessage() : null;
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
$monthly_billing_day = 29;
$first_payment_date = '2016-03-29';
$debug_output = true;

$result = wf_generate_table_amortfull($amort_id, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $debug_output);

echo 'Result: ' . $result . PHP_EOL;
