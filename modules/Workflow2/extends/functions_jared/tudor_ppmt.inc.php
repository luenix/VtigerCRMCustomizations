<?php

include('./financial_class.php');

date_default_timezone_set('America/Los_Angeles');

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

if (!function_exists("wf_generate_amort_table")) {
    /**
     * Creates the amortization schedule for a given $amort_ID.
     *
     * 1.  Drops table `vtiger_amort_$amort_ID` if it exists.
     * 2.  Creates table `vtiger_amort_$amort_ID` to spec.
     * 3.  For every month in $term, calculates and sets 8 data points.
     * 4.  Iterates through the array $months and pushes individual MySQL INSERT queries.
     *
     * @param int    $amort_ID             The record ID to pull financial data from.
     * @param int    $term                 The term in number of months.
     * @param float  $APR                  The Annual Percentage Rate.
     * @param float  $loan                 The initial loan.
     * @param float  $GST                  The Belizean General Sales Tax, currently 12.5% on 2016-05-27.
     * @param int    $monthly_billing_day  The monthly payment due date.  Eventually
     * @param string $first_payment_date   The initial
     * @param string $output
     * @param bool   $debug
     *
     * @todo Create MySQL code for steps 1 & 4
     *
     * @return string $table_file_path
     */

    function wf_generate_amort_table(
        $amort_ID,
        $term,
        $APR,
        $loan,
        $GST,
        $monthly_billing_day,
        $first_payment_date,
        $output = 'text',
        $debug = false
    ) {

        /**
         * @var array  $months             Array that holds amortization schedule data by 1st, 2nd, 3rd, ..., nth month
         * @var string $table_file_content String that holds the text content to be saved to vtiger_amort_$amort_ID.txt
         * @var string $table_file_dir     String that holds file dir of vtiger_amort_$amort_ID.txt
         * @var string $table_file_name    String that holds file name of vtiger_amort_$amort_ID.txt
         * @var string $table_file_path    String that holds file path of vtiger_amort_$amort_ID.txt
         */
        $months = array();
        $table_file_content = '';
        $table_file_dir = '';
        $table_file_name = '';
        $table_file_path = '';

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

        // Set table file information after processing passed function parameters
        $table_file_dir .= './';
        $table_file_name .= 'vtiger_amort_' . $amort_ID . '.txt';
        $table_file_path .= $table_file_dir . $table_file_name;

        // set db and table information
        $servername = "localhost";
        $username = "srmadmin";
        $password = "Fuck0ffPhunk!";
        $dbname = "srm";

        // Set values for query needed to create table if it does not exist
        $mysql_tb = "vtiger_amortfull_" . $amort_ID;
        $tables_in = "Tables_in_" . $dbname;

        // Check mysql connection
        $checkconn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$checkconn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        // Build mysql query
        $sql = "SHOW TABLES WHERE Tables_in_" .$dbname. " = '" .$mysql_tb."'";
        // count number of rows return from query, value of 1 would mean matching table name found
        $results = mysqli_num_rows(mysqli_query($checkconn, $sql));

        // on matching table delete
        if($results > 0){
            /*die("Already Exists");*/
            $sql =	"DROP TABLE " . $mysql_tb;
            mysqli_query($checkconn, $sql);

        }

        // create new table
        $sql =	"CREATE TABLE `" .$mysql_tb."` (
		id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		payment_no int(5) DEFAULT NULL,
		payment_due_date date DEFAULT NULL,
		payment_due decimal(10,2) DEFAULT NULL,
		payment_principle decimal(10,2) DEFAULT NULL,
		payment_interest decimal(10,2) DEFAULT NULL,
		payment_gst decimal(10,2) DEFAULT NULL,
		new_balance decimal(10,2) DEFAULT NULL,
		payment_total decimal(10,2) DEFAULT NULL
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8";
        mysqli_query($checkconn, $sql);

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

        if ($debug) {
            echo PHP_EOL . 'Creating text file `vtiger_amort_' . $amort_ID . '`.' . PHP_EOL . PHP_EOL;
            $table_file_content .= '+------------+------------------+-------------+-------------------+------------------+-------------+-------------+---------------+' . PHP_EOL;
            $table_file_content .= '| payment_no | payment_due_date | payment_due | payment_principle | payment_interest | payment_gst | new_balance | payment_total |' . PHP_EOL;
            $table_file_content .= '+------------+------------------+-------------+-------------------+------------------+-------------+-------------+---------------+' . PHP_EOL;
        }

        // Iterate through array $months, creating an INSERT statement for each month
        for ($ii = 1; $ii <= count($months); $ii++) {
            /*     // format values
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
            // reformat payment due date into mysql compatible one
            $datedue = date("Y-m-d",strtotime($months[$ii]['payment_due_date']));

            // Create connection
            $conn = mysqli_connect($servername, $username, $password, $dbname);
            if (!$conn) {
                die("Connection failed: " . mysqli_connect_error());
            }

            $sql = "INSERT INTO `" . $mysql_tb . "` (payment_no, payment_due_date, payment_due, payment_principle, payment_interest, payment_gst, new_balance, payment_total)
            VALUES (" . $months[$ii]['payment_no'] . ", '" . $datedue . "', " . number_format($months[$ii]['payment_due'], 2, '.', '') . ", " . number_format($months[$ii]['payment_principle'], 2, '.', '') . ", " . number_format($months[$ii]['payment_interest'], 2, '.', '') . ", " . number_format($months[$ii]['payment_gst'], 2, '.', '') . ", " . number_format($months[$ii]['new_balance'], 2, '.', '') . ", " . number_format($months[$ii]['payment_total'], 2, '.', '') . ")";

            if (mysqli_query($conn, $sql)) {
                $mysqlresult = "success";
            } else {
                $mysqlresult = "failed";
                die("Amortfull Write Fail");
            }
            mysqli_close($conn);


        } // END for ($i = 1; $i <= count($months); $i++)


        /*
        if ($debug) {
            $table_file_content .= "+------------+------------------+-------------+-------------------+------------------+-------------+-------------+---------------+" . PHP_EOL . PHP_EOL;
        }

        if ($table_file_content) {
            if (file_put_contents('./vtiger_amort_' . $amort_ID . '.txt', $table_file_content) && $debug) {
                echo 'vtiger_amort_' . $amort_ID . ' data output to file: ' . $table_file_path . PHP_EOL;
            }
        }
        */
        $finalresult = "Generated";
        return $finalresult;
    }
}
