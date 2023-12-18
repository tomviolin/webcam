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
MOVIEDEST2=/opt/webcam/sent
MOVIETRASH=/opt/webcam/trash
mkdir -p $MOVIEDEST
cd $HOMEDIR
# cleanout leftover tempfiles overlasting oneday
#find . -xdev -name \*_temp -mtime +1 -exec rm {} \;

today=`date +%Y/%m/%d`
shopt -s nullglob
echo looping....
cd $HOMEDIR
for f in `ls -1dr 2022/*/[0-9][$DIGITS]`; do
	echo $f
	[ "$f" '<' '2022/06/01' ] && break;
	echo ">>>" cd $HOMEDIR
	cd $HOMEDIR
	year=${f:0:4}
	month=${f:5:2}
	day=${f:8:2}
	movie=$year$month$day
	# are we trying to process today's full day timelapse?
	if [ "$year/$month/$day" == "$today" ]; then
		# yes - move along here, nothing to see
		echo "today-skip"
		continue
	fi
	echo ">>>" cd $HOMEDIR/$f
	cd $HOMEDIR/$f
	if [ ! -f "$MOVIEDEST/$movie.mp4" -a ! -f "$MOVIEDEST2/$movie.mp4" \
	       -a ! -f "$MOVIEDEST/${movie}-uploading.mp4" ]; then
		jpgs=`echo ??/20*_???.jpg`
		#echo $jpgs
		if [ "x$jpgs" == "x" ]; then
			echo skipping...
			continue
		fi
		echo we are in...
		# mjpeg format
		# workflow:
		#      *.jpg => 
		#      _proc_movie.mp4 =>
		#      movie.mp4

		# *.jpg => _proc_movie.mp4
		cat $jpgs | /usr/local/bin/ffmpeg -f jpeg_pipe -i - -s 1920x1080 -c:v libx264 -preset slow "$MOVIEDEST/_proc_$movie.mp4" -y

		if [ "$?" == "0" ]; then
			mv "$MOVIEDEST/_proc_$movie.mp4" "$MOVIEDEST/$movie.mp4"
			echo -n ""
		else
			echo "*** FFMPEG ERROR processing >$movie< ***"
			echo "pwd: `pwd`"
			#rm -f $movie.mp4
			sleep 5
		fi
	fi

	cd $HOMEDIR
done


lockfile-remove /dev/shm/iphonempeg-processing$DIGITS
