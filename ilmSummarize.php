<?php

    //
    //  ** create Sensor log summary CSV file **
    //
    //  function ilmSummarize(
    //			$condition,	// control a range of sensor log data to update
    //			$input_file,	// this summary data file is merged to output
    //			$output_file	// output summary data(this can be same as the $input_file)
    //		  )
    //
    //


    function ilmSummarize($condition, $input, $output) {
	$home_dir	= "/var/www/html/monitorSystem/";
	$log_folderId	= "0BwEMWPU5Jp9SN0cyM0N6ZWN1Wk0";        // log folder ID of Google Drive 

	//
	//  read input_file to be updated
	//
	if (($handle = fopen($input, "r")) != FALSE) {

	    while (($in_data = fgetcsv($handle)) != FALSE) {
		$out_data[$in_data[0]] = $in_data;
	    }

	    fclose($handle);
	}

	//
	//  merge input and new data from Google drive
	//
	try {
	    include $home_dir."driveLibraryV3.php";

	    if (($service = GD_createService()) == null) {
		echo "No authentication information\n";
		return(5);
	    }
 
	    $query = "('".$log_folderId."' in parents) and (name contains '".$condition."')";
	    $nextToken = null;

	    do {
		$parameters = array('q' => $query , 'orderBy' => 'name' , 'pageToken' => $nextToken);
		$result = $service->files->listFiles($parameters);

		echo count($result->getFiles());
		foreach( $result->getFiles() as $file) {

		    echo 'name = '.$file->name." <BR>\n";

		    $content = GD_downloadFile($service, $file->name, $log_folderId);

		    preg_match_all("/\[([0-9,]+)\],\s*[0-9\.]+,\s*[0-9\.]+,\s*([0-9\.]+)/", $content, $matches, PREG_SET_ORDER);
		    preg_match("/Record of the day \('([0-9\/]+)'\)/", $content, $ndate);

//		    echo '*** datetime = '.$ndate." <BR>\n";
//		    echo '*** first data = '.$matches[0][0].' : '.$matches[0][2]." <BR>\n";

		    //
		    // $prev  : To calculate the DELTA, accumulate a ratest value of visible range illuminance in $prev.
		    // $prev2 : accumulate a visible range illuminance value just before steep increase.
		    // $md    : 'TRUE' A steep increase data has been detected and keeping the level. This means the artificial lighting is used.
		    //          'FALSE' Normal status
		    // $peek  : If $md == 'TRUE' $peek indicate the steeply rising value of the illuminance.
		    // $nml   : an array of visible range illuminance data whitch is eliminated effect from artificial light.
		    // $level : an array of datetime and levelized visible range illuminance data.
		    // $matches : an array of datetime and illuminance data of visivle range.
		    //			data format of $matches[n][0] is 'yy,mm,dd'
		    //			data format of $matches[n][1] is fixed-point decimal number(3 decimal places)
		    //
		    unset($nml);
		    $prev = $prev2 = 0.0;
		    $md = FALSE;
		    for ($i = 0; $i < count($matches); $i++) {
		        $match = $matches[$i];
		        $d = $match[2] - $prev;

		        if ($d > 40) {
			    $md = TRUE;
			    $peek = $match[2];
		        }
		        if ($d < -40) $md = FALSE;
		        if ($md && (abs($match[2] - $peek) > 17) && $prev2 > 5.0) $md = FALSE;
		        if ($md) {
			    $prev = $match[2];
			    $nml[$i] = $prev2;
		        } else {
			    $prev = $prev2 = $nml[$i] = $match[2];
		        }
		    }

		    unset($level);

		    for ($i = 3; $i < count($nml)-3; $i++) {
			$level[$i-3][0] = $matches[$i][1];
			$level[$i-3][1] = ($nml[$i-3]*0.4+$nml[$i-2]*0.8+$nml[$i-1]*1.2+$nml[$i]*2+$nml[$i+1]*1.2+$nml[$i+2]*0.8+$nml[$i+3]*0.4) / 6.8;
		    }
//------------------------------------------------------
		    $max_v = 0;
		    $max_t = "";

		    for ($i = 0; $i < count($level); $i++) {
			if ($level[$i][1] > $max_v) {
			    $max_t = $level[$i][0];
			    $max_v = $level[$i][1];
//			    echo "max_t = ".$max_t." : max_v = ".$max_v."<br>\n";
			}
		    }

//------------------------------------------------------
		    $out_data[$ndate[1]] = array($ndate[1] , $max_t , $max_v);

		}

	    } while (($nextToken = $result->nextPageToken) != null);

	} catch (Google_Exception $e) {
	    echo $e->getMessage();
	}

	//
	//  Sort the output data
	//
	ksort($out_data);

//	print_r($out_data);
	//
	//  write the result data to output file
	//
	if (($handle = fopen($output, "w")) == FALSE) {
	    echo "file cannot open for write [".$output."]\n";
	    return(6);
	}

	foreach($out_data as $out_entry) {
	    fputcsv($handle, $out_entry);
	}

	fclose($handle);

	return(0);
    }

?>

