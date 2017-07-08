<?php

    function objectCapture() {

    	$work_dir = "/var/www/work/";
    	$spool_dir = "/var/www/_spool/";
    	$home_dir = "/home/pi/projects/python/";

	global $o_distance, $o_average, $o_ratio;

    	echo '<<<'.$o_distance.' '.$o_average.' '.$o_ratio.">>>\n";

	$timestamp = date("Ymd_Hi_s");

	try {
	    //
	    // prepare imageinfo and ihandle, and lock it to detect confliction.
	    //
	    $imageinfo = array();
	    $ihandle = fopen($work_dir."imageinfo.txt", "w");

	    if (isset($o_distance)) $imageinfo['obj.distance'] = $o_distance;
	    if (isset($o_average) ) $imageinfo['obj.average']  = $o_average;
	    if (isset($o_ratio)   ) $imageinfo['obj.ratio']    = $o_ratio;

	    if (!flock($ihandle, LOCK_EX | LOCK_NB)) {
		echo "Detect confliction. stop captureing."; 

		$log_str = $timestamp.',    .   , x,  /  , F . ,    ,       ,  .       ';
		$handle = fopen($work_dir."ccam_log.csv", "a") ;
		fwrite($handle, $log_str);
		fclose($handle);
		fclose($ihandle);

		return;
	    }

	    //
	    // Acquire a illuminance infomation
	    //
	    if (($l_handle = fopen($work_dir."illuminance.txt", "r")) != FALSE) {
		$imageinfo['Lux'] = fgets($l_handle, 100);
		fclose($l_handle);
	    }
	    else {
		$imageinfo['Lux'] = 0;
	    }

	    if ($imageinfo['Lux'] < 4.0) {

		echo "Too dark to capture image!";
		fclose($handle);
		fclose($ihandle);

		return;
	    }

	    //
	    // Execute a camera module
	    //
	    echo "capture start...\n";
	    $imageinfo['timestamp'] = $timestamp;
	    $imageinfo['filename'] = 'Bcam_'.$timestamp.'.jpg';
	    $local_filename = $work_dir.$imageinfo['filename'];
	    $raspistill = '/usr/bin/raspistill';
	    if ($imageinfo['Lux'] != NULL && $imageinfo['Lux'] < 30.0) {
			$capture_opt = ' -n -t 1200 -w 1200 -h 900 -q 65 -o '.$local_filename ;
	    }
	    else {
			$capture_opt = ' -n -t 300 -w 1200 -h 900 -q 65 -o '.$local_filename ;
	    }
	    exec($raspistill.$capture_opt,$output);

	    //
	    // To recognize a cppturing emvironment, parse the Exif info of PICT
	    //
	    $exif = exif_read_data( $local_filename );

	    $val = explode("/", $exif["ExposureTime"] );
	    $imageinfo["ShutterVal"] = $val[1]/$val[0];
	    $imageinfo["ExposureTime"] = sprintf("1/%d", $val[1]/$val[0]);

	    $val = explode("/", $exif["FNumber"] );
	    $imageinfo["FNumber"] = $val[0]/$val[1];
	    $imageinfo["F"] = sprintf("F%.1f", $val[0]/$val[1]);

	    $imageinfo["ISO"] = $exif["ISOSpeedRatings"];

	    //
	    // According to the upload policy, 
	    // decide a value of $should_upload (= whether upload a PICT or not)
	    //
	    $imageinfo["distance"] = 0.0;

	    if ($imageinfo["ShutterVal"] > 5000) {
		//
		// When the camera fails to photometry because of under EV,
		// it shoots the 'darkness' with exposure time 1/5586 and F2.9.
		// In this case, the PICT does not contain any information.
		//
		echo "Bad Capture condition...\n";
		$should_upload = FALSE;
		$should_upload_tn = FALSE;

	    } else if (file_exists($ref_img = $work_dir."ref_img.jpg")) {
		//
		// If Refernce File is exist, check similarity of each PICT.
		//
		$ref_vec = puzzle_fill_cvec_from_file($ref_img);
		$new_vec = puzzle_fill_cvec_from_file($local_filename);
		$imageinfo["distance"] = puzzle_vector_normalized_distance($ref_vec, $new_vec);
		$should_upload = $imageinfo["distance"] > 0.20;
		$should_upload_tn = TRUE;
	    }
	    else {
		$should_upload = TRUE;
		$should_upload_tn = TRUE;
	    }

	    foreach ($imageinfo as $exif_tag => $exif_val) {
	        fwrite($ihandle, $exif_tag.'='.$exif_val."\n");
	    }

	    //
	    // Clean up old image files int the work directory.
	    //
	    foreach (glob($work_dir."Bcam_*.jpg") as $tmpfile) {
		    if ($tmpfile != $local_filename) unlink($tmpfile);
	    }

	    echo "data upload = ".$should_upload."\n";
	    echo "thumnail upload = ".$should_upload_tn."\n";

	    if ($should_upload_tn) {
		//
		// prepare a thumbnail image for update
		//
		$thumb_filename = $spool_dir ."tnb_" . $timestamp . ".jpg";
		list($w,$h) = getimagesize($local_filename);
		$img_src = imagecreatefromjpeg($local_filename);
		$img_dst = imagecreatetruecolor($w/4, $h/4);
		imagecopyresampled($img_dst, $img_src, 0,0,0,0, $w/4, $h/4, $w, $h);
		imagejpeg($img_dst, $thumb_filename);
		chmod($thumb_filename, 0664);
	    }

	    if ($should_upload) {
		copy($local_filename, $spool_dir."Bcam_".$timestamp.'.jpg');
		copy($local_filename, $ref_img);
		chmod($spool_dir.'Bcam_'.$timestamp.'.jpg', 0664);
	    }

	    if ($should_upload || $should_upload_tn)  {
	    	copy($work_dir."imageinfo.txt", $spool_dir."imginf".$timestamp.".txt");
		chmod($spool_dir."imginf".$timestamp.".txt", 0664);
	    }

	    //
	    // Log ( log file = /var/www/work/ccam_log.csv ) 
	    //
	    $uploadFlag = $should_upload ? ', B,' : ', b,';

	    $fileSize = filesize( $local_filename );
	    $log_str = $timestamp;
	    $log_str .= ', '.$imageinfo["Lux"];
	    $log_str .= $uploadFlag.$imageinfo["ExposureTime"];
	    $log_str .=', '.$imageinfo["F"].', '.$imageinfo["ISO"].', '.$fileSize.', '.$imageinfo["distance"]."\n";

	    $handle = fopen($work_dir."ccam_log.csv", "a");
	    fwrite($handle, $log_str);
	    fclose($handle);

	    fclose($ihandle);

	} catch (Exception $e) {
		echo $e->getMessage();
	}

    }

    if (isset($argv)) {
	$o_distance = $argv[1];
	$o_average  = $argv[2];
	$o_ratio    = $argv[3];
    }

    objectCapture();
 ?>
