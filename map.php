<?php
date_default_timezone_set('America/Los_Angeles');

$kbmap = 'AvailabilityMap/kbmap.html';
$points = 'AvailabilityMap/points.html';
$points_lastmodifiedtime = '';

if (file_exists($points)) {
    $points_lastmodifiedtime = date("F d, Y @ h:iA", filemtime($points));
    $points_shorthandtime = date("m-d-y_hiA", filemtime($points));
}
?>
<!DOCTYPE html>
<html>
<head>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link type="text/css" rel="stylesheet" href="AvailabilityMap/css/map.css">
</head>
<body>
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0">
  <symbol id="kbmapdefs">
<?php include $kbmap; ?>
<?php include $points; ?>
  </symbol>
</svg>
<!-- Disapproval noted by Justin: -->
<div id="timestamp">
  <span><strong>Map Last Updated:</strong> <span id="points-LMT" data-shorthandtime="<?php echo $points_shorthandtime; ?>"><?php echo $points_lastmodifiedtime; ?></span><!-- &bull; <button type="button" id="download-PDF">Save Map as PDF</button>-->
</div>
<div id="kbmap-wrapper">
  <svg
   id="kbmap"
   version="1.0"
   xmlns="http://www.w3.org/2000/svg"
   viewBox="0 0 19030.000000 4394.000000"
   preserveAspectRatio="xMidYMid meet">
    <use xlink:href="#kbmapdefs"/>
  </svg>
</div>
<div id="section-button-wrapper">
  <div id="section-button-a" class="section-button">Section A</div>
  <div id="section-button-b" class="section-button">Section B</div>
  <div id="section-button-c" class="section-button">Section C</div>
  <br>
  <div id="section-button-full" class="section-button">Full Map</div>
</div>
<div id="lot-box" class="shown">
  <div id="lot-box-header">
    <div id="lot-box-title">Lot Information</div>
    <div id="lot-box-close">X</div>
  </div>
  <table id="lot-box-data">
    <tr>
      <td id="lb-price">&nbsp;</td>
      <td id="lb-size_acres"><i>ac</i></td>
    </tr>
    <tr>
      <td></td>
      <td></td>
    </tr>
    <tr>
      <td>Owner</td>
      <td id="lb-owner">&nbsp;</td>
    </tr>
    <tr>
      <td>Tour Date</td>
      <td id="lb-tour_date">&nbsp;</td>
    </tr>
    <tr>
      <td></td>
      <td></td>
    </tr>
    <tr>
      <td>Property Consultant</td>
      <td id="lb-rep">&nbsp;</td>
    </tr>
    <tr>
      <td>Reserved Date</td>
      <td id="lb-reserved_date">&nbsp;</td>
    </tr>
  </table>
  <div id="lot-box-hidden-data" class="hidden">
    <div id="lb-lot_id"></div>
    <div id="lb-rep_id"></div>
    <div id="lb-owner_id"></div>
    <div id="lb-owner_module"></div>
  </div>
</div>
<script type="text/javascript" src="AvailabilityMap/js/map.js"></script>
</body>
</html>
