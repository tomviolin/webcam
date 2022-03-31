#!/bin/bash

# create symbolic links that are "close enough"
# to the naming scheme on YouTube.
cd /opt/webcam/outbox
for f in 20*.mp4; do 
	echo ln -s $f \"HD_Webcam_Timelapse_${f:4:2}-${f:6:2}-${f:0:4}_${f:9:2}:00-${f:9:2}:59.mp4\"
	ln -s $f "HD_Webcam_Timelapse_${f:4:2}-${f:6:2}-${f:0:4}_${f:9:2}:00-${f:9:2}:59.mp4"
done

