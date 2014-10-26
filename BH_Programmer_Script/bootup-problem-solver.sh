#!/bin/bash

echo
echo Hi, on the following screen press CTRL+a, then press k, and finally press y
echo Then the window should disappear.
echo
echo "Hit enter when you're ready"
read

screen /dev/ttyUSB0 9600
