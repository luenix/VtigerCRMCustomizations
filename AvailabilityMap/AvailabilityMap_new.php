<?php

class AvailabilityMap
{

    /**
     * @param string $section         The string that holds the section to be generated.
     *                                Should currently just be 'Section C'.
     *                                Defaults to 'Section C'.
     * @param string $kbmap_filename  The string that hold the filename of the file that hold the base SVG data.
     * @param string $points_filename The string that holds the filename of the file to output SVG points to.
     *                                Defaults to './points.html'.
     * @param bool   $debug_output    The boolean that enables debug output to console.
     * @param bool   $debug_rows      The boolean that toggles generating debug output of $row details.
     */
    public static function generatePoints(
        $section = 'Section C',
        $kbmap_filename = './kbmap.svg',
        $points_filename = './kbmapdefs.svg',
        $debug_output = false,
        $debug_rows = false)
    {
        /**
         * @var string $pointsSVG  The string that holds the HTML content to be output to a text file of SVG points.
         * @var array  $lots     The array that holds the MySQL SELECT query return of lot records.
         *                         Currently expected K001 - K744.
         *                         As of 2016-05-31, K036 and K038 aren't showing up in queries to MySQL table `vtiger_lots_map`.
         * @var string $hostname MySQL DB hostname
         * @var string $username MySQL DB username
         * @var string $password MySQL DB password
         * @var string $dbname   MySQL DB database name
         */
        //$pointsSVG = '';
        //$lots = array();
        $hostname = "-----";
        $username = "-----";
        $password = "-----!";
        $dbname = "-----";

        echo ($debug_output) ? 'Step 1: Initialized' . PHP_EOL : null;

        try {
            $DBH = new PDO("mysql:host=$hostname;dbname=$dbname;charset=utf8", $username, $password);
            $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo ($debug_output) ? 'Step 2: Connected to the database' . PHP_EOL : null;
        } catch (PDOException $e) {
            echo ($debug_output) ? 'PDO error occurred.' . PHP_EOL . 'Error message: ' . $e->getMessage() . PHP_EOL . 'File: ' . $e->getFile() . PHP_EOL . 'Line: ' . $e->getLine() . PHP_EOL : null;
        }

        switch ($section) {
            case 'Section C':
                try {
                    // As of 2016-05-31, K036 and K038 aren't showing up in queries to MySQL table `vtiger_lots_map`.
                    $stmt = $DBH->prepare(
                        'SELECT * 
                        FROM vtiger_lots_map 
                        WHERE (lot_number REGEXP \'^K[0-7][0-9][0-9]$\') 
                        LIMIT 745'
                    );
                    $stmt->execute();

                    if (($debug_output)) {
                        echo 'Step 3: Queried the database for lot information of ' . $section . PHP_EOL;
                        echo '        Row count: ' . $stmt->rowCount() . PHP_EOL;
                        echo '        Lots missing out of 744: ' . (744 - $stmt->rowCount()) . PHP_EOL;
                    }

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
                break; // END 'Section C'
            default:
                break;
        }

        // Can now close $DBH
        $DBH = null;

        echo ($debug_output) ? 'Step 4: Iterated through query result' . PHP_EOL : null;

        foreach ($lots as $lot) {
            $pointsSVG .= '    <circle cx="' . $lot['x_coord'] . '" cy="' . $lot['y_coord'] . '" r="20" stroke="black" stroke-width="3" fill="none"/>' . PHP_EOL;
            $pointsSVG .= '    <circle id="' . $lot['lot_number'] . '"cx="' . $lot['x_coord'] . '" cy="' . $lot['y_coord'] . '" r="19" stroke="' . AvailabilityMap::getCircleColor($lot['status']) . '" stroke-width="2" fill="' . AvailabilityMap::getCircleColor($lot['status']) . '" data-lot_id="' . $lot['lot_id'] . '" data-status="' . $lot['status'] . '" data-price="$' . number_format($lot['price']) . '" data-discount="$' . number_format($lot['price'] - $lot['discount']) . '" data-owner="' . $lot['owner'] . '" data-owner_id="' . $lot['owner_id'] . '" data-tour_date="' . $lot['tour_date'] . '" data-rep="' . $lot['rep'] . '" data-rep_id="' . $lot['rep_id'] . '" data-reserved_date="' . $lot['reserved_date'] . '" data-size_acres="' . $lot['size_acres'] . '" data-size_ft="' . $lot['size_ft'] . '" data-owner_module="' . $lot['owner_module'] . '"' . ' style="cursor: pointer" />' . PHP_EOL;
            $pointsSVG .= '    <text x="' . ($lot['x_coord'] - 11) . '" y="' . ($lot['y_coord'] + 5) . '" font-size="16" style="pointer-events: none; cursor: pointer">' . substr($lot['lot_number'], -3) . '</text>' . PHP_EOL;
        }

        $pointsSVG .= '</symbol>' . PHP_EOL . '</svg>' . PHP_EOL;

        echo ($debug_output) ? 'Step 5: Created HTML of point data' . PHP_EOL : null;

        copy($kbmap_filename, $points_filename);
        file_put_contents($points_filename, $pointsSVG, FILE_APPEND | LOCK_EX);
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

}
