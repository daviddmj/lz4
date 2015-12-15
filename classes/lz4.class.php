<?php

/**
 * Class LZ4
 * @description LZ4 compression / decompression class
 * @author dafonso@prestaconcept.net
 */

final class LZ4
{
    /**
     * Only accepted decompressed data type
     */
    const TEXT_TYPE = 'text/plain';

    /**
     * File Extension
     */
    const FILE_EXT = 'lz4';

    /**
     * Method flags
     */
    const COMPRESS = 'compress';
    const DECOMPRESS = 'decompress';

    /**
     * Compression level flags
     */
    const HIGH_COMPRESSION = 1;
    const STANDARD_COMPRESSION = 0;

    /**
     * Input filename
     * @var string $inputFile
     */
    private $inputFile = null;

    /**
     * Output filename
     * @var string $outputFile
     */
    private $outputFile = null;

    /**
     * Execution time flags
     * @var int $time_start
     * @var int $time_end
     */
    private $time_start = 0;
    private $time_end = 0;

    /**
     * Default method
     * @var string
     */
    private $method = self::DECOMPRESS;

    /**
     * Set compression level, self::HIGH_COMPRESSION by default
     * @var bool $compressionLevel
     */
    private $compressionLevel = self::HIGH_COMPRESSION;

    /**
     * Output real time reporting
     * @var bool $reportEnabled
     */
    private $reportEnabled = false;

    /**
     * Enable write to disk
     * @var bool $AllowFileOutput
     */
    private $AllowFileOutput = false;

    /**
     * Store a file in memory pointer
     * @var null
     */
    private $memoryStream = null;

    /**
     * Allocated size for memory stream
     * @var int
     */
    private $memoryStreamSize = 0;

    /**
     * Input file data
     * @var null|string
     */
    private $inputFileData = null;

    /**
     * Store execution errors
     * @var array
     */
    private $errors = array();

    /**
     * Store execution logs
     * @var array
     */
    private $log = array();

    /**
     * Check if LZ4 extension is loaded and sets input and output filename
     * @throws Exception
     */
    public function __construct()
    {
        // Check if extension is loaded
        if (!extension_loaded("lz4")) {
            throw new Exception('LZ4 extension not loaded');
        }
    }

    /**
     * Set the input file
     * @param string $inputFile
     * @return $this
     * @throws Exception
     */
    public function setInputFile($inputFile)
    {
        // Check if input file exists
        if (!file_exists($inputFile)) {
            throw new Exception(sprintf('Input file "%s" not found', $inputFile));
        }

        $this->inputFile = $inputFile;

        return $this;
    }

    /**
     * Return input file
     * @return string
     */
    public function getInputFile()
    {
        return $this->inputFile;
    }

    /**
     * Set the output file
     * @param string $outputFile
     * @return $this
     */
    public function setOutputFile($outputFile)
    {
        $this->outputFile = $outputFile;

        return $this;
    }

    /**
     * Return output file
     * @return string
     */
    public function getOutputFile()
    {
        return $this->outputFile;
    }

    /**
     * Enable file output
     * @param $AllowFileOutput
     * @return $this
     */
    public function setAllowFileOutput($AllowFileOutput)
    {
        if (is_bool($AllowFileOutput)) {
            $this->AllowFileOutput = $AllowFileOutput;
        }

        return $this;
    }

    /**
     * Enable reporting
     * @param bool $reportEnabled
     * @return $this
     */
    public function setReportEnabled($reportEnabled)
    {
        if (is_bool($reportEnabled)) {
            $this->reportEnabled = $reportEnabled;
        }

        return $this;
    }

    /**
     * Set compression level
     * @param $compressionLevel
     * @return $this
     */
    public function setCompressionLevel($compressionLevel)
    {
        // Check if $compressionLevel is correct
        if (in_array($compressionLevel, array(self::STANDARD_COMPRESSION, self::HIGH_COMPRESSION))) {
            $this->compressionLevel = $compressionLevel;
        }

        return $this;
    }

    /**
     * Return encountered errors
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function getLog()
    {
        return $this->log;
    }

    /**
     * Compress input file
     * @return void
     * @throws Exception
     */
    public function compressFile()
    {
        $this->method = self::COMPRESS;
        $this->processFile();
    }

    /**
     * Decompress input file
     * @return void
     * @throws Exception
     */
    public function decompressFile()
    {
        $this->method = self::DECOMPRESS;
        $this->processFile();
    }

    /**
     * Initialize properties and close memory stream if still opened
     * @return void
     */
    public function clear()
    {
        // Close stream if still opened
        if (($this->memoryStream !== null) && (get_resource_type($this->memoryStream) == 'stream')) {
            fclose($this->memoryStream);
        }

        // Free memory
        unset(
            $this->outputFile,
            $this->inputFile,
            $this->inputFileData,
            $this->time_start,
            $this->time_end,
            $this->memoryStream,
            $this->errors,
            $this->log
        );

        // Initialize properties
        $this->outputFile    = null;
        $this->inputFile     = null;
        $this->inputFileData = null;
        $this->time_start    = 0;
        $this->time_end      = 0;
        $this->memoryStream  = null;
        $this->errors        = array();
        $this->log           = array();
    }

    /**
     * Read a line if data available
     * @return null|string
     */
    public function readLine()
    {
        $data = null;
        if (($this->method == self::COMPRESS) || ($this->memoryStream == null)) return $data;
        !feof($this->memoryStream) ? $data = fgets($this->memoryStream) : fclose($this->memoryStream);
        return $data;
    }

    /**
     * Delete input file to free disk space
     * @return bool
     */
    public function deleteInputFile()
    {
        if (file_exists($this->inputFile)) {
            return unlink($this->inputFile);
        }

        return false;
    }

    /**
     * Turn LZ4 library warnings into Exceptions
     * @param $errNo
     * @param $errStr
     * @throws Exception
     */
    private function warning_handler($errNo, $errStr) {
        throw new Exception($errStr, $errNo);
    }

    /**
     * Return a formatted string with memory usage data
     * @return string
     */
    private function getMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true);
        $peakUsage   = memory_get_peak_usage(true);
        $unit=array('b','kb','mb','gb','tb','pb');
        $formattedValue = @round($memoryUsage/pow(1024,($i=floor(log($memoryUsage,1024)))),2).' '.$unit[(int)$i];
        $formattedPeakValue = @round($peakUsage/pow(1024,($i=floor(log($peakUsage,1024)))),2).' '.$unit[(int)$i];

        return sprintf('Memory usage: %s / peak usage: %s', $formattedValue, $formattedPeakValue);
    }

    /**
     * Pre-process treatments
     * @return void
     */
    private function preProcess()
    {
        // User local handler to catch warnings and convert them into Exceptions
        set_error_handler("self::warning_handler", E_WARNING);

        if (!$this->reportEnabled) return;

        $this->time_start = microtime(true);
        $this->log[] = sprintf('Begin %s process - %s'  . PHP_EOL, $this->method, $this->getMemoryUsage());

        if ($this->method == self::COMPRESS) {
            $this->log[] = sprintf('Compression level: %s' . PHP_EOL, $this->compressionLevel == self::STANDARD_COMPRESSION ? 'STANDARD' : 'HIGH');
        }
    }

    /**
     * Compress or decompress the input file content and flush it in output file if necessary
     * @throws Exception
     * @return void
     */
    private function process()
    {
        $this->readInputFile();
        $this->createMemoryStream();
        $this->writeOutputFile();
    }

    /**
     * Post-process treatments
     * @return void
     */
    private function postProcess()
    {
        // Restore error warning handler
        restore_error_handler();

        if (!$this->reportEnabled) return;

        $this->time_end = microtime(true);
        $execution_time = ($this->time_end - $this->time_start);
        $this->log[] = sprintf('Input file:  %s' . PHP_EOL, $this->inputFile);
        $this->log[] = sprintf('Output file: %s' . PHP_EOL, $this->outputFile);
        $this->log[] = sprintf('Finish %s process - %s' . PHP_EOL, $this->method, $this->getMemoryUsage());
        $this->log[] = sprintf('Total Execution Time: %s seconds' . PHP_EOL, $execution_time);
    }

    /**
     * Call file processing stack
     * @return void
     * @throws Exception
     */
    private function processFile()
    {
        $this->preProcess();
        $this->process();
        $this->postProcess();
    }

    /**
     * Get stream content type
     * @return null|string
     */
    private function getStreamContentType()
    {
        if ($this->memoryStream !== null) {
            $fInfo = new finfo(FILEINFO_MIME);
            $type = explode(';', $fInfo->buffer($this->readLine()));
            unset($fInfo);
            rewind($this->memoryStream);
            if (count($type)>0) return strtolower($type[0]);
        }

        return null;
    }

    /**
     * Read input file content
     * @return void
     */
    private function readInputFile()
    {
        if ($this->inputFile == null) {
            $this->errors[] = 'Input file not set';
            return;
        }

        // Read input file
        $fileHandle = fopen($this->inputFile, 'r');
        if ($fileHandle == false) {
            $this->errors[] = sprintf('Unable to open input file %s', $this->inputFile);
            return;
        }
        $this->inputFileData = fread($fileHandle, filesize($this->inputFile));
        fclose($fileHandle);
        unset($fileHandle);
    }

    /**
     * Create memory stream from input file content
     * @return void
     */
    private function createMemoryStream()
    {
        if ($this->inputFileData !== null) {
            // Create memory stream
            $this->memoryStream = null;
            $this->memoryStreamSize = 0;
            try {
                $this->memoryStream = fopen("php://memory", 'r+');
                $this->memoryStreamSize = fputs($this->memoryStream, ($this->method == self::COMPRESS ? lz4_compress($this->inputFileData, $this->compressionLevel) : lz4_uncompress($this->inputFileData)));
                rewind($this->memoryStream);

                if (($this->method == self::DECOMPRESS) && (!$this->AllowFileOutput) && (($contentType = $this->getStreamContentType()) !== self::TEXT_TYPE)) {
                    fclose($this->memoryStream);
                    $this->memoryStream = null;
                    $this->errors[] = sprintf('Bad decoded content type detected, expected "%s" found "%s"', self::TEXT_TYPE, $contentType);
                }
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }

            // Free memory
            unset($this->inputFileData);
            $this->inputFileData = null;
        }
    }

    /**
     * Write decoded or encoded memory content to disk if necessary
     * @return boolean
     */
    private function writeOutputFile()
    {
        if (($this->method == self::COMPRESS || $this->AllowFileOutput) && ($this->memoryStream !== null) && ($this->outputFile !== null)) {
            if ($this->memoryStreamSize > 0) {
                // Create output file
                $fileHandle = fopen($this->outputFile, 'w+');
                if ($fileHandle == false) {
                    $this->errors[] = sprintf('Unable to create output file %s', $this->outputFile);
                    return;
                }

                // Copy data to output file
                stream_copy_to_stream($this->memoryStream, $fileHandle);
                fclose($fileHandle);
                unset($fileHandle);
            }

            // Free memory stream
            fclose($this->memoryStream);
            unset($this->memoryStream);
            $this->memoryStream = null;
        }
    }
}
