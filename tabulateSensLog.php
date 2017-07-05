<?php

    function tabulateSensLog($isClear) {

    	$work_dir = "/var/www/work/";
    	$spool_dir = "/var/www/_spool/";
    	$home_dir = "/home/pi/";
    	$php_dir  = "/var/www/html/monitorSystem/";

    	$t_flag  = 0;

	openlog("monitorSystem::tabulateSensLog", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
		$input_csv = $work_dir."sens_log.csv";

		if (($r_hndl = fopen($input_csv, "r")) == FALSE) {
			//
			// The sensor system did not work.
			//
			syslog(LOG_WARNING, "sens_log file not found.");
			return;
		}

		if (($entry = fgetcsv($r_hndl)) == FALSE) {
			//
			// The sensor system is not working.
			//
			syslog(LOG_WARNING, "The senser system is not working.");
			fclose($r_hndl);
			return;
		}

		$ndate0 = "";
		$tok = strtok(trim($entry[0], "' "), "/");
		while($tok) {
			$ndate0 .= $tok;
			$tok = strtok("/");
		}

		while(1) {

			//
			// Create output file
			//
			$w_file = $work_dir."Sens_".$ndate0.".csv";
			$tp_file = $spool_dir."Sens_".$ndate0.".html";
			$lm_file = $spool_dir."ilm_".$ndate0.".html";
			$w_hndl = fopen($w_file, "a");

			//
			// write first line of the output file
			//
			if ($entry[5] == 0) {
				//
				// If measured barometric pressure == 0 [hPa], it means I2C bus was disconnected.
				//
				syslog(LOG_WARNING, "Some of sens data was truncated because of illegal value.");
				$t_flag = 1;
			}
			else {
				fputcsv($w_hndl, $entry);
			}

			while(1) {

				if (($entry = fgetcsv($r_hndl)) == FALSE) {
					//
					// the case of END of File
					//
					fclose($w_hndl);
					fclose($r_hndl);
					exec('sh -c "php '.$php_dir.'createLogPage.php '.$w_file.'" > '.$tp_file);
					exec('sh -c "php '.$php_dir.'create_ilm_log.php '.$w_file.'" > '.$lm_file);
					unlink($w_file);
					chmod($tp_file, 0664);
					if ($isClear) {
					    unlink($input_csv);
					    touch($input_csv);
					}
					copy($input_csv, $spool_dir."sens_log.csv");
					return;
				}

				$ndateX = "";
				$tok = strtok(trim($entry[0], "' "), "/");
				while($tok) {
					$ndateX .= $tok;
					$tok = strtok("/");
				}

				if ($ndateX != $ndate0) {
					//
					// the case of date boundary
					//
					fclose($w_hndl);
					exec('sh -c "php '.$php_dir.'createLogPage.php '.$w_file.'" > '.$tp_file);
					exec('sh -c "php '.$php_dir.'create_ilm_log.php '.$w_file.'" > '.$lm_file);
					unlink($w_file);
					chmod($tp_file, 0664);
					$ndate0 = "";
					$tok = strtok(trim($entry[0], "' "), "/");
					while($tok) {
						$ndate0 .= $tok;
						$tok = strtok("/");
					}
					break;
				}

				if ($entry[5] == 0) {

 					if ($t_flag == 0) {
						syslog(LOG_WARNING, "Some of sens data was truncated because of illegal value.");
						$t_flag = 1;
					}
				}
				else {
					fputcsv($w_hndl, $entry);
				}
			}

		}

	}
	catch (Exception $e) {
		echo $e->getMessage();
	}

	return;
    }
 ?>

