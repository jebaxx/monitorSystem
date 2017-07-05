<?php

$work_dir = "/var/www/work/";

if (isset($argv)) {
	$sens_csv = $argv[1];
}
else {
	$sens_csv = $work_dir.'sens_log2M.csv';
}

// tmp_A : ADT7410
// tmp_B : AM3230(Temperature)
// M     : AM3230(humidity)

$tbl_T = $tbl_M = '';
$min_TS = $min_TA = $min_TB =  100.0;
$max_TS = $max_TA = $max_TB = -100.0;
$min_M = 100.0;
$max_M = 0.0;
$sumS = $sumA = $sumB = $sumM = 0.0;
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

	$tbl_T .= '[' .$timeofday. ',' .$tmpS. ',' .$tmpA. ',' .$tmpB. '],' .PHP_EOL;
	$tbl_M .= '[' .$timeofday. ',' .$m. '],' .PHP_EOL;

	$sumS += $tmpS;
	$sumA += $tmpA;
	$sumB += $tmpB;
	$sumM += $m;
	$num ++;

	if ($tmpS > $max_TS) $max_TS = $tmpS;
	if ($tmpS < $min_TS) $min_TS = $tmpS;
	if ($tmpA > $max_TA) $max_TA = $tmpA;
	if ($tmpA < $min_TA) $min_TA = $tmpA;
	if ($tmpB > $max_TB) $max_TB = $tmpB;
	if ($tmpB < $min_TB) $min_TB = $tmpB;
	if ($m > $max_M) $max_M = $m;
	if ($m < $min_M) $min_M = $m;
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

	var data_T, opt_T, chart_T, view_T;
	var data_M, opt_M, chart_M, viwe_M;

	function drawCharts() {
		drawChart_T();
		drawChart_M();
	}

	var columnCheck = [true, false, true, true];

	function drawChart_T() {
		data_T = new google.visualization.DataTable();
		data_T.addColumn('timeofday', 'Time');
		data_T.addColumn('number', 'CPU');
		data_T.addColumn('number', 'ADT7410');
		data_T.addColumn('number', 'AM2320');
		data_T.addRows([
			<?php echo $tbl_T; ?>
		]);

		view_T = new google.visualization.DataView(data_T);

		opt_T = { 
			title: 'Temperature (C)',
			legend: { position: 'in' },
			vAxis: { minorGridlines: { count: 4, color: '#E6E6FA' }},
			hAxis: { minorGridlines: { count: 3, color: '#E6E6FA' },
				 viewWindow: {min: [0,0,0], max: [23,59,59] } },
			height: '350',
			width:  '100%'
		};

		chart_T = new google.visualization.LineChart(
					document.getElementById('linechart_temp'));

		var setC = new Array();
		for (i = j = 0; i < columnCheck.length; i++) {
			if (columnCheck[i]) {
				setC[j++] = i;
			}
		}
		view_T.setColumns(setC);

		chart_T.draw(view_T, opt_T);
	}

	function drawChart_M() {
		data_M = new google.visualization.DataTable();
		data_M.addColumn('timeofday', 'Time');
		data_M.addColumn('number', 'AM2320');
		data_M.addRows([
			<?php echo $tbl_M; ?>
		]);

		view_M = new google.visualization.DataView(data_M);

		opt_M = {
 			title: 'Humidity(%)',
			legend: { position: 'in' },
			vAxis: { viewWindow: {min: null, max: null, },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			hAxis: { viewWindow: {min: [0,0,0], max: [23,59,59] },
				 minorGridlines: { count: 3, color: '#E6E6FA' } },
			height: '350',
			width:  '100%'
		};

		chart_M = new google.visualization.LineChart(
					document.getElementById('linechart_M'));


		chart_M.draw(view_M, opt_M);
	}

	function changeColumn(index, checked) {
		if (checked) {
			columnCheck[parseInt(index)] = true;
		}
		else {
			columnCheck[parseInt(index)] = false;
		}
		
		var setC = new Array();
		for (i = j = 0; i < columnCheck.length; i++) {
			if (columnCheck[i]) {
				setC[j++] = i;
			}
		}

		view_T.setColumns(setC);
		chart_T.draw(view_T, opt_T);
	}

	function changeScale(mode) {
		if (mode == 'FIXED') {
			opt_M['vAxis']['viewWindow']['max'] = 120;
			opt_M['vAxis']['viewWindow']['min'] = 0;
		}
		else {
			opt_M['vAxis']['viewWindow']['min'] = null;
			opt_M['vAxis']['viewWindow']['max'] = null;
		}
		chart_M.draw(view_M, opt_M);
	}

	function changeSize_T(mode) {
		if (mode == 'small') {
			opt_T['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_T['height'] = 350;
		}
		else {
			opt_T['height'] = 700;
		}
		chart_T.draw(view_T, opt_T);
	}

	function changeSize_M(mode) {
		if (mode == 'small') {
			opt_M['height'] = 150;
		}
		else if (mode == 'medium') {
			opt_M['height'] = 350;
		}
		else {
			opt_M['height'] = 700;
		}
		chart_M.draw(view_M, opt_M);
	}

	</script>

</head>

<body onresize="chart_T.draw(view_T, opt_T);chart_M.draw(view_M, opt_M);">
        <h2>Raspberry pi 2 Model B</h2>

	<form name="visibleData" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">#Data Select:</span>
	  <input type="checkbox" name="tmpr" value="1" onclick="changeColumn(this.value, this.checked)">
	  <span style="padding-right: 10pt">CPU</span>
	  <input type="checkbox" name="tmpr" value="2" checked="checked" onclick="changeColumn(this.value, this.checked)">
	  <span style="padding-right: 10pt">ADT7410</span>
	  <input type="checkbox" name="tmpr" value="3" checked="checked" onclick="changeColumn(this.value, this.checked)">
	  <span style="padding-right: 30pt">AM2320</span>

	  <span style="padding-right: 8pt">#SIZE :</span>
	  <input type="radio" name="t_size" onclick="changeSize_T('small')">SMALL
	  <input type="radio" name="t_size" onclick="changeSize_T('medium')" checked="checked">MEDIUM
	  <input type="radio" name="t_size" onclick="changeSize_T('large')">LARGE
	</form>

	<div id="linechart_temp"></div>

	<form name="scale" style="padding-left:2px; border:1px solid gray; background-color: buttonface;">
	  <span style="padding-right: 10pt">#SCALE:</span>
	  <input type="radio" name="s_temp" onclick="changeScale('AUTO')" checked="checked">AUTO
	  <input type="radio" name="s_temp" onclick="changeScale('FIXED')">FIXED
	  <span style="padding-left: 50pt; padding-right: 8pt">#SIZE:</span>
	  <input type="radio" name="p_size" onclick="changeSize_M('small')">SMALL
	  <input type="radio" name="p_size" onclick="changeSize_M('medium')" checked="checked">MEDIUM
	  <input type="radio" name="p_size" onclick="changeSize_M('large')">LARGE
	</form>

	<div id="linechart_M"></div>
	<hr>
	<?php
	echo "<h3>*** Record of the day (".$date.") ***</h3>\n";
	echo "tmp_A(ADT7410) : min = ".$min_TA.', max = '.$max_TA.', average = '.$sumA / $num."<br>\n";
	echo "tmp_B(AM2320)  : min = ".$min_TB.', max = '.$max_TB.', average = '.$sumB / $num."<br>\n";
	echo "tmp_S(CPU)     : min = ".$min_TS.', max = '.$max_TS.', average = '.$sumS / $num."<br>\n";
	echo "humidity       : min = ".$min_M .', max = '.$max_M .', average = '.$sumM / $num."<br>\n <br><hr>\n";
	 ?>
</body>
</html>

