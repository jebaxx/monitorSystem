<?php

    include_once "/home/pi/projects/driveLibrary/driveLibraryV3.php";

    function tabulateSensLog2($isClear) {

	openlog("monitorSystem::tabulateSensLog2", LOG_ODELAY | LOG_PERROR, LOG_USER);

    	$work_dir = "/var/www/work/";
    	$spool_dir = "/var/www/_spool/";
    	$php_dir  = "/var/www/html/monitorSystem/";

	try {
	    $uploaded_csv = $spool_dir."sens_log2.csv";
	    $local_csv    = $work_dir."sens_log2M.csv";

	    if (!file_exists($local_csv)) {
		//
		// Local csv file not found. Mavis system might be rebooted.
		// Attempt to restore a measured data from Google Drive
		//
		$content = GD_downloadFile_oneshot("sens_log2M.csv", "0BwEMWPU5Jp9SN0cyM0N6ZWN1Wk0");  // log folder

		if ($content == NULL) {
		    syslog(LOG_NOTICE, "sens_log2M.csv isn't found in google drive storage.");
		}
		else {
		    file_put_contents($local_csv, $content);
		    syslog(LOG_INFO, $local_csv . " file has be recoverd from google drive.");
		}
	    }

	    //
	    // Merge new data from Boco to the local csv
	    //
	    if (($r_hndl = fopen($uploaded_csv, "r")) == FALSE) {
		//
		// There is no new data for merge.
		// The sensor system of Boco may not work.
		//
		syslog(LOG_WARNING, $uploaded_csv. " file not found. No data is arrived for update.");
	    }
	    else {
	        //
	        // Merge process start
	        //
	        $lc = 0;
		while (($entry = fgetcsv($r_hndl)) != FALSE) {
		    $rec[reset($entry).":".next($entry)] = $entry;
		    $lc ++;
		}

		syslog(LOG_DEBUG, "LC(log2): " . $lc);
		fclose($r_hndl);

		if (($r_hndl = fopen($local_csv, "r")) == FALSE) {
		    syslog(LOG_ERR, $local_csv." cannot reopen for read.");
		}
		else {

		    $lc = 0;
		    while (($entry = fgetcsv($r_hndl)) != FALSE) {
			$rec[reset($entry).":".next($entry)] = $entry;
			$lc ++;
		    }

		    syslog(LOG_DEBUG, "LC(log2M): " . $lc);
		    fclose($r_hndl);
		}
		ksort($rec);

		// save merged file to work_dir
		if (($w_hndl = fopen($local_csv, "w")) == FALSE) {
	    	    syslog(LOG_ERR, $local_csv. "cannot open for update.");
	    	    return;
		}

		$lc = 0;
		foreach ($rec as $entry) {
		    fputcsv($w_hndl, $entry);
		    $lc ++;
		}

		syslog(LOG_DEBUG, "LC(log2M): " . $lc);
		fclose($w_hndl);

		//
		// backup marged csv file to Google Drive
		//
		copy($local_csv, $spool_dir."sens_log2M.csv");
	    }

/////////////////////////////////////////////////////////////////////////////////////////////////

	    if (($r_hndl = fopen($local_csv, "r")) == FALSE) {
	    	syslog(LOG_ERR, $local_csv . " cannot open for tabulation");
	    	return;
	    }

	    if (($entry = fgetcsv($r_hndl)) == FALSE) {
	    	syslog(LOG_NOTICE, $local_csv . " have no entry.");
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
		$w_file = $work_dir."Sens2_".$ndate0.".csv";
		$tp_file = $spool_dir."Sens2_".$ndate0.".html";
		$lm_file = $spool_dir."ilm2_".$ndate0.".html";

		if (($w_hndl = fopen($w_file, "a")) == FALSE) {
		    syslog(LOG_ERR, $w_file . " cannot create.");
		    return;
		}

		//
		// write first line of the output file
		//
		fputcsv($w_hndl, $entry);

		while(1) {

			if (($entry = fgetcsv($r_hndl)) == FALSE) {
				//
				// the case of END of File
				//
				fclose($w_hndl);
				fclose($r_hndl);
				exec('sh -c "php '.$php_dir.'createLogPage2.php '.$w_file.'" > '.$tp_file);
				exec('sh -c "php '.$php_dir.'create_ilm_log2.php '.$w_file.'" > '.$lm_file);
				unlink($w_file);
				chmod($tp_file, 0664);
				if ($isClear) {
				    unlink($local_csv);
				    touch($local_csv);
				}
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
				exec('sh -c "php '.$php_dir.'createLogPage2.php '.$w_file.'" > '.$tp_file);
				exec('sh -c "php '.$php_dir.'create_ilm_log2.php '.$w_file.'" > '.$lm_file);
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

			fputcsv($w_hndl, $entry);
		}

	    }

	}
	catch (Exception $e) {
		syslog(LOG_ERR, "tabulateSensLog2 ..." . $e->getMessage());
	}

	return;
    }
 ?>

