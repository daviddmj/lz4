<?php
/**
 * LZ4 utility
 * @description LZ4 compression / decompression utility
 * @author dafonso@prestaconcept.net
 */

require_once(dirname(__FILE__) . '/classes/lz4.class.php');

$usage = <<<USAGE

              \033[1mLZ4 compression / decompression utility
                              lz4php v0.1

\033[1mOptions\033[0m:
    -h              Display help.
    -i              Input filename or file pattern
    -o              Output filename (used only if a single file was found
                    else will use found names and add or remove compression file extension)
    -c              Compress flag
    -w              Write data to output file(s) (will output decompressed data to console if not specified)
    -d              Display debug information
    --standard      Use standard compression level (high is default)

\033[1mExamples\033[0m:
    \033[1m# read myinputfilename, create myoutputfilename with compressed data.\033[0m
    php lz4php.php -i myinputfilename -o myoutputfilename -w -c

    \033[1m# read myinputfilename, create myoutputfilename with compressed data,
    # use standard compression level (default is high), display debug information\033[0m
    php lz4php.php -i myinputfilename -o myoutputfilename -w -c -d --standard

    \033[1m# read myinputfilename, create myoutputfilename with decompressed data, display debug information\033[0m
    php lz4php.php -i myinputfilename -o myoutputfilename -w -d

    \033[1m# read myinputfilename, output data to console, display debug information\033[0m
    php lz4php.php -i myinputfilename -d


USAGE;

$options = getopt('i:o:chdw', array('standard'));

if (isset($options['h'])) {
    print_r($usage);
    exit(0);
}

if (!isset($options['i'])) {
    die("You must specify an input file or file pattern with -i argument" . PHP_EOL);
}

// Allow pattern search
$files = glob($options['i']);

if (count($files) == 0) {
    die("No input file matching your pattern" . PHP_EOL);
}

try {
    // Create object
    $lz4 = new LZ4();

    if (isset($options['o']) && (count($files) == 1)) {
        $lz4->setOutputFile($options['o']);
    }

    $lz4->setCompressionLevel(isset($options['standard']) ? LZ4::STANDARD_COMPRESSION : LZ4::HIGH_COMPRESSION);
    $lz4->setReportEnabled(isset($options['d']));
    $lz4->setAllowFileOutput(isset($options['w']));

    // Process each file
    foreach ($files as &$file) {
        // Set input file
        $lz4->setInputFile($file);

        // Guess file extension
        $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // Call right method
        if (isset($options['c'])) {
            // Add file extension if not single file to compress
            if ($lz4->getOutputFile() == null) {
                $lz4->setOutputFile($file . '.' . $lz4::FILE_EXT);
            }
            if ($lz4::FILE_EXT !== $fileExt) $lz4->compressFile();
        } else {
            // Remove file extension if not single file to decompress
            if ($lz4->getOutputFile() == null) {
                $lz4->setOutputFile(str_replace('.' . $lz4::FILE_EXT, '', $file));
            }
            if ($lz4::FILE_EXT == $fileExt) $lz4->decompressFile();
        };

        // Output colorized error lines to console
        if (count($lz4->getErrors()) > 0) {
            foreach ($lz4->getErrors() as $error) {
                echo sprintf("\033[0;31m%s\033[0m", $error);
            }
            echo PHP_EOL;
        }

        // Output colorized log lines to console
        if (count($lz4->getLog()) > 0) {
            foreach ($lz4->getLog() as $log) {
                echo sprintf("\033[0;32m%s\033[0m", $log);
            }
            echo PHP_EOL;
        }

        // Display data if decompression mode and no need to write output files
        while ($data = $lz4->readLine()) {
            echo $data;
        }

        // Clear memory
        $lz4->clear();
    }

    unset($lz4);
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}

