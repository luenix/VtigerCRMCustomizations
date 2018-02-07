<?php

// include('./financial_class.php');

// Code below autoloads classes with require_once.
function __autoload($class_name)
{
    /** @noinspection PhpIncludeInspection */
    require_once $class_name . '.php';
}

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
     * @param bool   $debug               The toggle for debug output to console.
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
        $debug = false
    ) {
        /**
         * @var array  $months Array that holds amortization schedule data by 1st, 2nd, 3rd, ..., nth month
         * @var string $result String that defaults to 'Failed'.  Changes to 'Succeeded' if this function completes the MySQL try block.
         */
        $months = array();
        $result = 'Failed';

        // Call date_default_timezone_set() until datetime.timezone bug is addressed.
        date_default_timezone_set('America/Los_Angeles');

        // Call setlocale() to use $X,XXX.XX formatting later in conjunction with money_format()
        setlocale(LC_MONETARY, 'en_US');

        if ($debug) {
            echo 'wf_generate_amort_table($amort_ID, $term, $APR, $loan, $GST, $monthly_billing_day, $first_payment_date, $output = \'text\', $debug = \'false\')' . PHP_EOL . PHP_EOL;
        }

        // If testing, set $amort_ID to 55371
        if ($amort_ID == 'test') {
            $amort_ID = 55371;
            echo ($debug) ? 'Passed \'test\' for $amort_ID.  Setting $amort_ID to \'' . $amort_ID . '\'.' . PHP_EOL . PHP_EOL : null;
        }

        if ($debug) {
            echo 'Parameters:' . PHP_EOL;
            echo '  $amort_ID            | ' . $amort_ID . PHP_EOL;
            echo '  $term                | ' . $term . PHP_EOL;
            echo '  $APR                 | ' . $APR . PHP_EOL;
            echo '  $loan                | ' . $loan . PHP_EOL;
            echo '  $GST                 | ' . $GST . PHP_EOL;
            echo '  $monthly_billing_day | ' . $monthly_billing_day . PHP_EOL;
            echo '  $first_payment_date  | ' . $first_payment_date . PHP_EOL;
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

        // Set table name with appended $amort_ID
        $amort_tb = "vtiger_amortfull_" . $amort_ID;

        if ($debug) {
            echo 'Creating table ' . $amort_tb . PHP_EOL;
        }

        try {
            $DBH = new PDO("mysql:host=$hostname;dbname=$dbname;charset=utf8", $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Drop table if it exists
            $stmt = $DBH->prepare("DROP TABLE IF EXISTS `" . $amort_tb . "`");
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
                /*
                // format values
                $months[$ii]['payment_due'] = money_format('%n', $months[$ii]['payment_due']);
                $months[$ii]['payment_principle'] = money_format('%n', $months[$ii]['payment_principle']);
                $months[$ii]['payment_gst'] = money_format('%n', $months[$ii]['payment_gst']);
                $months[$ii]['payment_interest'] = money_format('%n', $months[$ii]['payment_interest']);
                $months[$ii]['new_balance'] = money_format('%n', $months[$ii]['new_balance']);
                $months[$ii]['payment_total'] = money_format('%n', $months[$ii]['payment_total']);

                if ($debug) {
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_no'], 10, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_due_date'], 16, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_due'], 11, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_principle'], 17, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_interest'], 16, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_gst'], 11, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['new_balance'], 11, ' ', STR_PAD_LEFT) . ' ';
                    $table_file_content .= '| ' . str_pad($months[$ii]['payment_total'], 13, ' ', STR_PAD_LEFT) . ' |' . PHP_EOL;
                }
                */

                $stmt->execute([
                    ':payment_no'        => $months[$ii]['payment_no'],
                    ':payment_due_date'  => $months[$ii]['payment_due_date'],
                    ':payment_due'       => number_format($months[$ii]['payment_due'], 2, '.', ''),
                    ':payment_principle' => number_format($months[$ii]['payment_principle'], 2, '.', ''),
                    ':payment_interest'  => number_format($months[$ii]['payment_interest'], 2, '.', ''),
                    ':payment_gst'       => number_format($months[$ii]['payment_gst'], 2, '.', ''),
                    ':new_balance'       => number_format($months[$ii]['new_balance'], 2, '.', ''),
                    ':payment_total'     => number_format($months[$ii]['payment_total'], 2, '.', ''),
                ]);

                $result = "Succeeded";
            } // END for ($i = 1; $i <= count($months); $i++)
        } catch (PDOException $e) {
            if ($debug) {
                echo $e->getMessage();
            }
        }

        return $result;
    }
}
