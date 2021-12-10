#!/usr/bin/php
<?php
umask(002);
$factor = 2560/1920;
system("find /opt/webcam/staging -size -50k -size +1 -exec rm {} +");

$USER=chop(`whoami`);
echo "user: $USER\n";
// file stamping functions
function shadowstring($im, $x, $y, $string) {
	$white = imagecolorallocate($im, 255,255,255);
	$black = imagecolorallocate($im, 0,0,0);
	$gray  = imagecolorallocate($im, 128,128,128);
	$fontsize = 25; //floor(20*$factor);
	imagettftext($im, $fontsize, 0, $x, $y-10, $black, __DIR__ . "/VeraBd.ttf", $string);
	imagettftext($im, $fontsize, 0, $x-4, $y-14, $gray, __DIR__."/VeraBd.ttf", $string);
	imagettftext($im, $fontsize, 0, $x-3, $y-13, $white, __DIR__."/VeraBd.ttf", $string);
}
// 20130321_233301_548.jpg
function dstr($file) {
	return (substr($file,4,2)."-".substr($file,6,2)."-".substr($file,0,4)
		." ".substr($file,9,2).":".substr($file,11,2).":".substr($file,13,2));
}
function stampfile($src,$dest) {
$factor = 2560/1920;
	$im = @imagecreatefromjpeg($src);
	#$new=imagecreatetruecolor(1920,1080);

	// OLD SD1100 picture extraction
	$new=imagecreatetruecolor(2560,1440);
	//imagecopyresampled($new,$im,0,0,0,159,2560,1440,3060,1721);
	// new ELPH 180 image extraction
	$new=imagecreatetruecolor(2560,1440);
	// this is a really hard to remember order of parameters, so here it is for reference:
	// imagecopyresampled($dst_image, $src_image, 
	// 	int $dst_x , int $dst_y , 
	// 	int $src_x , int $src_y , 
	// 	int $dst_w , int $dst_h , 
	// 	int $src_w , int $src_h ) 
	$ip = json_decode(file_get_contents("/opt/webcam/imageparams.json"));
	print_r($ip);
	imagecopyresampled($new,$im,
		$ip->dst_x,$ip->dst_y,
		$ip->src_x,$ip->src_y,
		$ip->dst_w,$ip->dst_h,
		$ip->src_w,$ip->src_h);
		
		
	//	0,0,         // destination upper left corner
	//	1000,359,     // source upper left corner
	//	2560,1440,   // destination size (w,h)
	//	3060,1721);  // source size (w,h)

	shadowstring($new, 1580*$factor,1060*$factor, dstr($src));
	$overlayfile = $_SERVER['HOME']."/iphonepics/sfs-logo-beveled.png";
	echo $overlayfile;
	$item = imagecreatefrompng($overlayfile);
	imagealphablending($item,true);
	imagesavealpha($item,true);
	imagealphablending($new,true);
	imagesavealpha($new,true);
	// imagecopy($new,$item,10,985,0,0,560,80); //,100);

	// extracting HD image from SD1100 output
	// imagecopyresampled($new,$item,10*$factor,985*$factor,0,0,560*$factor,80*$factor,560,80); //,100);
	// extracting QHD image from ELPH 180
	imagecopyresampled($new,$item,10*$factor,985*$factor,0,0,560*$factor,80*$factor,560,80); //,100);
	echo "saving $dest...";
	imagejpeg($new, $dest, 95);
	imagedestroy($new);
	imagedestroy($im);
	system("exiftool -TagsFromFile \"$src\" \"-all:all>all:all\" \"$dest\"");
	unlink($dest."_original");
}

#   20130308_191356_626.jpg
if (!file_exists("/dev/shm/iphonepics.pid")) {
	touch("/dev/shm/iphonepics.pid");
}
// attempt a lock
$lockfile = fopen("/dev/shm/iphonepics.pid", "r+");
if (!flock($lockfile, LOCK_EX | LOCK_NB)) {
	@fclose($lockfile);
	exit(0);
}
fseek($lockfile,0);
fputs($lockfile, getmypid()."\n\n\n\n");
fflush($lockfile);
fflush($lockfile);
fflush($lockfile);
$lastfiletime = time();
while (true) {
	sleep(0.1);
	echo ("cd /opt/webcam/staging\n");
	chdir("/opt/webcam/staging");

	// JUST HANDLE MOST RECENT FILE FIRST
	// in "catchup" scenarios it helps current user experience

	$last_hour = date("Ymd_H")."????_???.jpg";
	echo $last_hour."\n";
	$fileslast = glob($last_hour);
	$this_hour = date("Ymd_H", time()-60*60)."????_???.jpg";
	echo $this_hour."\n";
	$filesthis = glob($this_hour);
	$files = array_merge($fileslast,$filesthis);
	rsort($files);
//	print_r($files);
	echo "n = ".count($files)."\n";
	$year="";
	$month="";
	$day="";
	$hour="";
	$f="";
	$maxlog = 10;
	foreach ($files as $f) {
		$year=substr($f,0,4);
		$month=substr($f,4,2);
		$day=substr($f,6,2);
		$hour=substr($f,9,2);
		echo "mkdir /opt/webcam/tl/$year/$month/$day/$hour\n";
		$stampedfiledir="/opt/webcam/tl/$year/$month/$day/$hour";
		@mkdir($stampedfiledir,0755,true);
		$stampedfile=$stampedfiledir."/$f";
		if (! file_exists($stampedfile)) {
			stampfile($f, $stampedfile);
		}
		echo "unlink($f)\n";
		unlink($f);
		echo ".";
		$lastfiletime = time();
		if (--$maxlog <= 0) break;
	}

	// *** ESTABLISH CURRENT WEBCAM PICTURE **

	chdir("/opt/webcam");
	if ($year != "") {
		$tried = FALSE;
		while (TRUE) {
			system("rm -f newlink.jpg");
			$lnresult = system ("ln -sf tl/$year/$month/$day/$hour/$f newlink.jpg\n");
			if ($lnresult !== FALSE) $mvresult = system ("mv -f newlink.jpg currentlink.jpg\n");
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


	/* -- we don't try to restart cam oursevles anymore but here's where it would go :)
	} else {
		if (time() - $lastfiletime  > 15) {
			system("bash ./restartupcam.sh");
			$lastfiletime = time();
		}
	*/
	}






	// ** HANDLE CURRENT AND PREVIOUS HOUR for a few **
	// in "catchup" scenarios it helps current user experience
	echo ("cd /opt/webcam/staging\n");
	chdir("/opt/webcam/staging");

	$last_hour = date("Ymd_H")."????_???.jpg";
	echo $last_hour."\n";
	$fileslast = glob($last_hour);
	echo "nlast = ".count($fileslast)."\n";
	$this_hour = date("Ymd_H", time()-60*60)."????_???.jpg";
	echo $this_hour."\n";
	$filesthis = glob($this_hour);
	echo "nthis = ".count($filesthis)."\n";
	$files = array_merge($fileslast,$filesthis);
	rsort($files);
//	print_r($files);
	echo "n = ".count($files)."\n";
	$year="";
	$month="";
	$day="";
	$hour="";
	$f="";
	$maxlog = 5;
	foreach ($files as $f) {
		$year=substr($f,0,4);
		$month=substr($f,4,2);
		$day=substr($f,6,2);
		$hour=substr($f,9,2);
		echo "mkdir /opt/webcam/tl/$year/$month/$day/$hour\n";
		$stampedfiledir="/opt/webcam/tl/$year/$month/$day/$hour";
		@mkdir($stampedfiledir,0755,true);
		$stampedfile=$stampedfiledir."/$f";
		if (! file_exists($stampedfile)) {
			stampfile($f, $stampedfile);
		}
		echo "unlink($f)\n";
		unlink($f);
		echo ".";
		$lastfiletime = time();
		if (--$maxlog <= 0) break;
	}


	//continue;
	// *** NOW HANDLE BACKLOG FOR A FEW ***

	echo ("cd /opt/webcam/staging\n");
	chdir("/opt/webcam/staging");

	$files = (glob("20??????_??????_???.jpg"));
	sort($files);
//	print_r($files);
	echo "n = ".count($files)."\n";
	$year="";
	$month="";
	$day="";
	$hour="";
	$f="";
	$maxlog = 10;
	foreach ($files as $f) {
		$year=substr($f,0,4);
		$month=substr($f,4,2);
		$day=substr($f,6,2);
		$hour=substr($f,9,2);
		echo "mkdir /opt/webcam/tl/$year/$month/$day/$hour\n";
		$stampedfiledir="/opt/webcam/tl/$year/$month/$day/$hour";
		@mkdir($stampedfiledir,0755,true);
		$stampedfile=$stampedfiledir."/$f";
		if (! file_exists($stampedfile)) {
			stampfile($f, $stampedfile);
		}
		echo "unlink($f)\n";
		unlink($f);
		echo ".";
		if (--$maxlog <= 0) break;
	}

	if ($year == "") usleep(50000);
}
