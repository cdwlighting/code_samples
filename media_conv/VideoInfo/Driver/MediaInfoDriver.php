<?php namespace PowerChalk\MediaConverter\VideoInfo\Driver;

/**
 * Description of Conversion
 *
 * @author chris
 */
class MediaInfoDriver extends BaseVideoInfoDriver implements iVideoInfoDriver
{
	protected $mediaInfo_cmd = 'mediainfo --output=xml';

	protected function _LoadMediaInfo($filename)
	{
		$mediainfo = shell_exec($this->mediaInfo_cmd . ' ' . $filename);
		$mediainfo = preg_replace("/[^[:print:]]+/", "", $mediainfo);
		$mediaInfoXml = new SimpleXMLElement($mediainfo);
		$this->_processDisplayAspectRatio($mediaInfoXml);
		$this->_processDuration($mediaInfoXml);
		$this->_processFilename($mediaInfoXml);
		$this->_processFormat($mediaInfoXml);
		$this->_processFrameRate($mediaInfoXml);
		$this->_processHeight($mediaInfoXml);
		$this->_processRotation($mediaInfoXml);
		$this->_processWidth($mediaInfoXml);

		//	Check for validity, go to backup if invalid
		if(!$this->_videoInfo->getIsValid()) {
			$converter = new VideoConversion();
			$parameters = $converter->getVideoParameters($filename);
			$info = $converter->parseVideoParameters($parameters, $filename);
			$this->_processParameters($info);
			$this->_logger->info('Media Info returned INVALID XML for ' . $filename . ': ' . $mediainfo);
		}
		else {
			$this->_logger->info('Media Info returned valid XML for ' . $filename);
		}
	}

	protected function _processParameters($info)
	{
		if(!$this->_videoInfo->getDuration()) {
			$this->_videoInfo->setDuration(round($info['length']));
		}
		if(!$this->_videoInfo->getFilename()) {
			$this->_videoInfo->setFilename(realpath($info['filename']));
		}
		if(!$this->_videoInfo->getFormat()) {
			$this->_videoInfo->setFormat($info['format']);
		}
		if(!$this->_videoInfo->getFrameRate()) {
			$this->_videoInfo->setFrameRate((string) $info['fps'] > 0 ? round($info['fps']) : 30);
		}
		if(!$this->_videoInfo->getHeight()) {
			$this->_videoInfo->setHeight($this->_processViewPortDimension($info['height']));
		}
		if(!$this->_videoInfo->getWidth()) {
			$this->_videoInfo->setWidth($this->_processViewPortDimension($info['width']));
		}
	}

	protected function _processFilename(SimpleXmlElement $xml)
	{
		$filename = (string) $xml->File->track[0]->Complete_name;
		$filename = realpath($filename);
		$this->_videoInfo->setFilename($filename);
	}

	protected function _processFormat(SimpleXMLElement $xml)
	{
		$format = (string) $xml->File->track[1]->Format;
//		if($format = '')
//			$format = 'VP6';
		$this->_videoInfo->setFormat($format);
	}

	protected function _processHeight(SimpleXMLElement $xml)
	{
		$height = (string) $xml->File->track[1]->Height;
		$height = $this->_processViewPortDimension($height);
		$this->_videoInfo->setHeight($height);
	}

	protected function _processWidth(SimpleXmlElement $xml)
	{
		$width = (string) $xml->File->track[1]->Width;
		$width = $this->_processViewPortDimension($width);
		$this->_videoInfo->setWidth($width);
	}

	protected function _processViewPortDimension($dimension)
	{
		$dimension = trim($dimension);
		$dimension = str_replace(' pixels', '', $dimension);
		$dimension = str_replace(' ', '', $dimension);

		return $dimension;
	}

	protected function _processDisplayAspectRatio(SimpleXMLElement $xml)
	{
		$aspectRatio = (string) $xml->File->track[1]->Display_aspect_ratio;
		$aspectRatio = trim($aspectRatio);
		if(strstr($aspectRatio, ":")) {
			$ratio = explode(':', $aspectRatio);
			$aspectRatio = $ratio[0] / $ratio[1];
			$aspectRatio = round($aspectRatio, 3);
		}
		$this->_videoInfo->setAspectRatio($aspectRatio);
	}

	protected function _processFrameRate(SimpleXMLElement $xml)
	{
		$framerate = (int) $xml->File->track[1]->Frame_rate;
		$framerate = (string) ($framerate > 0) ? $framerate : 30;

		$this->_videoInfo->setFrameRate($framerate);
	}

	protected function _processDuration(SimpleXMLElement $xml)
	{
		$duration = (string) $xml->File->track[1]->Duration;
		if($duration == '') {
			$duration = (string) $xml->File->track[0]->Duration;
		}
		$duration = explode(' ', $duration);
		$hour = $min = $sec = $millis = 0;
		$min = '00';
		foreach($duration as $value) {
			$value = trim($value);

			if(strstr($value, 'h')) {
				$hour = (int) str_replace('h', '', $value) . ':';
			}
			if(!strstr($value, 'ms')) {
				if(strstr($value, 'm')) {
					$min = (int) str_replace('m', '', $value);
				}

				if(strstr($value, 's')) {
					$sec = (int) str_replace('s', '', $value);
				}
			}
			elseif(strstr($value, 'ms')) {
				$millis = (int) str_replace('ms', '', $value);
			}
		}

		$duration = $hour * 3600 + $min * 60 + $sec + $millis / 1000;

		$this->_videoInfo->setDuration($duration);
	}

	protected function _processRotation(SimpleXMLElement $xml)
	{
		$rotation = (string) $xml->File->track[1]->Rotation;
		$rotation = intval(trim($rotation));
		$this->_videoInfo->setRotation($rotation);
	}

}
