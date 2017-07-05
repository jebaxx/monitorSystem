<?php

    //
    //  ** create Sensor log summary CSV file **
    //
    //  function envSummarize(
    //			$condition,	// control a range of sensor log data to update
    //			$input_file,	// this summary data file is merged to output
    //			$output_file	// output summary data(this can be same as the $input_file)
    //		  )
    //
    //

    function envSummarize($condition, $input, $output) {
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

	    if (($service = GD_createService()) == NULL) {
		echo "No authentication information\n";
		return(5);
	    }
 
	    $query = "('".$log_folderId."' in parents) and (name contains '".$condition."')";
	    $nextToken = null;

	    do {
		$parameters = array('q' => $query , 'orderBy' => 'name' , 'pageToken' => $nextToken);
		$result = $service->files->listFiles($parameters);

		foreach( $result->getFiles() as $file) {

		    echo 'target = '.$file->name." <BR>\n";
		    $contents = GD_downloadFile($service, $file->name, $log_folderId);
		    unset($entry);
		    $entry = array();
		    $i = 0;
		    preg_match("@Record of.*([0-9][0-9][0-9][0-9]/[0-9][0-9]/[0-9][0-9])@", $contents, $d, PREG_OFFSET_CAPTURE);
		    if (!array_key_exists(0, $d)) {
			echo "There is no DateTime recoed!\n";
			continue;
		    }
		    $date   = $d[1][0];
		    $offset = $d[1][1];
		    $entry[$i++] = $date;
		    preg_match("@tmp_A.*min =\s*([0-9\.]+).*max =\s*([0-9\.]+).*average =\s*([0-9\.]+)@", $contents, $d, 0, $offset);
		    $entry[$i++] = $d[1];
		    $entry[$i++] = $d[2];
		    $entry[$i++] = $d[3];
		    preg_match("@tmp_B.*min =\s*([0-9\.]+).*max =\s*([0-9\.]+).*average =\s*([0-9\.]+)@", $contents, $d, 0, $offset);
		    $entry[$i++] = $d[1];
		    $entry[$i++] = $d[2];
		    $entry[$i++] = $d[3];
		    preg_match("@tmp_S.*min =\s*([0-9\.]+).*max =\s*([0-9\.]+).*average =\s*([0-9\.]+)@", $contents, $d, 0, $offset);
		    $entry[$i++] = $d[1];
		    $entry[$i++] = $d[2];
		    $entry[$i++] = $d[3];
		    preg_match("@p[\s]+: min =\s*([0-9\.]+).*max =\s*([0-9\.]+).*average =\s*([0-9\.]+)@", $contents, $d, 0, $offset);
		    $entry[$i++] = $d[1];
		    $entry[$i++] = $d[2];
		    $entry[$i++] = $d[3];

		    $out_data[$date] = $entry;
		}

	    } while (($nextToken = $result->nextPageToken) != null);

	} catch (Google_Exception $e) {
	    echo $e->getMessage();
	}

	//
	//  Sort the output data
	//
	ksort($out_data);

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

