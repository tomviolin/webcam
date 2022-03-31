#!/bin/bash

for f in `seq 0 9`; do
	screen -d -m  ./webcam-mpeg.sh $f
done


