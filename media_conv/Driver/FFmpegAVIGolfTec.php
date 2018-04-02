<?php namespace PowerChalk\MediaConverter\Driver;

/**
 * Description of FFmpegAVI
 *
 * @author chris
 */
class FFmpegAVIGolfTec extends FFmpegAVI
{
	protected function _scale($maxHeight = 480)
	{
		$videoInfo = $this->_videoInfo;
		$scale = '';
		$newHeight = 480;
		$newWidth = 640;

		$rotation = (int) $videoInfo->getRotation();

		if($rotation == 90 || $rotation == 270) {
			$scale = '-s ' . $newHeight . 'x' . $newWidth;
		}
		else {
			$scale = '-s ' . $newWidth . 'x' . $newHeight;
		}

		return $scale;
	}

}
