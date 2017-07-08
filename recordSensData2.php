<?php

    function recordSensData2() {

    	$home_dir = "/home/pi/projects/";
    	$spool_dir = "/var/www/_spool/";

	openlog("recordSensData2", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
		$timestamp = "'".date("Y/m/d")."'";
		$time = "'".date("H:i")."'";
		$val = str_getcsv(exec("python ".$home_dir."measureSenserData/MeasureSenserData.py"));
		$tmpS = trim($val[0]);
		$tmpA = trim($val[1]);
		$tmpB = trim($val[2]);
		$hum  = trim($val[3]);
		$ilm  = trim($val[4]);

		$line = $timestamp.', '.$time.',';
		$line .= sprintf("%6.2f, ", $tmpS);
		$line .= sprintf("%8.4f, ", $tmpA);
		$line .= sprintf("%5.1f, ", $tmpB);
		$line .= sprintf("%7.2f, ", $hum);
		$line .= sprintf("%8.3f",   $ilm);

		if ($time == "'00:00'") {
			unlink($spool_dir."sens_log2.csv");
		}
		if ( $handle = fopen($spool_dir."sens_log2.csv", "a") ) {
			fwrite($handle, $line."\n");
			fclose($handle);
		}
		else {
			syslog(LOG_ERROR, "csv file cannot open.");
		}

	}
	catch (Exception $e) {
		echo $e->getMessage();
	}

	return;
    }

 ?>
