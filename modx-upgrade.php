<?php
require_once 'config.core.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modX();
$modx->initialize('web');
$modx->getService('error','error.modError', '', '');
$version = $modx->getOption('settings_version');
if($_POST){
if(!isset($_POST['vn'])){
	updateStatus('<p class="error">No upgrade version specified</p>');
	exit();
}
ini_set('display_errors','on');
ini_set('memory_limit','2048M');
$versionNumber = $_POST['vn'];
$versionStr = 'modx-'.$versionNumber;
updateStatus('<style>body{font-family:"Trebuchet MS", Helvetica, sans-serif;} .success{color:#009900;} .error{color:#990000;}</style>');
updateStatus('<h3>MODX Upgrade Launcher</h3><p>This script backs up your existing MODX website, zips that backup, grabs a copy of '.$versionStr.', unzips and installs it.</p><p>You then just need to run the setup to complete the upgrade process.</p>');




$core_path = $modx->getOption('core_path');
updateStatus('<p>Clearing cache...<br/>');
clearDir($core_path.'cache/');
updateStatus('<br/>Cache cleared.</p>');

$ignore = array( 'cgi-bin','_build','backup','builds','FirePHPCore','.idea'.'.gitignore'); 
$baseDir = dirname(__FILE__);
$customCore = false;
if($baseDir.'/core/' != $core_path){
	updateStatus('<p>The core folder is in a custom location</p>');
	$customCore = true;
}
$output = '';
$i = 0;
$j = 0;   

ini_set('max_execution_time', 30000); 

function create_zip($source,$destination,$ignoreFile)
{
	if(file_exists($destination)){
		updateStatus('<p>Deleting previous backup file... ');
		unlink($destination);
		updateStatus('Backup zip file deleted</p>');
	}
	if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }
	$zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }
	updateStatus('<p>Zipping up backed up site...<br/>');						
	$source = str_replace('\\', '/', realpath($source));
	$c = 0;
	if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($files as $file)
        {
          	$file = str_replace('\\', '/', realpath($file));
			if (is_dir($file) === true){
            	if($file != $ignoreFile){		
                	$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
               		if($j % 100 == 0){
					updateStatus('*');
					}
					if ($j % 3200 == 0 || $j == 3200){
						if($j >= 3200){	
							updateStatus('<br/>');
						}
					}	
            		$j++;
				}
			}
			else if (is_file($file) === true)
        	{
            		$zip->addFile($file,str_replace($source . '/', '', $file));
        	}
			if($c == 200){
				$zip->close();
				$zip = new ZipArchive();
				$zip->open($destination, ZIPARCHIVE::CREATE);
			}
			$c++;
		}
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}




function doZip($versionStr){
	 global $baseDir;
	 if(!is_dir($baseDir.'/'.$versionStr)){
		mkdir($baseDir.'/'.$versionStr,0777,true);	 
	}
	 try{
     	$zip = new ZipArchive;
	 }
	 catch(exception $e){
	 	$response[0] = 0;
		$response[1] = '<p style="color:#990000">'.$e->message.'</p>';
	 }
	 if(is_file($baseDir.'/backup/'.$versionStr.'.zip')){
		$res = $zip->open($baseDir.'/backup/'.$versionStr.'.zip');
     	if ($res === TRUE) {
			updateStatus('<p>Extracting downloaded zip file...</p>');
        	$zip->extractTo($baseDir);
			$zip->close();
        	$response[0] = 1;
			$response[1] = '<p class="success">Successfully extracted download file</p>';
        } else {
			$response[0] = 0;
			$response[1] = 'Extraction failed - '.$res;
     	} 
     } else {
     	$response = grabFile($versionStr);
		if($response[0] == 1){
			updateStatus($response[1]);
			doZip($versionStr);	
		}else{
			updateStatus($response[1]);
			exit();	
		}
     }
	 return $response;
}

function grabfile($versionStr){
	global $baseDir;
	updateStatus('<p>Downloading upgrade zip... ');
	$upgradeZip = $baseDir.'/backup/'.$versionStr.'.zip';
	
	file_put_contents($upgradeZip, file_get_contents("http://modx.com/download/direct/".$versionStr.".zip"));
	if(!file_exists($upgradeZip)){
		$response[0] = 0;
		$response[1] = '<span class="error">Download of upgrade zip file failed.</span></p>'; 	
	}else{
		$response[0] = 1;
		$response[1] = '<span class="success">Upgrade zip file fetched successfully.</span></p>';	
	}
	return $response;
}

function chmodDirectory( $path,$level){  
  	global $ignore;
  	$dh = @opendir( $path ); 
  	while( false !== ( $file = readdir( $dh ) ) ){ // Loop through the directory 
        	if( !in_array( $file, $ignore ) ){
			if( is_dir( "$path/$file" ) ){
				chmod("$path/$file",0777);
				chmodDirectory( "$path/$file", ($level+1));
	       	 	} else {
				chmod("$path/$file",0777); // desired permission settings
			}//elseif 
		}//if in array 
	}//while 
	closedir( $dh ); 
}

function recurse_copy($src,$dst) {
    global $ignore, $output, $i;	 
    $dir = opendir($src); 
    if(!file_exists($dst)){
		@mkdir($dst); 
	}
	 while(false !== ( $file = readdir($dir)) ) {
    	if(!in_array($file,$ignore)){
        	if (( $file != '.' ) && ( $file != '..' )) {
            	if ( is_dir($src . '/' . $file) ) {
            		if($i % 100 == 0){
						updateStatus('*');
					}
						if ($i % 3200 == 0 || $i == 3200){
							if($i >= 3200){	
								updateStatus('<br/>');
							}
						}
					
            		$i++;
            		recurse_copy($src . '/' . $file,$dst . '/' . $file); 
				} else { 
                	copy($src . '/' . $file,$dst . '/' . $file); 
            	} 
        	}
		} 
    } 
    closedir($dir); 
}

function doBackup($baseDir,$versionStr,$modx){
	global $output,$customCore,$core_path;	
	$bd = false;
	if(!file_exists($baseDir.'/backup/pre_'.$versionStr.'/')){
		$bd = mkdir($baseDir.'/backup/pre_'.$versionStr,0777,true);
	}else{
		$bd = true;
	}
	if($bd){
		include($core_path.'/config/config.inc.php');
		if($database_type == 'mysql'){
			updateStatus('<p>Backing up database...<br/>');
			backupDB($database_server,$dbase,$database_user,$database_password,$database_connection_charset,$database_dsn,$baseDir.'/backup/pre_'.$versionStr.'/');	
		}
		updateStatus('<p>Backing up site root...<br/>');
		recurse_copy($baseDir,$baseDir.'/backup/pre_'.$versionStr);
		if($customCore){
			updateStatus('<br/>Backing up custom core directory...<br/>');
			recurse_copy($core_path,$baseDir.'/backup/pre_'.$versionStr);	
		}
		$output .= '</p><p class="success">Successfully backed up exisitng site files</p>';
		updateStatus($output);
	} else {
		$output .= '</p><p style="color:#990000;">Failed to create backup directory at '.$baseDir.'/'.$versionStr.'</p>';
		updateStatus($output);
		exit();
	}
} 

$starttime = time();
updateStatus('<p>Performing backup, please wait...</p>');
doBackup($baseDir,$versionStr,$modx);
updateStatus('<p>Preparing to zip up backup of existing site...</p>');
$zipped = create_zip($baseDir,$baseDir.'/backup/pre_'.$versionStr.'_upgrade_backup.zip', $baseDir.'/backup');
updateStatus($zipped);
if($zipped){
	$output = '<p style="color:#009900">Backup zip created successfully</p>';
	$response = doZip($versionStr);
	if($response[0] == 1){
		if(is_writable($baseDir)){
		recurse_copy($baseDir.'/'.$versionStr, $baseDir);
		$output = '<p style="color:#009900;">Files moved to '.$baseDir.'</p>';
		}else{
			$output = '<p>We cannot extract to this directory</p>';	
		}
		updateStatus($output);
		updateStatus('<p>Deleting upgrade folder...</p>');
		rrmDir($baseDir.'/'.$versionStr);
		updateStatus('<p>Deleting the backed up site folder...</p>');
		rrmDir($baseDir.'/backup/pre_'.$versionStr);
		updateStatus('<p>Deleting the upgrade file...</p>');
		unlink($baseDir.'/backup/'.$versionStr.'.zip');
		$output = '<p style="color:#009900;">Upgrade folder deleted.</p>';
		updateStatus($output);
		updateStatus('<p><a href="/setup">Proceed to setup</a></p>');
	}else{
		$output .= '<p style="color:#990000">Extraction failed</p>';
		updateStatus($output);
	}
} else {
	$output .= '<p style="color:#990000">Zipping up backup failed.</p>';
	updateStatus($output);
}
$endtime = time();
$completiontime = ceil(($endtime - $starttime) / 60);
updateStatus('<p>Process completed in '.$completiontime.' minutes</p>');
}else{
?>
<style>
body{font-family:"Trebuchet MS", Helvetica, sans-serif;} 
.success{color:#009900;} 
.error{color:#990000;}
</style>
<h3>MODX Upgrade Launcher</h3><p>This script backs up your existing MODX website, zips that backup, grabs a copy of the new version you have specified, unzips and installs it.</p><p>You then just need to run the setup to complete the upgrade process.</p>
<form action="" method="POST">
<label for="vn">MODX Version Number</label><br/>
<span style="font-size:0.7em;color:#999;">Currently running '<?php echo $version; ?>'</span><br/>
<label for="vn">modx-</label><input type="text" name="vn" id="vn" placeholder="2.2.X-pl"/>
<p>
<input type="submit" value="Start"/>
</p>
</form>
<?php
	}


function UpdateStatus($message){
	echo $message;
    ob_flush();
    flush();
}

function rrmdir($dir) { 
   $i = 0;
   if (is_dir($dir)) { 
   	if($i % 100 == 0){
		updateStatus('*');
	}
	if ($i % 2000 == 0 || $i == 2000){
		if($i >= 2000){	
			updateStatus('<br/>');
		}
	}
	$i++;
    $objects = scandir($dir); 
    foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
       } 
     } 
     reset($objects); 
     rmdir($dir); 
   } 
 }


function clearDir($path){
	$files = glob($path.'*'); // get all file names
	foreach($files as $file){ // iterate files
  		if(is_file($file)){
    		unlink($file); // delete file
		}else{
			if(is_dir($file)){
				rrmdir($file);
			}
		}
	}
}

function backupDB($host,$dbase,$user,$pass,$charset,$dsn,$path){
  	$link = mysqli_connect($host,$user,$pass,$dbase);
	mysqli_set_charset($link, $charset);
 	$tables = array();
  	$result = mysqli_query($link,'SHOW TABLES');
  	while($row = mysqli_fetch_row($result))
  	{
  		$tables[] = $row[0];
  	}
  	foreach($tables as $table)
  	{
    	$result = mysqli_query($link,'SELECT * FROM `'.$table.'`');
    	$num_fields = mysqli_field_count($link);
    	$return.= 'DROP TABLE IF EXISTS `'.$table.'`;';
    	$row2 = mysqli_fetch_row(mysqli_query($link,'SHOW CREATE TABLE `'.$table.'`'));
    	$return .= "\n\n".$row2[1].";\n\n";
    	for ($i = 0; $i < $num_fields; $i++){
      		while($row = mysqli_fetch_row($result)){
        		$return .= 'INSERT INTO `'.$table.'` VALUES(';
        		for($j=0; $j < $num_fields; $j++){
          			$row[$j] = addslashes($row[$j]);
          			$row[$j] = preg_replace("/\n/","\\n",$row[$j]);
          			if (isset($row[$j])){ 
						$return .= '"'.$row[$j].'"' ; 
					} else { 
						$return.= '""'; 
					}
          			if ($j<($num_fields-1)){ 
						$return.= ','; 
					}
        		}
        		$return .= ");\n";
      		}
    	}
    	$return .= "\n\n\n";
		updateStatus('*');
  	}
  	$handle = fopen($path.'db-backup-'.date('Y-m-d').'.sql','w+');
  	fwrite($handle,$return);
  	fclose($handle);
	updateStatus('<br/><span class="success">Database backup complete.</span></p>');
}
?> 

