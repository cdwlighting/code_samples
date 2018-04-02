<?php namespace PowerChalk\MediaConverter\VideoInfo;

/**
 * Description of VideoInfo
 *
 * @author chris
 */
class VideoInfo
{
	protected $_filename = '';
	protected $_format = '';
	protected $_height = '';
	protected $_width = '';
	protected $_rotation = '';
	protected $_aspectRatio = '';
	protected $_duration = '';
	protected $_frameRate = '';
	protected $_isValid = false;
	protected $_validation_errors = array();

	public function __construct($videoInfo = null)
	{
		if(is_null($videoInfo))
			return;
		$this->_filename = $videoInfo['filename'];
		$this->_format = $videoInfo['format'];
		$this->_height = $videoInfo['height'];
		$this->_width = $videoInfo['width'];
		$this->_rotation = $videoInfo['rotation'];
		$this->_aspectRatio = $videoInfo['aspect_ratio'];
		$this->_duration = $videoInfo['duration'];
		$this->_frameRate = $videoInfo['frame_rate'];

		$this->_isValid = $this->_validate();
	}

	public function getFilename()
	{
		return $this->_filename;
	}

	public function setFilename($filename)
	{
		$this->_filename = $filename;
		$this->_isValid = $this->_validate();
	}

	public function getFormat()
	{
		return $this->_format;
	}

	public function setFormat($format)
	{
		$this->_format = $format;
		$this->_isValid = $this->_validate();
	}

	public function getHeight()
	{
		return $this->_height;
	}

	public function setHeight($height)
	{
		$this->_height = $height;
		$this->_isValid = $this->_validate();
	}

	public function getWidth()
	{
		return $this->_width;
	}

	public function setWidth($width)
	{
		$this->_width = $width;
		$this->_isValid = $this->_validate();
	}

	public function getRotation()
	{
		if($this->_rotation == '') {
			return 0;
		}
		return $this->_rotation;
	}

	public function setRotation($rotation)
	{
		$this->_rotation = $rotation;
		$this->_isValid = $this->_validate();
	}

	public function getAspectRatio()
	{
		return $this->_aspectRatio;
	}

	public function setAspectRatio($aspectRation)
	{
		$this->_aspectRatio = $aspectRation;
		$this->_isValid = $this->_validate();
	}

	public function getDuration()
	{
		return (int) round($this->_duration);
	}

	/**
	 *
	 * @param integer $duration
	 */
	public function setDuration($duration)
	{
		$this->_duration = $duration;
		$this->_isValid = $this->_validate();
	}

	/**
	 *
	 * @return integer
	 */
	public function getFrameRate()
	{
		return $this->_frameRate;
	}

	/**
	 *
	 * @param integer $frameRate
	 */
	public function setFrameRate($frameRate)
	{
		$this->_frameRate = $frameRate;
		$this->_isValid = $this->_validate();
	}

	/**
	 *
	 * @return integer
	 */
	public function getFrameCount()
	{
		$frames = (int) ceil($this->_duration * $this->_frameRate);

		return $frames;
	}

	/**
	 *
	 * @return boolean
	 */
	public function getIsValid()
	{
		return $this->_isValid;
	}

	public function getValidationErrors()
	{
		return $this->_validation_errors;
	}

	protected function _validate()
	{
		$response = true;
		if(strlen($this->_filename) <= 15) {
			$this->_validation_errors['filename'] = 'Filename <= 15';
			$response = false;
		}
		else {
			unset($this->_validation_errors['filename']);
		}
		if(strlen($this->_format) < 2) {
			$this->_validation_errors['format_length'] = 'Format string length < 2';
			$response = false;
		}
		else {
			unset($this->_validation_errors['format_length']);
		}
		if($this->_height < 1) {
			$this->_validation_errors['height'] = 'Height < 1';
			$response = false;
		}
		else {
			unset($this->_validation_errors['height']);
		}
		if($this->_width < 1) {
			$this->_validation_errors['width'] = 'Width < 1';
			$response = false;
		}
		else {
			unset($this->_validation_errors['width']);
		}
		if($this->_duration <= 0) {
			$this->_validation_errors['duration'] = 'Duration <= 0';
			$response = false;
		}
		else {
			unset($this->_validation_errors['duration']);
		}
		if($this->_frameRate <= 0) {
			$this->_validation_errors['frame_rate'] = 'Frame Rate <= 0';
			$response = false;
		}
		else {
			unset($this->_validation_errors['frame_rate']);
		}
		return $response;
	}

}
