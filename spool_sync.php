<?php
	function spool_sync() {

	    $spool_dir = "/var/www/_spool/";

	    // _spool directory
	    $target_opt = "'\\\\192.168.0.50/work' 'ohayou' -U pi -D '_spool' -c ";

	    sleep(10);

	    foreach (glob($spool_dir."*") as $targetfile) {
		$targetfile = basename($targetfile);
		$param = $target_opt."'lcd ".$spool_dir."; put ".$targetfile."'";
		echo 'target file = [[['.$param."]]]\n";
		exec('smbclient '.$param);

		if ($targetfile != "sens_log2.csv") {
			unlink($spool_dir.$targetfile);
		}
	    }
	}
 ?>
