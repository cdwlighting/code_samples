<?php namespace PowerChalk\MediaConverter\Conversion;

/**
 *
 * @author chris
 */
interface iVideoConverter
{
	public function makeFlv(VideoInfo $videoInfo);
	public function makeThumbnail(VideoInfo $videoInfo);
	public function makeFrameImages(VideoInfo $videoInfo);
	public function makeFrameImageThumbnails(VideoInfo $videoInfo);
}
