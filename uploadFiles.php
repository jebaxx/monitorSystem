<?php

    include_once "driveLibraryV3.php";

    //
    // Upload all files in the spool directory to a google drive
    //
    //	file name	:	destination
    //	- - - - - - - - - - - - - - - - - - - - - - - - - - - - -
    //	ccam_*.jpg	:	auto capture directory
    //				embed the description data
    //	tn_*.jpg	:	thumbnail directory
    //				embed the description data
    //	cmd_*.log	:	remote execution directory
    //
    //	*(other files)	:	log directory
    //
    function uploadFiles($service) {

	$spool_dir = "/var/www/_spool/";

	$log_folderId =  "0BwEMWPU5Jp9SN0cyM0N6ZWN1Wk0";	// log directory
	$exec_folderId = "0BwEMWPU5Jp9SNExlMFdXdkdEZUE";	// remote execution log directory
	$cam_folderId  = "0BwEMWPU5Jp9SZDNEOEdaeWJrQjA";	// captured image file directory
	$tn_folderId   = "0BwEMWPU5Jp9SVkp5V3Q5bjhxVkE";	// captured image thumbnail file directory

	//
	// Prepare Syslog output
	//
	openlog("uploadFiles", LOG_ODELAY | LOG_PERROR, LOG_USER);

	//
	// Upload captured image files related to imageinfo files
	//
	while (1) {
	    if (($local_inf_file = reset(glob($spool_dir."imginf*.txt"))) == FALSE) break;

	    preg_match("#imginf([0-9][0-9][0-9][0-9]+[0-9-_]+).txt#", $local_inf_file, $d);
	    $timestamp = $d[1];

	    syslog(LOG_DEBUG, "imginf found :" .$timestamp);
	    $handle = fopen($local_inf_file, "r");
	    
	    while (($line = fgets($handle, 1024)) != FALSE) {
	    	$key = strtok($line, "=\n");
	    	$value = strtok("=\n");
	    	$img_var[$key] = $value;
	    }
	    fclose($handle);

	    $description = "distance = ".$img_var['distance']."\n";
	    $description .= "illuminance = ".$img_var['Lux']."\n";
	    $description .= "Exposure data = ".$img_var['ExposureTime']." - ".$img_var['F']." - ".$img_var['ISO'];
	    if (array_key_exists("obj.distance", $img_var)) {
		$description .= "\no.dist = ".$img_var["obj.distance"];
		$description .= " o.avrg = ".$img_var["obj.average"];
		$description .= " o.ratio = ".$img_var["obj.ratio"];
	    }

	    //
	    // Upload captured image file
	    //
	    if (file_exists($local_file = $spool_dir . "ccam_" . $timestamp . ".jpg"))
	    {
		GD_uploadNewFile($local_file, $service, "ccam_".$timestamp.".jpg", $cam_folderId, $description, "image/jpeg");
		unlink($local_file);
	    	syslog(LOG_DEBUG, "upload : ccam_".$timestamp.".jpg");
	    }

	    //
	    // Upload captured image file(2)
	    //
	    if (file_exists($local_file = $spool_dir . "Bcam_" . $timestamp . ".jpg"))
	    {
		GD_uploadNewFile($local_file, $service, "Bcam_".$timestamp.".jpg", $cam_folderId, $description, "image/jpeg");
		unlink($local_file);
	    	syslog(LOG_DEBUG, "upload : Bcam_".$timestamp.".jpg");
	    }

	    //
	    // Upload captured thumbnail file
	    //
	    if (file_exists($local_file = $spool_dir."tn_".$timestamp.".jpg"))
	    {
		GD_uploadNewFile($local_file, $service, "tn_".$timestamp.".jpg", $tn_folderId, $description, "image/jpeg");
		unlink($local_file);
	    	syslog(LOG_DEBUG, "upload : tn_".$timestamp.".jpg");
	    }

	    //
	    // Upload captured thumbnail file(2)
	    //
	    if (file_exists($local_file = $spool_dir."tnb_".$timestamp.".jpg"))
	    {
		GD_uploadNewFile($local_file, $service, "tn_b".$timestamp.".jpg", $tn_folderId, $description, "image/jpeg");
		unlink($local_file);
	    	syslog(LOG_DEBUG, "upload : tn_b".$timestamp.".jpg");
	    }

	    unlink($local_inf_file);
	}

	//
	// Upload remote command log file
	//
	$local_files = glob($spool_dir."cmd_*.log");

	foreach ( $local_files as $local_file) {
	    $pathInfo = pathinfo($local_file);
	    GD_uploadFile($local_file, $service, $pathInfo['basename'], $exec_folderId, "", "text/plain");
	    unlink($local_file);
	    syslog(LOG_DEBUG, "upload cmd : ".$local_file);
	}

	//
	// Upload rest of files in spool
	//
    	$local_files = glob($spool_dir."*");

    	foreach ( $local_files as $local_file) {
    	    $pathInfo = pathinfo($local_file);
    	    $target_file = $pathInfo['basename'];
    	    $extension = $pathInfo['extension'];

	    if ($target_file == 'sens_log2.csv') {
		continue;
	    }

    	    if ($extension == 'jpg') {
    	    	$mimeType = 'image/jpeg';
    	    } else if ($extension == 'html') {
    	    	$mimeType = 'text/html';
    	    } else {
    	    	$mimeType = 'text/plain';
    	    }
    	    GD_uploadFile($local_file, $service, $target_file, $log_folderId, "", $mimeType);
    	    unlink($local_file);
	    syslog(LOG_DEBUG, "upload other : ".$local_file);
    	}

    	closelog();
    }

 ?>
