<?php
    require_once "/home/pi/projects/google_api_v3/vendor/autoload.php";


//
// Content of the "client_secret.json"
//
//{
//   "installed":
//    {
//	"client_id":"323801835452-5gc0hlulqs0v8si4u13nvgc9r9chf0qf.apps.googleusercontent.com",
//	"project_id":"monitorsystem3-167600",
//	"auth_uri":"https://accounts.google.com/o/oauth2/auth",
//	"token_uri":"https://accounts.google.com/o/oauth2/token",
//	"auth_provider_x509_cert_url":"https://www.googleapis.com/oauth2/v1/certs",
//	"client_secret":"tXilGtwXw5ekzaVCopyid9EN",
//	"redirect_uris":
//	  [
//	    "urn:ietf:wg:oauth:2.0:oob",
//	    "http://localhost"
//	  ]
//   }
//} 

//
// Folder's ID
//
// log_folderId =  "0BwEMWPU5Jp9SN0cyM0N6ZWN1Wk0"	#// log directory
// exec_folderId = "0BwEMWPU5Jp9SNExlMFdXdkdEZUE"	#// remote execution log directory
// cam_folderId  = "0BwEMWPU5Jp9SZDNEOEdaeWJrQjA"	#// captured image file directory
// tn_folderId   = "0BwEMWPU5Jp9SVkp5V3Q5bjhxVkE"	#// captured image thumbnail file directory


    //
    // GD_createService()
    //
    // Create a Google Drive Service instance
    //
    //	input:
    //		Access Token( stored in a {home_dir}/projects/google_credentials directory)
    //
    //  output(return value):
    //		service instance
    //
    function GD_createService() {

	openlog("GD_createService", LOG_ODELAY | LOG_PERROR, LOG_USER);

	$projectDir = "/home/pi/projects/";
	$clientSecretPath = $projectDir."google_credentials/client_secret.json";
	$AccessTokenPath  = $projectDir."google_credentials/google_drive-credential.json";

	try {
	    $client = new Google_Client();
	    $client->setScopes("Google_Service_Drive::DRIVE");
	    $client->setAuthConfig($clientSecretPath);
	    $client->setAccessType('offline');

	    if (!file_exists($AccessTokenPath)) {
		syslog(LOG_ERR, "accessToken not found.");
		return null;
	    }

	    if (($AccessToken = json_decode(file_get_contents($AccessTokenPath), true)) == NULL) {
		syslog(LOG_ERR, "AccessToken cannot decode.");
		return null;
	    }

	    $client->setAccessToken($AccessToken);

	    if ($client->isAccessTokenExpired()) {
	    	syslog(LOG_INFO, 'AccessToken is expired. It will be refreshed.');
		$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
		file_put_contents($AccessTokenPath, json_encode($client->getAccessToken()));
	    }

	    $service = new Google_Service_Drive($client);

	} catch (Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	    return NULL;
	}

	return $service;
    }

    //
    // GD_aquireFiles
    //
    // Acquire the google drive files whitch have specified name
    //
    // input:
    //		service: google drive service instance
    //		targetName: name of the file
    //		parentfolderId: folder id which is resident in the google drive to specify a location of a target file
    //
    // output:
    //		array of Google_Service_Drive_DriveFile instance
    //
    function GD_acquireFiles($service, $targetName, $ParentFolderId) {

	openlog("GD_acquireFiles", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
	    $query = "(trashed != true) and ('".$ParentFolderId."' in parents) and (name = '".$targetName."')";
	    $parameters = array('q' => $query , 'orderBy' => 'name' , 'pageToken' => null);

	    $files = $service->files->listFiles($parameters);

	} catch (Google_Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	    return null;
	}

	return $files->getFiles();
    }

    //
    // GD_searchFiles
    //
    // Search google drive files matches to the query condition
    //
    // input:
    //		service: google drive service instance
    //		targetName: file query pattern (prefix matching)
    //		parentfolderId: folder id which is resident in the google drive to specify a location of a target file
    //
    // output:
    //		array of Google_Service_Drive_DriveFile instance
    //
    function GD_searchFiles($service, $targetName, $ParentFolderId) {

	openlog("GD_searchFiles", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
	    $query = "(trashed != true) and ('".$ParentFolderId."' in parents) and (name contains '".$targetName."')";
	    $parameters = array('q' => $query , 'orderBy' => 'name' , 'pageToken' => null);

	    $files = $service->files->listFiles($parameters);

	} catch (Google_Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	    return null;
	}

	return $files->getFiles();
    }

    //
    // GD_downloadFile
    //
    // download a file spesified by $targetName && $folderId using $service
    //
    // input:
    //		service: google drive service instance
    //		targetName: file name to query and retrieve a google drive file
    //		folderId: folder id which is resident in the google drive to specify a location of a target file
    //
    // output(return value):
    //		contents: file content itself
    //
    function GD_downloadFile($service, $targetName, $folderId)
    {
	openlog("GD_downloadFile", LOG_ODELAY | LOG_PERROR, LOG_USER);

	$fileList = GD_acquireFiles($service, $targetName, $folderId);

	if (($drv_file = reset($fileList)) == FALSE) {
	    syslog(LOG_ERR, "Target file does not exist.");
	    return NULL;
	}

	$response = $service->files->get($drv_file->getId(), array('alt'=>'media' ));

	return $response->getBody()->getContents();

    }

    //
    // GD_downloadFile_oneshot
    //
    // download a google drive file spesified by 'targetName' && 'folderId'. 
    // Since service instance is created internally, 'service' parameter not required.
    //
    // input:
    //		targetName: file name to query and retrieve a google drive file
    //		folderId: folder id which is stored in the google drive to specify a location of target file
    //
    // output(return value):
    //		contents: file content itself
    //
    function GD_downloadFile_oneshot($targetName, $folderId)
    {
	openlog("GD_downloadFile_oneshot", LOG_ODELAY | LOG_PERROR, LOG_USER);

	if (($service = GD_createService()) == NULL) {
	    syslog(LOG_ERR, "Cannot create google drive service.");
	    return;
	}

	return GD_downloadFile($service, $targetName, $folderId);
    }

    //
    // GD_uploadFile
    //
    // upload localFile to Google Drive
    // If a same file is exist in the google drive, the file is updated.
    // 
    // input:
    //		localfile:
    //		service: google drive service instance
    //		targetName: file name to query and retrieve a google drive file
    //		folderId:
    //		description:
    //		mimeType:
    //
    // output(return value):
    //		nothing
    //
    function GD_uploadFile($localFile, $service, $targetName, $folderId, $description, $mimeType)
    {
	openlog("GD_uploadFile", LOG_ODELAY | LOG_PERROR, LOG_USER);

	if (!file_exists($localFile)) {
	    syslog(LOG_ERR, "local file isn't exist.");
	    return;
	}

	try {
	    $fileList = GD_acquireFiles($service, $targetName, $folderId);

	    if (($drv_file = reset($fileList)) == FALSE) {
		//
		// If target file does not found in the Google drive,
		//    create a new file. 
		//
		GD_uploadNewFile($localFile, $service, $targetName, $folderId, $description, $mimeType);
		return;
	    }

	    //
	    // set file info
	    //
	    $new_drv_file = new Google_Service_Drive_DriveFile(
				array(	'name' => $targetName,
					'description' => $description
				));

	    $request = $service->files->update($drv_file->getId(), $new_drv_file,
				Array(	'data' => file_get_contents($localFile),
					'mimeType' => $mimeType,
					'uploadType' => 'multipart',
				));

	} catch (Google_Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	}

	return;
    }

    //
    // GD_uploadNewFile
    //
    // upload localFile to Google Drive
    // If a same file is exist in the google drive, new file of same name is created.
    // 
    // input:
    //		localfile:
    //		service: google drive service instance
    //		targetName: file name to query and retrieve a google drive file
    //		folderId:
    //		description:
    //		mimeType:
    //
    // output(return value):
    //		nothing
    //
    function GD_uploadNewFile($localFile, $service, $targetName, $folderId, $description, $mimeType)
    {
	openlog("GD_uploadNewFile", LOG_ODELAY | LOG_PERROR, LOG_USER);

	if (!file_exists($localFile)) {
	    syslog(LOG_ERR, "local file isn't exist.");
	    return;
	}

	try {
	    $drv_file = new Google_Service_Drive_DriveFile(
				array(	'name' => $targetName,
					'parents' => array($folderId),
					'description' => $description
				));

	    $request = $service->files->create($drv_file, 
				array(	'data' => file_get_contents($localFile),
					'mimeType' => $mimeType,
					'uploadType' => 'multipart',
				));

	} catch (Google_Exception $e) {
		syslog(LOG_ERR, $e->getMessage());
	}

	return;
    }

    //
    // GD_renameFile()
    //
    // rename the Google Drive File
    // 
    // input:
    //		service: google drive service instance
    //		folderId:
    //		oldName: file name of the target file
    //		newName: New file name
    //
    // output(return value):
    //		nothing
    //
    function GD_renameFile($service, $folderId, $oldName, $newName)
    {
	openlog("GD_renameFile", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
	    $fileList = GD_acquireFiles($service, $oldName, $folderId);

	    if (($drv_file = reset($fileList)) == FALSE) {
		syslog(LOG_ERR, "(rename) No such File.");
		return;
	    }

	    //
	    // set file name
	    //
	    $new_drv_file = new Google_Service_Drive_DriveFile( array('name' => $newName) );
	    $request = $service->files->update($drv_file->getId(), $new_drv_file, Array('uploadType' => 'multipart') );

	} catch (Google_Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	}

	return;
    }


 ?>

