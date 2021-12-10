#!/bin/bash
USER=`whoami`
PATH=$PATH:/home/$USER/bin
a=`ps -ef | grep iphonepics.sh | grep -v $$ | wc -l`
if [ "x$a" != "x1" ]; then
	exit 0
fi
#   20130308_191356_626.jpg
picstaken=n
cd ~/iphonepics/staging
find . -mmin +1 -exec mv {} ../staging.hold2 \;
while [ 1 = 1 ]; do

cd ~/iphonepics/staging
savef=
count=0
for f in 20??????_??????_???.jpg; do
	if [ "$f" != "20??????_??????_???.jpg" ]; then
		savef=$f
		year=${f:0:4}
		month=${f:4:2}
		day=${f:6:2}
		hour=${f:9:2}

		mkdir -p ../tl/$year/$month/$day/$hour
		#mv $f ../tl/$year/$month/$day/$hour/$f
		echo ">>" "stamp.php $f > ../tl/$year/$month/$day/$hour/$f"
		stamp.php $f > ../tl/$year/$month/$day/$hour/$f
		# convert $f -auto-gamma ../tl/$year/$month/$day/$hour/$f
		rm $f
		picstaken=y
		count=$(( count + 1 ))
		if [ $count -ge 100 ]; then break; fi
	fi
done
cd ~/iphonepics
if [ "x$savef" != "x" -a -f "tl/$year/$month/$day/$hour/$savef" ]; then
	ln -sf tl/$year/$month/$day/$hour/$savef newlink.jpg
	mv -f newlink.jpg currentlink.jpg
	echo "--newlink--"
fi
sleep 2 
#if [ "$picstaken" = "n" ]; then
#	cd ~/iphonepics
#	./restartupcam.sh
#fi
done


lockfile-remove /dev/shm/iphonepics
