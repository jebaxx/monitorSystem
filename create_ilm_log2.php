<?php

$work_dir = "/var/www/work/";

if (isset($argv)) {
	$sens_csv = $argv[1];
}
else {
	$sens_csv = $work_dir.'sens_log2M.csv';
}

// lf    : TSL2561(Full range illuminance)
// lir   : TSL2561(Infrared range illuminance)
// lv    : TSL2561(visible range illuminance)

$tbl_L = '';
$max_LF = $max_LIR = $max_LV = -100.0;
$sumLF = $sumLIR = $sumLV = 0.0;
$num = 0;

if ($handle = fopen($sens_csv, "r") ) {
    while (($buf = fgetcsv($handle)) != FALSE) {

	$date = reset($buf);
	$time = trim(next($buf));
	$timeofday = '['.substr($time,1,2).','.substr($time,4,2).',0]';
	$tmpS = next($buf);
	$tmpA = next($buf);
	$tmpB = next($buf);
	$m    = next($buf);

	$lv   = next($buf);

	if ($lv != FALSE) {
	    $tbl_L .= '['.$timeofday.','.$lv.'],'.PHP_EOL;

	    $sumLV += $lv;
	    $num ++;
	}

	if ($lv > $max_LV) $max_LV = $lv;
    }
    fclose($handle);
} else {
    echo 'no data';
}
?>

<html>
<head>
	<script type = "text/javascript" src="https://www.google.com/jsapi"></script>
	<script type = "text/javascript">
		google.load('visualization', '1.1', {packages: ['corechart']});
		google.setOnLoadCallback(drawCharts);

	var data_L, opt_L, chart_L, view_L;

	function drawCharts() {
		drawChart_L();
	}

	var columnCheck = [true, true, true, true];

	function drawChart_L() {
		data_L = new google.visualization.DataTable();
		data_L.addColumn('timeofday', 'Time');
		data_L.addColumn('number', 'visible lay illuminence');
		data_L.addRows([
			<?php echo $tbl_L; ?>
		]);

		view_L = new google.visualization.DataView(data_L);

		opt_L = { 
			title: 'illuminance (Lux)',
			legend: { position: 'in' },
			vAxis: { minorGridlines: { count: 4, color: '#E6E6FA' }},
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: [0,0,0], max: [23,59,59] } },
			height: '350',
			width:  '100%'
		};

		chart_L = new google.visualization.LineChart(
					document.getElementById('linechart_illuminence'));


		chart_L.draw(view_L, opt_L);
	}

	function changeSize_L(mode) {
		if (mode == 'small') {
			opt_L['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_L['height'] = 350;
		}
		else {
			opt_L['height'] = 700;
		}
		chart_L.draw(view_L, opt_L);
	}

	</script>

</head>

<body onresize="chart_L.draw(view_L, opt_L);">
        <h2>Raspberry pi 2</h2>

	<form name="visibleData" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 8pt">#SIZE :</span>
	  <input type="radio" name="l_size" onclick="changeSize_L('small')">SMALL
	  <input type="radio" name="l_size" onclick="changeSize_L('medium')" checked="checked">MEDIUM
	  <input type="radio" name="l_size" onclick="changeSize_L('large')">LARGE
	</form>

	<div id="linechart_illuminence"></div>

	<hr>
	<?php
	echo "<h3>*** Record of the day (".$date.") ***</h3>\n";
	echo "Visible ray illuminence: max = ".$max_LV .", average = ".$sumLV  / $num."<br>\n";
	 ?>
</body>
</html>

