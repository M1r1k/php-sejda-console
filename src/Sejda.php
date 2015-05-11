<?php

/**
 * @file
 * Contains Sejda class to work with Sejda-console CLI tool.
 */

namespace m1r1k\SejdaConsole;

use mikehaertl\tmp\File;
use mikehaertl\shellcommand\Command;

/**
 * Class Sejda
 *
 * @package m1r1k\SejdaConsole
 * @author Artem Miroshnyk <miroshnik1992@gmail.com>
 * @license http://www.opensource.org/licenses/MIT
 */
class Sejda {

  const TMP_PREFIX = 'tmp_sejda_pdf_';

  /**
   * @var string
   * The name of the Sejda Console binary. Default is `sejda-console`.
   * You can also configure a full path here.
   */
  public $binary = 'sejda-console';

  /**
   * @var array
   * Options to pass to the Command constructor. Default is none.
   */
  public $commandOptions = array();

  /**
   * @var string|null
   * The directory to use for temporary files.
   * If null (default) the dir is autodetected.
   */
  public $tmpDir;

  /**
   * @var bool
   * Whether the PDF was created.
   */
  protected $_isCreated = FALSE;

  /**
   * @var bool
   * Whether to ignore any errors from sejda. Default is false.
   */
  public $ignoreWarnings = FALSE;

  /**
   * @var array
   * Global options for sejda-console as array('--opt1', '--opt2'=>'val', ...).
   */
  protected $_options = array();

  /**
   * @var array
   * List of sejda-console objects as arrays.
   */
  protected $_objects = array();

  /**
   * @var \mikehaertl\tmp\File
   * The temporary PDF file.
   */
  protected $_tmpPdfFile;

  /**
   * @var \mikehaertl\tmp\File[]
   * List of tmp file objects. This is here to keep a reference to File and
   * thus avoid too early call of File::__destruct() if the file is not
   * referenced anymore.
   */
  protected $_tmpFiles = array();

  /**
   * @var Command
   * The command instance that executes sejda-console.
   */
  protected $_command;

  /**
   * @var string
   * The detailed error message. Empty string if none.
   */
  protected $_error = '';

  /**
   * @param array|string $options global options for wkhtmltopdf or page URL, HTML or PDF/HTML filename
   */
  public function __construct($options = NULL) {
    if (is_array($options)) {
      $this->setOptions($options);
    }
    elseif (is_string($options)) {
      $this->addPdf($options);
    }
  }

  /**
   * Add a pdf file.
   *
   * @param array|string $pdf
   *   Relative or absolute paths to pdf file.
   *
   * @return static
   *   The Sejda instance for method chaining.
   */
  public function addPdf($pdf) {
    $this->_objects[] = array(
      'key' => '-f',
      'value' => $pdf,
    );
    return $this;
  }

  /**
   * Add all files in the directory to the scope.
   *
   * @param array|string $directory
   *   Relative or absolute path to directory with pdf.
   *
   * @return static
   *   The Sejda instance for method chaining.
   */
  public function addDirectories($directory = array()) {
    $this->_objects[] = array(
      'key' => '-o',
      'value' => $directory,
    );
    return $this;
  }

  /**
   * Save the PDF to given filename (triggers PDF creation).
   *
   * @param string $filename to save PDF as
   * @return bool whether PDF was created successfully
   */
  public function saveAs($filename) {
    if (!$this->_isCreated && !$this->createPdf()) {
      return FALSE;
    }
    if (!$this->_tmpPdfFile->saveAs($filename)) {
      $this->_error = "Could not save PDF as '$filename'";
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Set global option(s).
   *
   * @param array $options
   *   List of global PDF options to set as name/value pairs.
   *
   * @return static
   *   The Pdf instance for method chaining.
   */
  public function setOptions($options = array()) {
    foreach ($options as $key => $val) {
      if (is_int($key)) {
        $this->_options[] = $val;
      }
      elseif ($key[0] !== '_' && property_exists($this, $key)) {
        $this->$key = $val;
      }
      else {
        $this->_options[$key] = $val;
      }
    }
    return $this;
  }

  /**
   * @return Command
   *   The command instance that executes sejda.
   */
  public function getCommand() {
    if ($this->_command === NULL) {
      $options = $this->commandOptions;
      if (!isset($options['command'])) {
        $options['command'] = $this->binary;
      }
      $this->_command = new Command($options);
    }
    return $this->_command;
  }

  /**
   * @return string
   *   The detailed error message. Empty string if none.
   */
  public function getError() {
    return $this->_error;
  }

  /**
   * @return string
   *   The filename of the temporary PDF file.
   */
  public function getPdfFilename() {
    if ($this->_tmpPdfFile === NULL) {
      $this->_tmpPdfFile = new File('', '.pdf', self::TMP_PREFIX, $this->tmpDir);
    }
    return $this->_tmpPdfFile->getFileName();
  }

  /**
   * Run the Command to create the tmp PDF file
   *
   * @return bool whether creation was successful
   */
  protected function createPdf() {
    if ($this->_isCreated) {
      return FALSE;
    }
    $command = $this->getCommand();
    $fileName = $this->getPdfFilename();

    $command->addArg('merge');
    foreach ($this->_objects as $object) {
      $command->addArg($object['key'], $object['value']);
    }
    $command->addArg($fileName, NULL, TRUE);
    if (!$command->execute()) {
      $this->_error = $command->getError();
      if (!(file_exists($fileName) && filesize($fileName) !== 0 && $this->ignoreWarnings)) {
        return FALSE;
      }
    }
    $this->_isCreated = TRUE;
    return TRUE;
  }

}