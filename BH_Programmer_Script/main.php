<?php

//if (!isset($commandsStr)){
//    die("do not call this file directly. include it instead");
//}

require_once __DIR__ .'/vendor/autoload.php';

use Malenki\Ansi;

/**
 * Configuration
 */

$inverse = TRUE;

$offset = 0;
$range = 32;
$maxCommandCount = 48;

$initialWait = 0.1; // time in sec
$maxWait = 0.3; // time in sec (if an attempt fails, wait time will be doubled for the next round up to this value)


$serialFile = '/dev/ttyUSB0';
$serialBaudrate = 9600;
$serialParity = 'none'; // none, odd, even
$serialCharLength = 8;
$serialStopBits = 1; // 1, 1.5, 2
$serialFlowControl = 'none'; // none, rts/cts, xon/xoff



/**
 *  Basic helper functions
 */


function error($msg,$exitcode=1){
     e("<red>{$msg}</red>\n\n");
    exit($exitcode);
}
function e($msg){
//    $msg = str_replace('&', '', $subject)
    echo Ansi::parse($msg);
}
function a($str){
    return Ansi::parse($str);
}


$filter = function($line, &$error = NULL)use($offset,$range){
    $error = NULL;
    
    static $ended = FALSE;
    
    $line = trim($line);
    
    
    // ignore empty lines
    if (strlen($line) == 0){
        return FALSE;
    }
    
    // if ended already ignore
    if ($ended){
        $error = 'Data is beyond programme ending - ie. there is an end-command somewhere before';
        return FALSE;
    }
    
    if (preg_match('/^(e|end)$/',$line)){
        $ended = TRUE;
        return FALSE;
    }
    
    
    if (preg_match('/^(\d+)$/',$line,$m)){
        $v = $m[0];
        $line = "r{$v}g{$v}b{$v}";
    }
    if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/',$line,$m)){
        list(,$r,$g,$b) = $m;
        $line = "r{$r}g{$g}b${b}";
    }
    if (preg_match('/^r(\d+)g(\d+)b(\d+)$/',$line,$m)){
        list(,$r,$g,$b) = $m;
        $rgb = array($r,$g,$b);
        foreach($rgb as $v){
            $v = intval($v)-$offset;
            if ($v < 0 or $range <= $v){
//                echo ' buhu '.$line." \n" ;
                $min = $offset;
                $max = $offset + $range - 1;
                $error = "final values of {$m[0]} are too low or too high, must be in range of {$min} - {$max}";
                return FALSE;
            }
        }
        return TRUE;
    }
    return FALSE;
};

$correctify = function($line)use($inverse,$offset,$range){
    $line = trim($line);
    
    if (preg_match('/^(e|end)$/',$line)){
        return 'e';
    }
    
    if (preg_match('/^(\d+)$/',$line,$m)){
        $v = $m[0];
        $line = "r{$v}g{$v}b{$v}";
    }
    if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/',$line,$m)){
        list(,$r,$g,$b) = $m;
        $line = "r{$r}g{$g}b${b}";
    }
    if (preg_match('/^r(\d+)g(\d+)b(\d+)$/',$line,$m)){
//        echo " fooo $line\n" ;
        list(,$r,$g,$b) = $m;
        $rgb = array($r,$g,$b);
        foreach($rgb as $i => $v){
            $v = $v - $offset;
            if ($inverse){
                $v = $range - $v - 1;
            }
            $rgb[$i] = $v;
        }
        list($r,$g,$b) = $rgb;
        $line = "r{$r}g{$g}b${b}";
    }
    return $line;
};


/*********************************************************
 * Actual programme start
 */

if ($argc <= 1){
    e("Usage: php {$argv[0]} <italic>path-to-programme-file</italic>\n\n");
    exit;
}

e("\nHi there! Let's have B&amp;H printer <red>fun</red> <green>fun</green> <blue>fun</blue>! <yellow>(^^)</yellow>\n\n");


if (!file_exists($argv[1])){
    error("File {$argv[1]} does not exist.");
}

$commandsStr = file_get_contents($argv[1]);

$commandsStr = trim($commandsStr);

if (empty($commandsStr)){
    error("Error: input file empty!");
}


// prepare message
$commands = preg_split('/(\r\n|\n|\r)/', $commandsStr);

e("<bold>> Validating command sequence..</bold> ");
$errors = array();
$commands = array_filter($commands, function($line)use($filter,&$errors){
    static $lineNr = 0;
    $lineNr++;
    
    $isValid = $filter($line, $error);
    if ($error !== NULL){
echo "$error\n";
        $errors[] = a("Error on line <bold>{$lineNr}</bold> <italic>'{$line}'</italic>: {$error}");
    }
    return $isValid;
});

if (count($commands) > $maxCommandCount){
    echo "\n\n";
    error("Counted ".count($commands)." commands - that' s just too many! Max of {$maxCommandCount}!!");
}

if (count($errors)){
    $msg = "\n\n\t ".implode("\n\t ",$errors)."\n\n";
    echo $msg;
    error("*****   Please fix your command sequence before retrying   *****");
} else {
    e("<green>ok</green>\n");
}


e("<bold>> Transforming commands.. </bold>");
$commands = array_map($correctify, $commands);
e("<green>ok</green>\n");

if (empty($commands)){
    echo "\n";
    error("No commands given!");
}

$commands = array_values($commands); // index cleanup



echo ">> Going to send the following sequence of ".(count($commands)+1)." commands : \n"
    .($offset  ? a("\tNOTE: values are shifted by -{$offset} (eg. {$offset} ~> 0)\n") : '' )
    .($inverse ? a("\tNOTE: values are inversed (greatest becomes least)\n") : '')
    ."\n\t"
    .implode("\n\t",  array_map(function($c){
        return a("<italic>{$c}</italic>");
    }, $commands))
    ."\n\t"
    .a("<italic>e</italic>")
    ."\n\n";


    
    
if (!file_exists($serialFile)){
    error('ERROR: Serial not found. Please make sure it is connected.');
}


/**
 * Now start actual communication
 */

e("<bold>> Setting up serial connection.. </bold>");

// Let's start the class
$serial = new PhpSerial;

// First we must specify the device. This works on both linux and windows (if
// your linux serial device is /dev/ttyS0 for COM1, etc)
$serial->deviceSet($serialFile);

// We can change the baud rate, parity, length, stop bits, flow control
$serial->confBaudRate($serialBaudrate);
$serial->confParity($serialParity);
$serial->confCharacterLength($serialCharLength);
$serial->confStopBits($serialStopBits);
$serial->confFlowControl($serialFlowControl);

// Then we need to open it
if (!$serial->deviceOpen()){
    error('ERROR: Serial found but could not be opened. Mhm mhm, try replugging the serial.');
}


e("<green>ok</green>\n"); // setting up connection: ok

// To write into

$sendAttempt = 0;
$waitTime = $initialWait;

$done = FALSE;
$hasError = FALSE;
$cmdCount = count($commands);
//print_r($commands);


//foreach($commands as $cmd){
//    echo "Sending '{$cmd}':\n";
//    $serial->sendMessage($cmd, 1);
//    $reply = $serial->readPort();
//    echo "$reply\n";
//}
//
//return;

e("<bold><cyan>&lt; Ready to send! </cyan></bold>");
readline(a("<bold><cyan>Prepare machine (push Reset and Load), then hit enter!</cyan></bold> or CTRL-C to abort"));

while(!$done){
    
    $sendAttempt++;
    
    echo ">> Send attempt {$sendAttempt}, send-delay = {$waitTime} sec\n";
   
    // try to read any data that might be there
    //$serial->readPort();

    $hasError = FALSE;
    for($i = 0; !$hasError and $i < $cmdCount; $i++){
        $cmd = $commands[$i];
        $cmdR = $cmd . "\r";
        
        $c = $i + 1;
        echo "\t{$c} / {$cmdCount} ({$cmd}) : ";
          

//	echo strlen($cmdR);
//	echo " - ".$cmdR[0].$cmdR[1]."\n";
	for($c = 0; $c < strlen($cmdR); $c++){
	    $serial->sendMessage($cmdR[$c],0.05);
	}  
        //$serial->sendMessage($cmdR,$waitTime);
        $reply = $serial->readPort();
        
        if (!rand(0,2)){
//            $reply = 'bad';
        }
//        echo "[$reply]\n";
        if ($reply == $cmdR){
            echo "ok\n";
        } else {
            $hasError = TRUE;
            echo("transmission error: received '{$reply}' as echo\n");
            echo "\t " . a("<yellow>aborted attempt</yellow>\n");
        }
    }
    if ($hasError){
        if (2*$waitTime < $maxWait){
            $waitTime *= 2;
        }
        $entry = readline(a("<cyan><bold>&lt; Enter q to quit, to retry just hit enter (on machine push reset and load again): </bold></cyan> "));
        if (strtolower(trim($entry)) == 'q'){
            echo "\n byebye \n";
            exit;
        }
    } else {
        $done = TRUE;
    }
}
if (!$hasError){ // $done is actually equivalent with !$hasError
    echo "\tend of transmission (e): ";
    $serial->sendMessage("e",$waitTime);
    $reply = $serial->readPort();
    if ($reply == 'e'){
        echo "ok\n";
    } else {
        $hasError = TRUE;
        echo "transmission error: got '{$reply}' in return\n";
    }
}

e("<bold>> Closing serial connection</bold>\n");
$serial->deviceClose();

if (!$hasError){
    e("\n<bold><green>Hurray, all should have worked!</green></bold>\n\n");
} else {
    e("\n<bold><red>Sorry, something didn't work out.</red></bold>\n\n");
    exit(1);
}
