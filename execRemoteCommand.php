<?php

    //
    //  ** Execute remote script file which is downloaded from Gooogle drive **
    //
    //  function ExecRemoteCmd()
    //
    //	<input file>
    //	folder ID : '0BwEMWPU5Jp9SNExlMFdXdkdEZUE
    //	File title : 'command.sh'
    //
    //	<output file>
    //	folder ID : '0BwEMWPU5Jp9SNExlMFdXdkdEZUE
    //	File title : '_cmd_[yyyymmdd_hhmm_ss].log'
    //

    function ExecRemoteCmd($service) {

	openlog("ExecRemoteCmd", LOG_ODELAY | LOG_PERROR, LOG_USER);
	include_once '/home/pi/projects/driveLibrary/driveLibraryV3.php';

	$work_dir = "/var/www/work/";
	$spool_dir = "/var/www/_spool/";
	$parent  = '0BwEMWPU5Jp9SNExlMFdXdkdEZUE';	// remote execution folder ID

	try {
	    $fileList = GD_acquireFiles($service, 'command.sh', $parent);

	    foreach ($fileList as $drv_cmd) {

		syslog(LOG_DEBUG, "command.sh found.");

		$response = $service->files->get($drv_cmd->getId(), array('alt'=>'media'));
		$content = $response->getBody()->getContents();

		if ($content == "") {
		    $content = $drv_cmd->description;
		}

		$scriptFile = "/tmp/command.sh";
		file_put_contents($scriptFile, $content);
		chmod($scriptFile, 0755);

		$out_f_body = "cmd_".date("Ymd_Hi_s");
		$out_local_f = $spool_dir.$out_f_body.".log";
		copy($scriptFile, $out_local_f);
		file_put_contents($out_local_f,  "\n----------\n\n", FILE_APPEND);

		exec("sh /tmp/command.sh >> ".$out_local_f." 2>&1");

		$new_drv_file = new Google_Service_Drive_DriveFile(array('name' => $out_f_body.".sh"));
		$service->files->update($drv_cmd->getId(), $new_drv_file, array('uploadType'=>'multipart'));
	    }
	} catch (Google_Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	}

    }

 ?>

