# lz4php
#### A command line utility based on lz4.class.php
**Requires to compile and install PHP LZ4 extension available here : https://github.com/kjdev/php-ext-lz4.git**

Usage : **php lz4php.php** -[options]

######Command line options:
    -h              Display help
    -i              Input filename or file pattern
    -o              Output filename (used only if a single file was found
                    else will use found names and add or remove compression file extension)
    -c              Compress flag
    -w              Write data to output file(s) (will output decompressed data to console if not specified)
    -d              Display debug information
    --standard      Use standard compression level (high is default)

######Usage examples:

  >Compress data
  
    /**
    * read myinputfilename, compress data, write content in myoutputfilename
    */
    php lz4php.php -i myinputfilename -o myoutputfilename -w -c

    /**
    * read myinputfilename, compress data, write content in myoutputfilename_
    * use standard compression level (default is high), display debug information
    */
    php lz4php.php -i myinputfilename -o myoutputfilename -w -c -d --standard

  >Decompress data
  
    /**
    * read myinputfilename, decompress data, write data in myoutputfilename, display debug information
    */
    php lz4php.php -i myinputfilename -o myoutputfilename -w -d

    /**
    * read myinputfilename, decompress data, output data to console (similar to "cat" command on Linux)
    */
    php lz4php.php -i myinputfilename
    

  >Compress data with input file pattern
  
    /**
    * will find all files with "txt" extension, compress data
    * output data in files based on input files names, display debug information
    */
    php lz4php.php -i ./inputdirectory/*.txt -w -d -c

    /**
    * will find all lz4 files containing "project" in names
    * compress data with standard compression level
    * output data in files based on input files names
    */
    php lz4php.php -i ./inputdirectory/*project* -c -w --standard

  >Decompress data with input file pattern
  
    /**
    * will find all lz4 files containing "2015" in names, decompress data
    * output data in files based on input files names, display debug information
    */
    php lz4php.php -i ./inputdirectory/*2015* -w -d

    /**
    * will find all lz4 files containing "compressed" in names
    * decompress data, output whole data to console (similar to "cat" command on Linux)
    */
    php lz4php.php -i ./inputdirectory/*compressed*

  >Interaction with Linux command line utilities
  
    // Diff between myfiletocompare and decompressed data
    php lz4php.php -i ./inputdirectory/*2015* | diff myfiletocompare -
    // Find all lines containing "world"
    php lz4php.php -i ./inputdirectory/*2015* | grep "world"
    // Count all lines containing "world"
    php lz4php.php -i ./inputdirectory/*2015* | grep "world" | wc -l
