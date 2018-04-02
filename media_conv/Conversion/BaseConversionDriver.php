<?php namespace PowerChalk\MediaConverter\Conversion;

/**
 * Description of BaseConversionDriver
 *
 * @author chris
 */
abstract class BaseConversionDriver
{
	protected function checkConversion(VideoInfo $video)
	{
		$filename = $video->getFilename();
		$pathInfo = pathinfo($filename);
		$file = $pathInfo['filename'] . '.flv';

		$fileExists = file_exists($file);
		$fileSize = filesize($file);

		return $fileExists && ($fileSize != 0);
	}

	protected function executeCommand($command)
	{
		exec($command);
	}

	protected function copyFile($src, $dest)
	{
		copy($src, $dest);
	}

	protected function _scale(VideoInfo $videoInfo);
}