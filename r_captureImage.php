<?php
	include_once "captureImage.php";


	function r_captureImage() {

	    $work_dir = "/var/www/work/";
	    $target_opt = "'\\\\192.168.0.50/work' 'ohayou' -U pi -D 'work' -c ";

	    //
	    // Get illuminance information before capturing
	    //
	    $param = $target_opt."'lcd /var/www/work; get illuminance.txt'";
	    exec('smbclient '.$param);

	    //
	    // Capture function
	    //
	    captureImage();

	    //
	    // Send files to upload server
	    //

	    // work directory

	    // imageinfo.txt
	    $param = $target_opt."'lcd ".$work_dir."; put imageinfo.txt'";
	    echo 'param = [[['.$param."]]]\n";
	    exec('smbclient '.$param);

	    // ccam_log.csv
	    $param = $target_opt."'lcd ".$work_dir."; put ccam_log.csv'";
	    echo 'param = [[['.$param."]]]\n";
	    exec('smbclient '.$param);

	    // ccam_*.jpg
	    foreach (glob($work_dir."ccam_*.jpg") as $pictfile) {
		$pictfile = basename($pictfile);
		$param = $target_opt."'lcd ".$work_dir."; put ".$pictfile."'";
		echo 'jpeg file = [[['.$param."]]]\n";
		exec('smbclient '.$param);
		unlink($work_dir.$pictfile);
	    }
	}
 ?>
