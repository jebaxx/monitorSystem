<?php

    	$work_dir = "/var/www/work/";
    	$home_dir = "/var/www/html/monitorSystem/";

	openlog("jobEngine", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
	    syslog(LOG_DEBUG, "start"); ////
	    //
	    // read job define table
	    //
	    $def_file = $work_dir.".jobTable";
	    $def_file_home = $home_dir.".jobTable";
	    if (($handle = fopen($def_file, "r")) == null) {
		echo "*** file (.jobTable) not found\n";
		copy($def_file_home, $def_file);
		$handle = fopen($def_file, "r");
	    }

	    while ($cols = fgetcsv($handle)) {
		$jobTable[trim($cols[0])][0]=trim($cols[1]);
		$jobTable[trim($cols[0])][1]=trim($cols[2]);
	    }

	    fclose($handle);

	    //
	    // read counter
	    //
	    $counter_ptn = glob($work_dir.".job_*");

	    if (($counter_file = current($counter_ptn)) == FALSE) {
		echo "*** file (counter) not found in work dir\n";
		$counter = 0;
		$counter_file = $work_dir.".job_".sprintf("%05d",$counter);
		touch($counter_file);
		echo "*** counter = ".$counter."\n";
	    } else {
		sscanf($counter_file, $work_dir.'.job_%d', $counter);
		$next_file = $work_dir.".job_".sprintf("%05d",++$counter);
		rename($counter_file, $next_file);
//		echo "*** file (counter) found in work dir\n";
		echo "*** next counter = ".$next_file."\n";
//		echo "*** counter = ".$counter."\n";
	    }

	    //
	    // Job operation
	    //
	    $timestamp = date("Hi");

	    if (array_key_exists("tabC",$jobTable)) {
		echo "*** tabC section found\n";
		if ($timestamp =="0000") {
			include_once "tabulateCamLog.php";
			tabulateCamLog(TRUE);
			syslog(LOG_DEBUG, "eo_tabC"); ////
		}
	    }
	
	    if (array_key_exists("tabS_m",$jobTable)) {
		echo "*** tabS_m section found\n";
		if ($timestamp =="0000") {
			include_once "tabulateSensLog.php";
			tabulateSensLog(TRUE);
			syslog(LOG_DEBUG, "eo_tabS_m"); ////
		}
	    }

	    if (array_key_exists("tabS_b",$jobTable)) {
		echo "*** tabS_b section found\n";
		if ($timestamp =="0000") {
			include_once "tabulateSensLog2.php";
			tabulateSensLog2(TRUE);
			syslog(LOG_DEBUG, "eo_tabS_b"); ////
		}
	    }

	    if (array_key_exists("cam",$jobTable)) {
		echo "*** cam section found\n";
		if ($counter % $jobTable["cam"][0] == $jobTable["cam"][1]) {
			include_once 'captureImage.php';
			captureImage();
			syslog(LOG_DEBUG, "eo_cam"); ////
		}
	    }

	    if (array_key_exists("rcam",$jobTable)) {
		echo "*** rcam section found\n";
		if ($counter % $jobTable["rcam"][0] == $jobTable["rcam"][1]) {
			include_once 'r_captureImage.php';
			r_captureImage();
			syslog(LOG_DEBUG, "eo_rcam"); ////
		}
	    }

	    if (array_key_exists("sens",$jobTable)) {
		echo "*** sens section found\n";
		if ($counter % $jobTable["sens"][0] == $jobTable["sens"][1]) {
			include_once "recordSensData.php";
			recordSensData();
			syslog(LOG_DEBUG, "eo_sens"); ////
		}
	    }
	
	    if (array_key_exists("sens2", $jobTable)) {
		echo "*** sens2 section found\n";
		if ($counter % $jobTable["sens2"][0] == $jobTable["sens2"][1]) {
			include_once "recordSensData2.php";
			recordSensData2();
		}
	    }

	    if (array_key_exists("recC",$jobTable)) {
		echo "*** recC section found\n";
		if ($counter % $jobTable['recC'][0] == $jobTable["recC"][1]) {
			include_once "tabulateCamLog.php";
			tabulateCamLog(FALSE);
			syslog(LOG_DEBUG, "eo_recC"); ////
		}
	    }
	
	    if (array_key_exists("recS_m",$jobTable)) {
		echo "*** recS_m section found\n";
		if ($counter % $jobTable['recS_m'][0] == $jobTable["recS_m"][1]) {
			include_once "tabulateSensLog.php";
			tabulateSensLog(FALSE);
			syslog(LOG_DEBUG, "eo_recS_m"); ////
		}
	    }
	
	    if (array_key_exists("recS_b",$jobTable)) {
		echo "*** recS_b section found\n";
		if ($counter % $jobTable['recS_b'][0] == $jobTable["recS_b"][1]) {
			include_once "tabulateSensLog2.php";
			tabulateSensLog2(FALSE);
			syslog(LOG_DEBUG, "eo_recS_b"); ////
		}
	    }
	
	    if (array_key_exists("spool_sync",$jobTable)) {
		echo "*** spool_sync section found\n";
		if ($counter % $jobTable['spool_sync'][0] == $jobTable["spool_sync"][1]) {
			include_once "spool_sync.php";
			spool_sync();
			syslog(LOG_DEBUG, "eo_spool_sync"); ////
		}
	    }
	
	    if (array_key_exists("bkup",$jobTable)) {
		echo "*** bkup section found\n";
		echo "*** bkup value = ".$jobTable["bkup"]."\n";
		if ($counter % $jobTable["bkup"][0] == $jobTable["bkup"][1]) {
		//	include_once "backupLogs.php";
		//	backupLogs();
		}
	    }

	    if (array_key_exists("drive_connector",$jobTable)) {
		echo "*** drive_connector section found\n";
		if ($counter % $jobTable["drive_connector"][0] == $jobTable["drive_connector"][1]) {
		    include "drive_connector.php";
		    syslog(LOG_DEBUG, "eo_drive_connector"); ////
		}
	    }

        } catch (Exception $e) {
		echo $e->getMessage();
        }

 ?>

