<?php

class AvailabilityMap
{

    /**
     * @param string $section         The string that holds the section to be generated.
     *                                Should currently just be 'Section C'.
     *                                Defaults to 'Section C'.
     * @param string $points_filename The string that holds the filename of the file to output SVG points to.
     *                                Defaults to './points.html'.
     * @param bool   $debug_output    The boolean that enables debug output to console.
     * @param bool   $debug_rows      The boolean that toggles generating debug output of $row details.
     */
    public static function generatePoints($points_filename = './points.html', $debug_output = false, $debug_rows = false)
    {
        /**
         * @var string $mapHTML  The string that holds the HTML content to be output to a text file of SVG points.
         * @var array  $lots     The array that holds the MySQL SELECT query return of lot records.
         *                         Currently expected K001 - K744.
         *                         As of 2016-05-31, K036 and K038 aren't showing up in queries to MySQL table `vtiger_lots_map`.
         * @var string $hostname MySQL DB hostname
         * @var string $username MySQL DB username
         * @var string $password MySQL DB password
         * @var string $dbname   MySQL DB database name
         */
        //$mapHTML  = '';
        //$lots     = array();
        $hostname = "localhost";
        $username = "-----";
        $password = "-----";
        $dbname = "-----";

        echo ($debug_output) ? 'Step 1: Initialized' . PHP_EOL : null;

        try {
            $DBH = new PDO("mysql:host=$hostname;dbname=$dbname;charset=utf8", $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo ($debug_output) ? 'Step 2: Connected to the database' . PHP_EOL : null;
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        try {
            // As of 2016-05-31, K036 and K038 aren't showing up in queries to MySQL table `vtiger_lots_map`.
            $stmt = $DBH->prepare(
                'SELECT * 
                FROM vtiger_lots_map 
                WHERE (lot_number REGEXP \'^K[0-7][0-9][0-9]$\' OR lot_number REGEXP \'^KB[7-9][0-9][0-9]$\')'
            );
            $stmt->execute();

            /*
            if (($debug_output)) {
                echo 'Step 3: Queried the database for lot information of ' . $section . PHP_EOL;
                echo '        Row count: ' . $stmt->rowCount() . PHP_EOL;
                echo '        Lots missing out of 744: ' . (744 - $stmt->rowCount()) . PHP_EOL;
            }
            */

            if ($debug_rows) {
                // Do generate debug output of $row details
                $rowNum = 0;
                $rowOffset = 0;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rowNum++;

                    // Toggle following if statement to ignore expected K036 and K038 mismatches.
                    /*
                    if ($rowNum == 36 || $rowNum == 38) {
                        $rowNum++;
                    }
                    */

                    $lots[$row['lot_number']] = $row;

                    if ($debug_output && (int)substr($row['lot_number'], -3) != $rowNum + $rowOffset) {
                        // Found a row that is mismatched between $rowNum and expected $row['lot_number'].
                        $rowOffset++;
                        echo 'At row ' . ($rowNum + $rowOffset) . ', found lot_number: ' . $row['lot_number'] . '***' . PHP_EOL;
                    } elseif ($debug_output) {
                        echo 'At row ' . ($rowNum + $rowOffset) . ', found lot_number: ' . $row['lot_number'] . PHP_EOL;
                    }
                }
            } else {
                // Do not generate debug output of $row details
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $lots[$row['lot_number']] = $row;
                }
            }
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        // Can now close $DBH
        $DBH = null;

        echo ($debug_output) ? 'Step 4: Iterated through query result' . PHP_EOL : null;

        $dateRegex = '/^\d{4}\-\d{2}\-\d{2}$/';
        /*
        if (preg_match($dateRegex, $test)) {
            echo 'does match<br>';
            echo substr($test, 5) . '-' . substr($test, 0, 4) . '<br>';
        } else {
            echo 'does not match<br>';
        }
        */

        foreach ($lots as $lot) {
            // Format data
            if (
                strcmp($lot['price'], '0.00') === 0 ||
                $lot['price'] === null
            ) {
                $lot['price'] = '';
            } else {
                $lot['price'] = '$' . number_format($lot['price']);
            }

            if (
                strcmp($lot['discount'], '0.00') === 0 ||
                $lot['discount'] === null
            ) {
                $lot['discount'] = '';
            } else {
                $lot['discount'] = '($' . number_format($lot['price'] - $lot['discount']) . ')';
            }

            if (
                strcmp($lot['reserved_date'], '0000-00-00') === 0 ||
                $lot['reserved_date'] === null
            ) {
                $lot['reserved_date'] = '';
            } elseif (preg_match($dateRegex, $lot['reserved_date'])) {
                $lot['reserved_date'] = substr($lot['reserved_date'], 5) . '-' . substr($lot['reserved_date'], 0, 4);
            }

            if (
                strcmp($lot['tour_date'], '0000-00-00') === 0 ||
                $lot['tour_date'] === null
            ) {
                $lot['tour_date'] = '';
            } elseif (preg_match($dateRegex, $lot['tour_date'])) {
                $lot['tour_date'] = substr($lot['tour_date'], 5) . '-' . substr($lot['tour_date'], 0, 4);
            }

            // Outer circle
            $mapHTML .= '    <circle';
            $mapHTML .= ' cx="' . $lot['x_coord'] . '"';
            $mapHTML .= ' cy="' . $lot['y_coord'] . '"';
            $mapHTML .= ' fill="none"';
            $mapHTML .= ' r="15"';
            $mapHTML .= ' stroke="black"';
            $mapHTML .= ' stroke-width="3"';
            $mapHTML .= ' />' . PHP_EOL;

            // Inner circle
            $mapHTML .= '    <circle';
            $mapHTML .= ' id="' . $lot['lot_number'] . '"';
            $mapHTML .= ' style="cursor: pointer"';
            $mapHTML .= ' cx="' . $lot['x_coord'] . '"';
            $mapHTML .= ' cy="' . $lot['y_coord'] . '"';
            $mapHTML .= ' r="14"';
            $mapHTML .= ' fill="' . AvailabilityMap::getCircleColor($lot['status']) . '"';
            $mapHTML .= ' stroke="' . AvailabilityMap::getCircleColor($lot['status']) . '"';
            $mapHTML .= ' stroke-width="2"';
            $mapHTML .= ' data-discount="' . $lot['discount'] . '"';
            $mapHTML .= ' data-lot_id="' . $lot['lot_id'] . '"';
            $mapHTML .= ' data-owner="' . $lot['owner'] . '"';
            $mapHTML .= ' data-owner_id="' . $lot['owner_id'] . '"';
            $mapHTML .= ' data-price="' . $lot['price'] . '"';
            $mapHTML .= ' data-rep="' . $lot['rep'] . '"';
            $mapHTML .= ' data-rep_id="' . $lot['rep_id'] . '"';
            $mapHTML .= ' data-reserved_date="' . $lot['reserved_date'] . '"';
            $mapHTML .= ' data-size_acres="' . $lot['size_acres'] . '"';
            $mapHTML .= ' data-size_ft="' . $lot['size_ft'] . '"';
            $mapHTML .= ' data-status="' . $lot['status'] . '"';
            $mapHTML .= ' data-tour_date="' . $lot['tour_date'] . '"';
            $mapHTML .= ' data-owner_module="' . $lot['owner_module'] . '"';
            $mapHTML .= ' />' . PHP_EOL;

            // Text over circle
            $mapHTML .= '    <text';
            $mapHTML .= ' font-size="12"';
            $mapHTML .= ' font-family="Verdana"';
            $mapHTML .= ' stroke="' . AvailabilityMap::getTextColor(AvailabilityMap::getCircleColor($lot['status'])) . '"';
            $mapHTML .= ' stroke-width="' . AvailabilityMap::getTextStrokeWidth(AvailabilityMap::getCircleColor($lot['status'])) . '"';
            $mapHTML .= ' style="pointer-events: none; cursor: pointer;"';
            $mapHTML .= ' x="' . ($lot['x_coord'] - 9) . '"';
            $mapHTML .= ' y="' . ($lot['y_coord'] + 5) . '"';
            $mapHTML .= ' >';
            $mapHTML .= substr($lot['lot_number'], -3);
            $mapHTML .= '</text>' . PHP_EOL;
        }

        echo ($debug_output) ? 'Step 5: Created HTML of point data' . PHP_EOL : null;

        file_put_contents($points_filename, $mapHTML);
        chmod($points_filename, 0644);
        chown($points_filename, 'apache');
        chgrp($points_filename, 'apache');

        echo ($debug_output) ? 'Step 6: Created HTML file \'' . $points_filename . '\'' . PHP_EOL : null;
    }

    /**
     * @param string $status String that holds lot status.
     *                       Can be one of the following:
     *                       - 'Available'
     *                       - 'Future Release'
     *                       - 'SOLD'
     *                       - 'RESERVED'
     *                       - 'SOT"
     *                       Defaults to return 'white'.
     *
     * @return string $color String that holds color of lot.
     */
    public static function getCircleColor($status = '')
    {
        /**
         * @var string $color String to be returned that holds color of lot.
         */
        $color = 'white';

        switch ($status) {
            case 'Available':
                $color = 'green';
                break;
            case 'Future Release':
                $color = 'grey';
                break;
            case 'SOLD':
                $color = 'red';
                break;
            case 'RESERVED':
                $color = 'yellow';
                break;
            case 'SOT':
                $color = 'red';
                break;
            default:
                break;
        }

        return $color;
    }

    public static function getTextColor($circleColor = '')
    {
        $color = 'black';

        switch ($circleColor) {
            case 'red';
                $color = '#fff';
                break;
            default:
                break;
        }

        return $color;
    }

    public static function getTextStrokeWidth($circleColor = '')
    {
        $strokeWidth = '1.0';

        switch ($circleColor) {
            case 'red';
                $strokeWidth = '0.3';
                break;
            default:
                break;
        }

        return $strokeWidth;
    }

}
