#!/bin/bash
umask 002
export PATH=/usr/local/bin:$PATH:$HOME/bin
if [ "x$1" != "x" ]; then
	DIGITS="$1"
else
	DIGITS="0-9"
fi
HOMEDIR=/opt/webcam/tl
lockfile-create --use-pid --retry 0 /dev/shm/iphonempeg-processing$DIGITS || exit 1
MOVIEDEST=/opt/webcam/outbox
mkdir -p $MOVIEDEST
cd $HOMEDIR
# cleanout leftover tempfiles overlasting oneday
#find . -xdev -name \*_temp -mtime +1 -exec rm {} \;

now=`date +%Y/%m/%d/%H`
today=`date +%Y-%m-%d`
shopt -s nullglob
echo looping....
cd $HOMEDIR
for f in */*/*/{[0-9][$DIGITS],fullday}; do
	echo ">>>" cd $HOMEDIR
	cd $HOMEDIR
	echo $now == $f:
	if [ "$f" != "$now" ]; then
		year=${f:0:4}
		month=${f:5:2}
		day=${f:8:2}
		hour=${f:11:2}
		movie=$year$month${day}_$hour
		# are we trying to process today's full day timelapse?
		if [ "$hour" = "fu" -a "$year-$month-$day" = "$today" ]; then
			# yes - move along here, nothing to see
			continue
		fi
		cd $HOMEDIR/$f
		if [ ! -f $MOVIEDEST/$movie.mp4 ]; then
			jpgs=`echo 20*_???.jpg`
			echo $jpgs
			if [ "$jpgs" = "" ]; then
				echo skipping...
				continue
			fi
			alljpgs=`echo *.jpg`
			if [ "$jpgs" != "$alljpgs" ]; then
				echo ==== extra files present, skipping ===
				echo pwd: `pwd`
				continue
			fi
			echo we are in...
			# mjpeg format
			# workflow:
			#      *.jpg => 
			#      /tmp/movie.mjpg => 
			#      /tmp/movie.mp4 =>
			#      movie.mp4

			# *.jpg => /tmp/movie.mjpg
			cat $jpgs > $MOVIEDEST/$movie.mjpg

			# /tmp/movie.mjpg => /tmp/movie.mp4
		        ffmpeg -f mjpeg -i "$MOVIEDEST/$movie.mjpg" -s 1920x1080 -q:v 0 -y -vcodec mjpeg "$MOVIEDEST/$movie.mp4"

			if [ "$?" == "0" ]; then
				echo -n ""
			else
				echo "*** FFMPEG ERROR processing >$movie< ***"
				echo "pwd: `pwd`"
				#rm -f $movie.mp4
				sleep 5
			fi
		fi

		# temporarily disabled until filesystem permissions issues are resolved
		# if [ -f "$MOVIEDEST/$movie.mp4" ]; then
	# 		#mkdir -p ../../../../junkcam
	# 		#mv *.jpg ../../../../junkcam
	# 		if [ "$hour" != "fu" ]; then
	# 			# every 1 second of movie extract for fullday
	# 			mkdir -p ../fullday
	# 			ffmpeg -f mp4 -i $MOVIEDEST/$movie.mp4 -vf fps=fps=1 ../fullday/$movie%04d_000.jpg
	# 		fi
	# 		# possibly delete or trash jpgs
	# 		#mv *.jpg /opt/webcam/trash
	# 	fi
		cd $HOMEDIR
	fi
done


lockfile-remove /dev/shm/iphonempeg-processing$DIGITS
