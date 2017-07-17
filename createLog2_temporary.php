<?php

createLog2_temporary();

 function createLog2_temporary() {

	openlog("monotorSystem::createLog2_temporary", LOG_ODELAY | LOG_PERROR, LOG_USER);

	include_once "driveLibraryV3.php";

	$work_dir = "/var/www/work/";
	$spool_dir = "/var/www/_spool/";
	$php_dir = "/var/www/html/monitorSystem/";

	try {
	    $uploaded_csv = $spool_dir."sens_log2.csv";
	    $local_csv    = $work_dir."sens_log2M.csv";
	    $temp_csv     = $work_dir."sens_log_Tmp.csv";

	    if (file_exists($local_csv)) {
	    	copy($local_csv, $temp_csv);
	    }
	    else {
		//
		// Local csv file not found. Mavis system might be rebooted.
		// Attempt to restore a measured data from Google Drive
		//
		$content = GD_downloadFile_oneshot("sens_log2M.csv", "0BwEMWPU5Jp9SN0cyM0N6ZWN1Wk0");  // log folder

		if ($content == NULL) {
		    syslog(LOG_NOTICE, "sens_log2M.csv isn't found in google drive storage.");
		}
		else {
		    file_put_contents($temp_csv, $content);
		    syslog(LOG_INFO, "create ".$temp_csv . " using data from google drive.");
		}
	    }

	    //
	    // load local data and insert to array
	    //
	    if (($r_hndl = fopen($temp_csv, "r")) == FALSE) {
		syslog(LOG_ERR, $local_csv." cannot reopen for read.");
	    }
	    else {
		while (($entry = fgetcsv($r_hndl)) != FALSE) {
		    $rec[reset($entry).":".next($entry)] = $entry;
		}

		fclose($r_hndl);
	    }

	    //
	    // Merge uploaded data from Boco
	    //
	    if (($r_hndl = fopen($uploaded_csv, "r")) == FALSE) {
		syslog(LOG_WARNING, $uploaded_csv. " file not found. No data is arrived for update.");
	    }
	    else {
		//
		// Merge process start
		//
		while (($entry = fgetcsv($r_hndl)) != FALSE) {
		    $rec[reset($entry).":".next($entry)] = $entry;
		}

		fclose($r_hndl);
	    }

	    ksort($rec);

	    // save merged file to work_dir
	    if (($w_hndl = fopen($temp_csv, "w")) == FALSE) {
	    	syslog(LOG_ERR, $temp_csv. "cannot open for update.");
	    	return;
	    }
	    else {

		foreach ($rec as $entry) {
		    fputcsv($w_hndl, $entry);
		}

		fclose($w_hndl);
	    }

	    system('sh -c "php '.$php_dir.'createLogPage2.php '.$temp_csv.'"');
	}
	catch (Exception $e) {
	    syslog(LOG_ERR, "createLog2_temporary" . $e->getMessage());
	}

	unlink($temp_csv);

	return;

 }
 ?>
