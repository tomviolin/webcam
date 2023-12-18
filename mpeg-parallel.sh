#!/bin/bash
cd `dirname $0`
for f in 0 1 2 3 4 5 6 7 8 9 ; do
	screen -d -m ./webcam-mpeg-daily.sh $f #>/dev/null 2>&1 &
	sleep .5 # stagger them a bit
done


