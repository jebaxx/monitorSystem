<?php
//    recordSensData();

    function PostToGAE($sensData)
    {
	date_default_timezone_set("Asia/Tokyo");

	$postData["sensData[timestamp]"] = date(DATE_ATOM);

	foreach ($sensData as $sensorName => $value) {
	    $postData["sensData[".$sensorName."]"] = $value;
	}

	$param = [
		CURLOPT_URL => "http://jebaxxmonitor.appspot.com/postData",
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_POST => TRUE,
		CURLOPT_POSTFIELDS => http_build_query($postData),
	];

	$cc = curl_init();
	if (!(curl_setopt_array($cc, $param))) {
		syslog(LOG_DEBUG, "curl_setopt_array");
	}

	if (!($response = curl_exec($cc))) {
		syslog(LOG_DEBUG, "curl_exec error >>>".$response);
	}
	curl_close($cc);
    }

    function recordSensData2() {

    	$home_dir = "/home/pi/projects/";
    	$spool_dir = "/var/www/_spool/";

	openlog("recordSensData2", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
		$timestamp = "'".date("Y/m/d")."'";
		$time = "'".date("H:i")."'";
		$val = str_getcsv(exec("python ".$home_dir."measureSenserData/MeasureSenserData.py"));
		$tmpS = trim($val[0]);
		$tmpA = trim($val[1]);
		$tmpB = trim($val[2]);
		$hum  = trim($val[3]);
		$ilm  = trim($val[4]);

		// create sensData to upload senser record
		//
		$sensData['T-cpu-boco'] = $tmpS;
		$sensData['T-ADT7410-02'] = $tmpA;
//		$sensData['T-AM2320-01'] = $tmpB;
//		$sensData['H-AM2320-01'] = $hum;
		$sensData['I-BH1750FV1-01'] = $ilm;
		PostToGAE($sensData);
		//////////////////////////////////////////

		$line = $timestamp.', '.$time.',';
		$line .= sprintf("%6.2f, ", $tmpS);
		$line .= sprintf("%8.4f, ", $tmpA);
		$line .= sprintf("%5.1f, ", $tmpB);
		$line .= sprintf("%7.2f, ", $hum);
		$line .= sprintf("%8.3f",   $ilm);

		if ($time == "'00:00'") {
			unlink($spool_dir."sens_log2.csv");
		}
		if ( $handle = fopen($spool_dir."sens_log2.csv", "a") ) {
			fwrite($handle, $line."\n");
			fclose($handle);
		}
		else {
			syslog(LOG_ERROR, "csv file cannot open.");
		}

	}
	catch (Exception $e) {
		echo $e->getMessage();
	}

	return;
    }

 ?>
