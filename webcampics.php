#!/usr/bin/php
<?php
umask(002);
$factor = 2560 / 1920;

function cleanup_junk_files() {
	system("find /opt/webcam/staging -size -50k -size +1 -exec rm {} +");
}

function initialize() {
	global $USER;
	$USER = chop(`whoami`);
	cleanup_junk_files();
}

// file stamping functions
function datestring_from_filename($file)
{
	return (substr($file, 4, 2) . "-" . substr($file, 6, 2) . "-" . substr($file, 0, 4)
		. " " . substr($file, 9, 2) . ":" . substr($file, 11, 2) . ":" . substr($file, 13, 2));
}
function shadowstring($im, $x, $y, $string)
{
	$white = imagecolorallocate($im, 255, 255, 255);
	$black = imagecolorallocate($im, 0, 0, 0);
	$gray  = imagecolorallocate($im, 128, 128, 128);
	$fontsize = 25; //floor(20*$factor);
	imagettftext($im, $fontsize, 0, $x, $y - 10, $black, __DIR__ . "/VeraBd.ttf", $string);
	imagettftext($im, $fontsize, 0, $x - 4, $y - 14, $gray, __DIR__ . "/VeraBd.ttf", $string);
	imagettftext($im, $fontsize, 0, $x - 3, $y - 13, $white, __DIR__ . "/VeraBd.ttf", $string);
}
// 20130321_233301_548.jpg
function stampfile($src_filename, $dest_filename)
{
	echo "STAMPFILE: $src_filename, $dest_filename ===\n";
	$factor = 2560 / 1920;
	$raw_source_image = @imagecreatefromjpeg("staging/$src_filename");
	#$new=imagecreatetruecolor(1920,1080);

	// OLD SD1100 picture extraction
	//$new_image = imagecreatetruecolor(2560, 1440);
	//imagecopyresampled($new,$im,0,0,0,159,2560,1440,3060,1721);
	// new ELPH 180 image extraction
	$new_image=imagecreatetruecolor(2560,1440);
	//
	// order of parameters for reference:
	// imagecopyresampled($dst_image, $src_image, 
	// 	int $dst_x , int $dst_y , 
	// 	int $src_x , int $src_y , 
	// 	int $dst_w , int $dst_h , 
	// 	int $src_w , int $src_h ) 
	$imageparams = json_decode(file_get_contents("/opt/webcam/imageparams.json"));
	print_r($imageparams);
	imagecopyresampled(
		$new_image,
		$raw_source_image,
		$imageparams->dst_x,
		$imageparams->dst_y,
		$imageparams->src_x,
		$imageparams->src_y,
		$imageparams->dst_w,
		$imageparams->dst_h,
		$imageparams->src_w,
		$imageparams->src_h
	);


	//	0,0,         // destination upper left corner
	//	1000,359,     // source upper left corner
	//	2560,1440,   // destination size (w,h)
	//	3060,1721);  // source size (w,h)

	// -- writes the date and time in shadowed
	//    text onto $new_image.
	shadowstring($new_image, 
		1580 * $factor, 
		1060 * $factor, 
		datestring_from_filename($src_filename));
	
	// -- specify where to find logo for watermark
	$overlayfile = "/opt/webcam/sfs-logo-beveled.png";

	// -- report this filename
	echo $overlayfile;

	// -- read the file image content into a
	//    GD image buffer
	$logo_image = imagecreatefrompng($overlayfile);

	// -- turn on alpha blending and alpha saving
	//    for both the webcam and logo image GD buffers.
	imagealphablending($logo_image, true);
	imagesavealpha($logo_image, true);
	imagealphablending($new_image, true);
	imagesavealpha($new_image, true);


	// -- extracting QHD image from ELPH 180
	imagecopyresampled(
		$new_image, $logo_image,	// dest,src image
		10 * $factor, 985 * $factor, // dest  x,y
		0, 0,                    // src x,y
		560 * $factor, 80 * $factor, // dest w,h
		560, 80   // src w,h
	);
	echo "saving $dest_filename...";
	imagejpeg($new_image, $dest_filename, 95);
	imagedestroy($new_image);
	imagedestroy($raw_source_image);
	system("exiftool -TagsFromFile \"staging/$src_filename\" \"-all:all>all:all\" \"$dest_filename\"");
}

function stage_to_tl_name($filename) {
	$year = substr($filename, 0, 4);
	$month = substr($filename, 4, 2);
	$day = substr($filename, 6, 2);
	$hour = substr($filename, 9, 2);
	$stampedfiledir = "tl/$year/$month/$day/$hour";
	$stampedfile = $stampedfiledir . "/$filename";
	return $stampedfile;
}
function process_one_file($filename) {
	print("==process_one_file($filename)\n");
	$stampedfile = stage_to_tl_name($filename);
	$stampedfiledir = dirname($stampedfile);
	@mkdir($stampedfiledir, 0755, true);
	if (!file_exists($stampedfile)) {
		stampfile($filename, $stampedfile);
	}
	echo "unlink($filename)\n";
	unlink("staging/$filename");
	$imglink = "currentlink.jpg";
	if (file_exists($imglink)) {
		$imgtruepath = realpath($imglink);
		$linkname = basename($imgtruepath);
		$thisname = basename($stampedfile);
		echo "$thisname > $linkname ?\n";
		if ($thisname > $linkname) {
			echo "YES!\n";
			chdir ("/opt/webcam");
			// we are newer!
			// proceed to install link to the file that we just processed
			
			// It is VERY IMPORTANT that the new link
			// be installed using a filesystem rename() call
			// because in many filesystems the 'move' or 'rename'
			// system call is guaranteed to be ATOMIC on the filesystem.
			// This means that no other tasks or threads can mess it up,
			// which is important so that this system can be as threadsafe
			// as possible.

			// -- make sure there's no 'newlink.jpg' left over from a crash
			echo "rm -f newlink.jpg";
			system("rm -f newlink.jpg");

			// -- prepare link command
			$linkresult = symlink($stampedfile, "newlink.jpg");

			// -- if symlink succeeded then
			if ($linkresult !== FALSE) {
				// -- do a likely atomic rename of the newlink.jpg
				// -- over to currentlink.jpg
				$mvresult = rename("newlink.jpg","currentlink.jpg");
			}
		}
	}
}

function lock_files() {
	#   20130308_191356_626.jpg
	if (!file_exists("/dev/shm/iphonepics.pid")) {
		touch("/dev/shm/iphonepics.pid");
	}
	// attempt a lock
	$lockfile = fopen("/dev/shm/iphonepics.pid", "r+");
	if (!flock($lockfile, LOCK_EX | LOCK_NB)) {
		@fclose($lockfile);
		return (1);
	}
	fseek($lockfile, 0);
	fputs($lockfile, getmypid() . "\n\n\n\n");
	fflush($lockfile);
	fflush($lockfile);
	fflush($lockfile);
	$lastfiletime = time();
	return (0);
}

// ====================
//    MAIN CODE 
// ====================

if (lock_files() != 0) {
	exit(1);
}



while (TRUE) {
	sleep(0.1);
	echo ("cd /opt/webcam/staging\n");
	chdir("/opt/webcam");

	// JUST HANDLE MOST RECENT FILE FIRST
	// in "catchup" scenarios it helps current user experience

	chdir("staging");
	$allfiles = glob("20??????_??????_???.jpg");
	chdir("/opt/webcam");
	// -- sort in descending order
	if (count($allfiles)==0) continue;
	rsort($allfiles);
	$the_most_current_file = $allfiles[0];

	print(
		"THIS IS THE MOST RECENT FILE:\n" .
		"$the_most_current_file\n".
		"=============================\n");

	process_one_file($the_most_current_file);

}

exit(0);

/*
	$lastfiletime = time();
	// *** ESTABLISH CURRENT WEBCAM PICTURE **
	//
	$mostrecent = $filenames[0];
	print_r($filenames);
	print("most recent filename: $mostrecent\n");
	chdir("/opt/webcam");
	$tried = FALSE;
	$mrpath = "tl/$year/$month/$day/$hour/$mostrecent";
	if (file_exists($mrpath) && !is_dir($mrpath)) {
		system("rm -f newlink.jpg");
		$command = "ln -sf tl/$year/$month/$day/$hour/$mostrecent newlink.jpg";
		print(">>> $command\n");
		$lnresult = system($command);
		if ($lnresult !== FALSE) {
			$command2 = "mv -f newlink.jpg currentlink.jpg\n";
			print(">>> $command2\n");
			$mvresult = system($command2);
		}
		if ($lnresult !== FALSE && $mvresult !== FALSE) {
			break;
		}
		if ($tried && TRUE) {
			echo "total failure!\n";
			fclose($lockfile);
			exit(9);
		}
		$tried = TRUE;
		system("rm -f currentlink.jpg");
	}

	$last_hour = date("Ymd_H", time() - 60 * 60) . "????_???.jpg";
	echo $last_hour . "\n";
	$fileslast = glob($last_hour);
	rsort($fileslast);
	if (count($fileslast) > 1) {
		$fileslast = [ $fileslast[0] ];
	}
	print("filesthis after rsort:\n");
	print_r($filesthis);
	if (count($filesthis) > 0) {
		$most_recent_file = $filesthis[0];
	}
	/* -- we don't try to restart cam oursevles anymore but here's where it would go :)
		} else {
			if (time() - $lastfiletime  > 15) {
				system("bash ./restartupcam.sh");
				$lastfiletime = time();
			}
		}
		*

	// ** HANDLE CURRENT AND PREVIOUS HOUR for a few **
	// in "catchup" scenarios it helps current user experience
	echo ("cd /opt/webcam/staging\n");
	chdir("/opt/webcam/staging");

	$last_hour = date("Ymd_H") . "????_???.jpg";
	echo $last_hour . "\n";
	$fileslast = glob($last_hour);
	rsort($fileslast);
	echo "nlast = " . count($fileslast) . "\n";
	$this_hour = date("Ymd_H", time() - 60 * 60) . "????_???.jpg";
	echo $this_hour . "\n";
	$filesthis = glob($this_hour);
	rsort($filesthis);
	echo "nthis = " . count($filesthis) . "\n";
	$files = array_merge($fileslast, $filesthis);
	rsort($files);
	//	print_r($files);
	echo "n = " . count($files) . "\n";
	$year = "";
	$month = "";
	$day = "";
	$hour = "";
	$f = "";
	$maxlog = 5;
	foreach ($files as $f) {
		$year = substr($f, 0, 4);
		$month = substr($f, 4, 2);
		$day = substr($f, 6, 2);
		$hour = substr($f, 9, 2);
		echo "mkdir /opt/webcam/tl/$year/$month/$day/$hour\n";
		$stampedfiledir = "/opt/webcam/tl/$year/$month/$day/$hour";
		@mkdir($stampedfiledir, 0755, true);
		$stampedfile = $stampedfiledir . "/$f";
		if (!file_exists($stampedfile)) {
			stampfile($f, $stampedfile);
		}
		echo "unlink($f)\n";
		unlink($f);
		echo ".";
		$lastfiletime = time();
		if (--$maxlog <= 0) break;
	}


	continue;
	// *** NOW HANDLE BACKLOG FOR A FEW ***

	echo ("cd /opt/webcam/staging\n");
	chdir("/opt/webcam/staging");

	$files = (glob("20??????_??????_???.jpg"));
	sort($files);
	//	print_r($files);
	echo "n = " . count($files) . "\n";
	$year = "";
	$month = "";
	$day = "";
	$hour = "";
	$f = "";
	$maxlog = 10;
	foreach ($files as $f) {
		$year = substr($f, 0, 4);
		$month = substr($f, 4, 2);
		$day = substr($f, 6, 2);
		$hour = substr($f, 9, 2);
		echo "mkdir /opt/webcam/tl/$year/$month/$day/$hour\n";
		$stampedfiledir = "/opt/webcam/tl/$year/$month/$day/$hour";
		@mkdir($stampedfiledir, 0755, true);
		$stampedfile = $stampedfiledir . "/$f";
		if (!file_exists($stampedfile)) {
			stampfile($f, $stampedfile);
		}
		echo "unlink($f)\n";
		unlink($f);
		echo ".";
		if (--$maxlog <= 0) break;
	}

	if ($year == "") usleep(50000);
}

 */
