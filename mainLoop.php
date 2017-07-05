<html>
<head>
<?php
    try {
////////////////////////////////////////////////
	$work_dir = "/var/www/work/";
    	$home_dir = "/var/www/html/monitorSystem/";
	$def_file_home = $home_dir.".jobTable";
	$def_file = $work_dir.".jobTable";
	if (($handle = fopen($def_file, "r")) == null) {
	    echo "*** file (.jobTable) not found\n";
	    copy($def_file_home, $def_file);
	    $handle = fopen($def_file, "r");
	}

	while ($cols = fgetcsv($handle)) {
	    if (trim($cols[0]) == "mainloop") {
	    	$loopInterval = trim($cols[1]);
	    }
	}

	fclose($handle);
	echo '<meta http-equiv="Refresh" content='.$loopInterval.">\n";
////////////////////////////////////////////////
    } catch (Google_Exception $e) {

        echo $e->getMessage();
    }
 ?>

<title>RaspiStill capture example</title>
</head>
<body>
<hr>
<?php
    try {
	require_once("/home/pi/projects/vendor/google/apiclient/src/Google/autoload.php");
	require_once("/home/pi/projects/vendor/google/apiclient/src/Google/Service/Drive.php");

	session_start();
	$client = $_SESSION['client'];

	include_once "execRemoteCommand.php";
	ExecRemoteCmd();

	include_once "uploadFiles.php";
	uploadFiles();

    	if (file_exists($work_dir."imageinfo.txt")) {
	    //
	    // load imageinf.txt file
	    //
	    $handle = fopen($work_dir."imageinfo.txt", "r");

	    $img_var = array();

	    while (($line = fgets($handle, 1024)) != FALSE) {
	        $key = strtok($line, "=\n");
	        $value = strtok("=\n");
	        $img_var[$key] = $value;
	    }

	    fclose($handle);

	    echo "Exposure data : ".$img_var['ExposureTime']." - ".$img_var['F']." - ".$img_var['ISO']."<br>\n";
	    echo "distance : ".$img_var['distance']." -  Illuminance : ".$img_var['Lux']."<br>\n";

	    $linkName = "work/".$img_var['filename'];

	    //
	    // Clean up old image files int the work directory.
	    //
	    foreach (glob("work/ccam_*.jpg") as $tmpfile) {
		if ($tmpfile != $linkName) unlink($tmpfile);
	    }

	    echo '<hr>';
	    echo '<h2>'.$img_var['timestamp'].'</h2>';
//	    echo 'whoami ? - - - '.exec("whoami")."<br>\n";
	    echo '<img border=0 width=800 src="'.$linkName.'">';
	    echo '<br>';
	}
	else {
	    echo '<hr>';
	    echo '<h2>No image.</h2>';
	    echo '<hr>';
	}

    } catch (Google_Exception $e) {

        echo $e->getMessage();
    }
 ?>
</body>
</html>
