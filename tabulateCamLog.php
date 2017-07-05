<?php

    function tabulateCamLog($isClear) {

    	$work_dir = "/var/www/work/";
    	$spool_dir = "/var/www/_spool/";
    	$home_dir = "/home/pi/";

	try {
		$input_csv = $work_dir."ccam_log.csv";

		if (($r_hndl = fopen($input_csv, "r")) == FALSE) {
			//
			// camera module did not work.
			//
			return;
		}

		if (($entry = fgetcsv($r_hndl)) == FALSE) {
			//
			// camera module did not work.
			//
			return;
		}

		$ndate0 = strtok(trim($entry[0], "' "), "_");

		while(1) {

			//
			// Create output file
			//
			$w_file = $spool_dir."ccam_".$ndate0.".csv";
			$w_hndl = fopen($w_file, "w");

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
					chmod($w_file, 0664);
					fclose($r_hndl);
					if ($isClear) unlink($input_csv);
					return;
				}

				if (strtok($entry[0], "_") != $ndate0) {
					//
					// the case of date boundary
					//
					fclose($w_hndl);
					chmod($w_file, 0664);
					$ndate0 = strtok($entry[0], "_");
					break;
				}

				fputcsv($w_hndl, $entry);
			}

		}

	}
	catch (Exception $e) {
		echo $e->getMessage();
	}
	
	return;
    }
 ?>
