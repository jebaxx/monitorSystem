<?php

    function recordSensData() {

    	$work_dir = "/var/www/work/";
    	$spool_dir = "/var/www/_spool/";
    	$home_dir = "/home/pi/projects/python/";

	try {
		// To permit backup & restore operation,
		// log file shoud have write permission to pi group user
		umask(002);

		// LED signal
		exec("python ".$home_dir."led_sig.py");

		$timestamp = "'".date("Y/m/d")."'";
		$time = "'".date("H:i")."'";
		$tmpS = exec("python ".$home_dir."measure_tmpS.py");
		$tmpA = exec("python ".$home_dir."measure_tmpA.py");
		$p1   = exec("python ".$home_dir."measure_prs.py");
		$p_A  = str_getcsv($p1);
		$tmpB = trim($p_A[0]);
		$press = trim($p_A[1]);
		$illum = exec("python ".$home_dir."measure_lux.py");
		$illum = str_getcsv($illum);
		$lf   = trim($illum[0]);
		$lir  = trim($illum[1]);
		$lv   = trim($illum[2]);

		$line = $timestamp.', '.$time.',';
		$line .= sprintf("%6.2f, ", $tmpS);
		$line .= sprintf("%8.4f, ", $tmpA);
		$line .= sprintf("%5.1f, ", $tmpB);
		$line .= sprintf("%7.2f, ", $press);
		$line .= sprintf("%8.3f, ", $lf);
		$line .= sprintf("%8.3f, ", $lir);
		$line .= sprintf("%8.3f", $lv);

		if ( ! file_exists($work_dir."sens_log.csv" )) {
		    //
		    // The case the system was rebooted, sensor record file in the RAM-DISK 
		    //  was disappeared. So, if csv file is not found in the RAM-DISK, 
		    //  the module try to revive the record data from Google drive backup.
		    //
		    include_once "/home/pi/projects/driveLibrary/driveLibraryV3.php";
		    $folder_id = "0BwEMWPU5Jp9SN0cyM0N6ZWN1Wk0";
		    $content = GD_downloadFile_oneshot("sens_log.csv", $folder_id);
		    file_put_contents($work_dir."sens_log.csv", $content);
		}

		if ( $handle = fopen($work_dir."sens_log.csv", "a") ) {
			fwrite($handle, $line."\n");
			fclose($handle);
		}

		if ($press == 0) {
			openlog("recordSensData", LOG_ODELAY | LOG_PERROR, LOG_USER);
			syslog(LOG_ERR, "*** Illegal sensor status!!! ***");

			if ( $ehandle = fopen($work_dir."led_warn.cem", "x") ) {
				fclose($ehandle);
				syslog(LOG_DEBUG, "*** exec led_warn ***");
				$phandle = popen("python ".$home_dir."led_warn.py", "r");
				pclose($phandle);
			}
		}

		if ( $handle = fopen($work_dir."illuminance.txt", "w") ) {
			fputs($handle, sprintf("%8.3f", $lv));
			fclose($handle);
		}

		// syslog test
		// openlog("recordSensData", LOG_ODELAY | LOG_PERROR, LOG_USER);
		// syslog(LOG_DEBUG, "*** this is test ***");
	}
	catch (Exception $e) {
		syslog(LOG_ERR, "*** Exception captured ***");
		echo $e->getMessage();
	}

	return;
    }
 ?>
