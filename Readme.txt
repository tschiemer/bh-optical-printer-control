

1. Problems when freshly started up computer
--------------------------------------------------------------------------------------

If - after startup - the programmer does not work because it receices a long
long invalid return message (nothing intelligable really), then proceed as follows:

(usb cable must be attached)

1. open terminal
2. type screen /dev/ttyUSB0 9600
	a black screen is shown
3. type Ctrl+a, then k, then y (when some question comes up)

Now it should work.






2. Programming Rules
---------------------------------------------------------------------------------------

Programmes consist of sequences of cue-commands. each programme is stored in a single
text file (NOT rich text or word file, very primitive text) with no additional contents.

The command sequences are structured such that there is a command on every line, such as

command1
command2
command3
etc

Commands can set the brightness of the three color components Red-Green-Blue with values
from 0 to 31 where 0 is the dimmest and 31 the brightest setting. This can be done in two ways,
eg going from dimmest up,

r0g0b0
r1g1b1
r2g2b2
r3g3b3
...
r31g31b31

or slightly differently formatted as follows

0 0 0
1 1 1
2 2 2
3 3 3
...
31 31 31

Of course the three values don't have to be the same, they could be any combination really.
But, for the case that you want to set all to the same intensity (maybe you are working on
b/w film?), it suffices to write only one value per line, as follows

0
1
2
3
...
31


        IMPORTANT DANGER IMPORTANT DANGER IMPORTANT DANGER
The first cue command MUST ALWAYS be 0!!! Any cues before the first 0 are basically ignored.

