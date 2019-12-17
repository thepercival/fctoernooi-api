<?php
$file = "./planninginput.sql";
$fh = fopen($file,'r+');

// string to put username and passwords
$lines = '';

while(!feof($fh)) {

    $line = fgets($fh);
    // echo $line;
    $line = str_replace("(id,","(", $line);
    $pos = strpos( $line, "VALUES (");
    $line = substr( $line, 0, $pos + 8 ) . substr( $line, $pos + 14 );

    $lines .= $line;
    // $lines .= "\r\n";
}

fclose($fh);

// using file_put_contents() instead of fwrite()
file_put_contents($file."2", $lines);

