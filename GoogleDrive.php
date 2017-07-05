<?php
    require_once("/home/pi/projects/vendor/google/apiclient/src/Google/autoload.php");
    require_once("/home/pi/projects/vendor/google/apiclient/src/Google/Service/Drive.php");

    ////////////////////////////////////
    // From CreateGoogleDriveService
    ////////////////////////////////////

    //
    // Create a Google Drive Service instance
    //
    //	input:
    //		Access Token( stored in a {home_dir}/Google_AccessToken file )
    //
    //  output(return value):
    //		service instance
    //
    function GD_createService() {

	$home_dir     = "/var/www/html/monitorSystem/";
	$clientSecret = "TezNOGKS3l6L5chyxP0FOa1R";
	$clientId     = "200873567822-4h2eovivkb2t8is00mdefoero0iqc954.apps.googleusercontent.com";

	openlog("GD_createService", LOG_ODELAY | LOG_PERROR, LOG_USER);

	try {
	    //
	    // Read Token file which was written by the web application
	    //
	    if (($handle = fopen($home_dir."Google_AccessToken", "r")) == FALSE) {
		//
		// If the token file is not exist,
		//   it can be assumed authentication process by the web application had not be completed.
		//
		syslog(LOG_ERR, "cannot open access token file");
		return NULL;
	    }

	    $token = fgets($handle);
	    fclose($handle);

	    // Initialize Google_Client instance
	    $client = new Google_Client();

	    $client->setClientId($clientId);
	    $client->setClientSecret($clientSecret);
	    $client->setRedirectUri('http://localhost/webmonitor_callback.php');
	    $client->setAccessToken($token);

	    // create service instance
	    $service =  new Google_Service_Drive($client);

	    return $service;

	} catch (Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	}

	return $service;
    }

    ////////////////////////////////////
    // From SeaarchDriveFiles
    ////////////////////////////////////

    //
    // Search the google drive and specify a file to match the query condition
    //
    // input:
    //		service: google drive service instance
    //		targetName: file query pattern (begining with match)
    //		parentfolderId: folder id which is resident in the google drive to specify a location of a target file
    //
    // output:
    //		array of Google_Service_Drive_DriveFile instance
    //
    function GD_searchFiles($service, $targetName, $ParentFolderId) {

	try {
	    $query = "('".$ParentFolderId."' in parents) and (title = '".$targetName."')";
	    $parameters = array('q' => $query , 'orderBy' => 'title' , 'pageToken' => null);

	    $files = $service->files->listFiles($parameters);

	} catch (Google_Exception $e) {
	    return null;
	}

	return $files->getItems();
    }

    ////////////////////////////////////
    // From downloa_GoogleDriveFile
    ////////////////////////////////////

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

	$fileList = GD_searchFiles($service, $targetName, $folderId);

	if (($drv_file = reset($fileList)) == FALSE) {
	    syslog(LOG_ERR, "Target file does not exist.");
	    return NULL;
	}

	$downloadUrl = $drv_file->getDownloadUrl();

	if (!$downloadUrl) {
	    syslog(LOG_ERR, "Target file don't have a downloadUrl.");
	    return NULL;
	}

	$request = new Google_Http_Request($downloadUrl, 'GET', null, null);
	$httpRequest = $service->getClient()->getAuth()->authenticatedRequest($request);

	if ($httpRequest->getResponseHttpCode() == 200) {
	    return $httpRequest->getResponseBody();
	}
	else
	{
	    syslog(LOG_ERR, "HTTP responce error.");
	    return NULL;
	}
    }

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

    ////////////////////////////////////
    // From update_GoogleDriveFile
    ////////////////////////////////////

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

	if (filesize($localFile) == 0) {
	    syslog(LOG_DEBUG, "local file size is Zero.");
	    return;
	}

	try {
	    $fileList = GD_searchFiles($service, $targetName, $folderId);

	    if (($drv_file = reset($fileList)) == FALSE) {
		//
		// If target file does not found in the Google drive,
		//    create a new file. 
		//
		GD_uploadNewFile($localFile, $service, $targetName, $folderId, $description, $mimeType);
		return;
	    }

	    //
	    // target file is aleady exist. Do update.
	    //
	    //syslog(LOG_DEBUG, "update start : ".$drv_file->getTitle());
	    $client = $service->getClient();
	    $client->setDefer(true);

	    //
	    // set file info
	    //
	    $drv_file->setDescription($description);
	    $drv_file->setMimeType($mimeType);

	    $request = $service->files->update($drv_file->getId(), $drv_file);

	    $chunkSize = 1024 * 256;
	    $media = new Google_Http_MediaFileUpload(
				$client,
				$request,
				$mimeType,
				null,
				true,
				$chunkSize );

	    $media->setFileSize(filesize($localFile));

	    //
	    // upload file contents
	    //
	    $handle = fopen($localFile, "rb");

	    for ($status = false; !$status && !feof($handle);) {
			$chunk = fread($handle, $chunkSize);
			$status = $media->nextChunk($chunk);
	    }

	    $client->setDefer(false);
	    fclose($handle);

	} catch (Google_Exception $e) {
	    syslog(LOG_ERR, $e->getMessage());
	}

	return;
    }

    ////////////////////////////////////
    // From upload_to_GoogleDrive
    ////////////////////////////////////

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

	if (filesize($localFile) == 0) {
	    syslog(LOG_DEBUG, "local file size is Zero.");
	    return;
	}

	try {
		//syslog(LOG_DEBUG, "create new : ".$targetName. " for ".$localFile);
		$client = $service->getClient();
        	$client->setDefer(true);

		$drv_file = new Google_Service_Drive_DriveFile($client);

		//
		// create parent filder instance
		//
		$parent = new Google_Service_Drive_ParentReference();
		$parent->setID($folderId);

		//
		// set file info
		//
		$drv_file->setTitle($targetName);
		$drv_file->setDescription($description);
		$drv_file->setMimeType($mimeType);
		$drv_file->setParents(array($parent));

		$request = $service->files->insert($drv_file);

		//
		// create a new file in destination side
		//
		$chunkSize = 1024 * 256;
		$media = new Google_Http_MediaFileUpload(
				$client,
				$request,
				$mimeType,
				null,
				true,
				$chunkSize );

		$media->setFileSize(filesize($localFile));

		//
		// upload file contents
		//
		$handle = fopen($localFile, "rb");

		for ($status = false; !$status && !feof($handle);) {
			$chunk = fread($handle, $chunkSize);
			$status = $media->nextChunk($chunk);
		}

        	$client->setDefer(false);
		fclose($handle);

	} catch (Google_Exception $e) {
		syslog(LOG_ERR, $e->getMessage());
	}

	return;
    }

 ?>

