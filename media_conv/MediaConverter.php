<?php namespace PowerChalk\MediaConverter;

use File\Disk;
use PowerChalk\Exceptions\PCException;
use PowerChalk\Process\ProcessFactory;
use PowerChalk\Logger\Logger;

class MediaConverter
{
	private $video_id;
	private $logger;
	private $mailer;
	private $hostname;
	private $video;
	private $video_info;
	private $media_converter;
	private $process_control;
	private $valid_video_id = false;
	private $calling_method;

	const LOCK_FILE_OWNER = 'www-data';
	const LOCK_FILE_GROUP = 'www-data';

	public function __construct(Logger $logger, $mailer, $video_id = null)
	{
		$this->process_control = ProcessFactory::mediaConverter();
		$this->logger = $logger;
		$this->mailer = $mailer;
		$this->initializeVideo($video_id);
		$this->hostname = gethostname();
	}

	public function initializeVideo($video_id, $video = null)
	{
		if(empty($video_id)) {
			$this->valid_video_id = false;
			return;
		}

		$parts = explode('.', $video_id);
		$this->video_id = $parts[0];
		$this->validateVideoId();

		$this->video = $video;
		if(empty($video)) {
			$this->getVideoFromID();
		}
	}

	private function validateVideoId()
	{
		if(!$this->valid_video_id) {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . " - validating video ID...";
			$this->info($this->formatLogMessage($message));
			if(empty($this->video_id)) {
				$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - Request did not contain a video ID, exiting...';
				$this->crit($this->formatLogMessage($message));
				return false;
			}
			$this->valid_video_id = true;

			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - Video ID VALID';
			$this->info($this->formatLogMessage($message));
		}
		else {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . " - using previously validated video ID.";
			$this->info($this->formatLogMessage($message));
		}
		return true;
	}

	static private function getCallingMethod($trace)
	{
		if(isset($trace[1])) {
			return $trace[1]['class'] . '::' . $trace[1]['function'] . '->';
		}
		return '';
	}

	private function formatLogMessage($message)
	{
		return '[' . $this->video_id . '] - ' . $message;
	}

	private function getVideoFromID()
	{
		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - getting video...';
		$this->info($this->formatLogMessage($message));
		$vid_lookup = $this->video_id . '%';
		$this->video = VideoTable::getInstance()->getVideoIDLike($vid_lookup);

		if(!$this->video) {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - The video ID (' . $vid_lookup . ') was not found, exiting...';
			$this->crit($this->formatLogMessage($message));
		} else {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - Video located and retrieved.';
			$this->info($this->formatLogMessage($message));
		}
	}

	public function validateVideo()
	{
		if(empty($this->video_id)) {
			$this->crit(__METHOD__ . ' - Empty Video ID, exiting...');
			return false;
		}

		$this->info(__METHOD__ . ' - Video ID provided, checking for record...');

		if(!$this->video) {
			$this->crit(__METHOD__ . ' - Video record not located, exiting...');
			return false;
		}
		$this->info(__METHOD__ . ' - Video record located...');

		if(!$this->video->isChalkTalk()) {
			$this->info(__METHOD__ . ' - Video is not a chalktalk, updating upload counter...');
			$mp = $this->video->getMemberProfile();
			$mp->setUploadCount($mp->getUploadCount() + 1);
			$mp->save();
		}

		if($this->video->getApproved() == Video::APPROVED_DUPLICATE) {
			$this->crit(__METHOD__ . ' - Video is a duplicate, sending email and exiting...');
			PostOffice::sendEmailDuplicateVideo($this->mailer, $this->video);
			return false;
		}

		$source = sfConfig::get('sf_web_dir') . sfConfig::get('app_upload_source') . '/' . $this->video->getVideoId();
		$source_path = pathinfo($source);
		if(!isset($source_path['extension'])) {
			$source .= '.flv';
			$source_path = pathinfo($source);
		}

		if(!file_exists($source)) {
			$this->crit(__METHOD__ . ' - Video source file not found, exiting...');
			$this->video->setApproved(Video::APPROVED_FAILED_NO_VIDEO);
			$this->video->save();
			return false;
		}
		$this->info(__METHOD__ . ' - Video source file located, validation complete.');
		return true;
	}

	public function queueVideo()
	{
		$this->info(__METHOD__ . ' - Adding to queue...');
		$this->video->setApproved(Video::APPROVED_QUEUED);
		$this->video->setQueueHostname(null);
		$this->video->save();
	}

	public function processQueue($limit = 1)
	{
		try {
			$this->process_control->lock();

			$this->info(__METHOD__ . ' - starting, claiming ' . $limit . ' video(s)...');
			$videos = VideoTable::getInstance()->claimQueuedVideos($limit);
			while($videos) {
				$this->info(__METHOD__ . ' - converting ' . $videos->count() . ' video(s)...');
				foreach($videos as $video) {
					$this->info(__METHOD__ . ' - initializing video, video ID ' . $video->getVideoId() . '...');
					$this->initializeVideo($video->getVideoId(), $video);
					if($this->convertVideo()) {
						$this->sendEmails();
						$this->info(__METHOD__ . ' - video converted...');
					}
					else {
						$this->crit(__METHOD__ . ' - video conversion failed!');
					}
				}
				$this->info(__METHOD__ . ' - ' . $videos->count() . ' video(s) converted, looking for more...');
				if(!$this->process_control->isKilled()) {
					$videos = VideoTable::getInstance()->claimQueuedVideos($limit);
				}
				else {
					$videos = null;
				}
			}
			$this->info(__METHOD__ . ' - No videos in queue, unlocking queue.');
			if(!$this->process_control->unlock()) {
				$this->crit(__METHOD__ . ' - unable to unlock queue, exiting...');
			}
		}
		catch(\Exception $e) {
			$this->info(__METHOD__ . ' - unable to lock queue, reason: [' . $e->getMessage() . '], exiting...');
		}
	}

	public function convertVideo()
	{
		$this->info(__METHOD__ . ' - Started...');

		if(!$this->makeFLV()) {
			$message = __METHOD__ . ' - makeFLV() - FAILED - Error creating FLV, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$this->process_control->updateStatusFile();

		if(!$this->makeMP4()) {
			$message = __METHOD__ . ' - makeMP4() - FAILED - Could not create MP4, continuing process.';
			$this->crit($this->formatLogMessage($message));
		}
		$this->process_control->updateStatusFile();

		if(!$this->makeThumbnail()) {
			$message = __METHOD__ . ' - makeThumbnail() - FAILED - Could not create a thumbnail, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$this->process_control->updateStatusFile();

		if(!$this->moveToProduction()) {
			$message = __METHOD__ . ' - moveToProduction() - FAILED - Could not be moved to production, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$this->process_control->updateStatusFile();

		if(!$this->updateVideoRecord()) {
			$message = __METHOD__ . ' - updateVideoRecord() - FAILED - Could not update database record, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$this->process_control->updateStatusFile();

		$this->scaleVideoThumbnail();
		$this->process_control->updateStatusFile();

		if(!$this->makeSWF()) {
			$message = __METHOD__ . ' - makeSWF() - FAILED - Could not create SWF, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$this->process_control->updateStatusFile();

		$message = __METHOD__ . ' - Complete';
		$this->info($this->formatLogMessage($message));

		return true;
	}

	public function makeFLV()
	{
		$message = __METHOD__ . ' - Creating...';
		$this->info($this->formatLogMessage($message));

		$this->calling_method = __METHOD__;

		if(!$this->getVideoInfo(array('default', 'original'))) {
			$message = __METHOD__ . ' - unable to locate source file, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->getConverter()) {
			$message = __METHOD__ . ' - unable to load proper converter, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->media_converter->makeFlv()) {
			$message = __METHOD__ . ' - media_converter->makeFLV() - FAILED - ' . get_class($this->media_converter) . '.  Command executed:
' . $this->media_converter->getLastCommand();
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$this->info(__METHOD__ . ' - Created successfully');
		return true;
	}

	public function makeMP4()
	{
		$message = __METHOD__ . ' - Creating...';
		$this->info($this->formatLogMessage($message));

		if(!$this->getVideoInfo(array('default', 'original'))) {
			$message = __METHOD__ . ' - unable to locate source file, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->getConverter(null, VideoTable::getInstance()->getVideoIDLike($this->video_id . '%'))) {
			$message = __METHOD__ . ' - unable to load proper converter, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->media_converter->makeMp4()) {
			$message = __METHOD__ . ' - media_converter->makeMP4() - FAILED';
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$this->info(__METHOD__ . ' - Created successfully');
		return true;
	}

	public function makeThumbnail()
	{
		$message = __METHOD__ . ' - Creating...';
		$this->info($this->formatLogMessage($message));

		if(!$this->getVideoInfo(array('flv_complete', 'flv_production'))) {
			$message = __METHOD__ . ' - unable to locate source file, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->getConverter()) {
			$message = __METHOD__ . ' - unable to load proper converter, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->media_converter->makeThumbnail()) {
			$message = __METHOD__ . ' - media_converter->makeThumbnail() - FAILED';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$message = __METHOD__ . ' - Watermark: loading video...';
		$this->info($this->formatLogMessage($message));

		if($this->video) {
			$message = __METHOD__ . ' - Watermark: video located, checking for chalktalk...';
			$this->info($this->formatLogMessage($message));
			if($this->video->isChalkTalk()) {
				$thumb_source_path = $this->getThumbSourcePath();
				$message = __METHOD__ . ' - Watermark: video is chalktalk, looking for thumb at ' . $thumb_source_path . '...';
				$this->info($this->formatLogMessage($message));
				$water_thumb = Image::createWatermarkedThumb($this->video['video_id'], $thumb_source_path);
				if($water_thumb['status'] != 'ok') {
					$message = __METHOD__ . ' - Watermark: Image::createWatermarkedThumb() - ' . $water_thumb['status'] . ' - ' . print_r($water_thumb, true);
					$this->crit($this->formatLogMessage($message));
				}
				else {
					$message = __METHOD__ . ' - Watermark: Image::createWatermarkedThumb() - success';
					$this->info($this->formatLogMessage($message));
				}
			}
			else {
				$message = __METHOD__ . ' - Watermark: video is not a chalktalk, aborting creation of watermarked thumb';
				$this->info($this->formatLogMessage($message));
			}
		}

		$this->info(__METHOD__ . ' - Created successfully');
		return true;
	}

	public function moveToProduction()
	{
		$message = __METHOD__ . ' - moving files to production...';
		$this->info($this->formatLogMessage($message));

		if(!$this->validateFileExists($this->getFLVCompletePath())) {
			$message = __METHOD__ . ' - uanble to find the finished FLV, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$path_flv_complete = $this->getFLVCompletePath();
		$path_flv_production = $this->getFLVProductionPath();
		$path_thumb_source = $this->getThumbSourcePath();
		$path_thumb_production = $this->getThumbProductionPath();
		$path_mp4_complete = $this->getMP4CompletePath();
		$path_mp4_production = $this->getMP4ProductionPath();

		$message = __METHOD__ . ' - Getting video record...';
		$this->info($this->formatLogMessage($message));

		if(!$this->video) {
			$message = ' - The video did not have a record in the database, exiting...';
			$this->crit($this->formatLogMessage($message));
			chmod($path_flv_complete, 0755);
			unlink($path_flv_complete);
			return false;
		}

		$this->info(__METHOD__ . ' - Got video record, copying completed FLV to production...');

		$result = copy($path_flv_complete, $path_flv_production);
		if(!$result) {
			$message = ' - Could not move ' . $path_flv_complete . ' to ' . $path_flv_production;
			$this->crit($this->formatLogMessage($message));
			PostOffice::sendSystemDebug('executeMoveToProduction()', $message);
			return false;
		}

		$this->info(__METHOD__ . ' - successfully moved to production, moving thumbnail to production...');

		$result = copy($path_thumb_source, $path_thumb_production);

		if(!$result) {
			$message = __METHOD__ . ' - Could not move ' . $path_thumb_source . ' to ' . $path_thumb_production;
			$this->crit($this->formatLogMessage($message));
			PostOffice::sendSystemDebug('executeMoveToProduction()', $message);
		}

		$this->info(__METHOD__ . ' - moved thumbnail to production');

		$result = copy($path_mp4_complete, $path_mp4_production);
		if(!$result) {
			$message .= __METHOD__ . ' - Could not move ' . $path_mp4_complete . ' to production';
			$this->crit($this->formatLogMessage($message));
			PostOffice::sendSystemDebug('executeMoveToProduction()', $message);
		}

		$message = __METHOD__ . ' - moved MP4, cleaning up source files from queue folder...';
		$this->info($this->formatLogMessage($message));
		$path_upload_source = $this->getUploadSourcePath();
		$path_upload_complete = $this->getUploadCompletePath();

		$result = copy($path_upload_source, $path_upload_complete);

		chmod($path_upload_source, 0755);
		chmod($path_flv_complete, 0755);
		chmod($path_thumb_source, 0755);

		if($result) {
			unlink($path_upload_source);
		}
		unlink($path_flv_complete);
		unlink($path_thumb_source);
		unlink($path_mp4_complete);

		$message = __METHOD__ . ' - all files moved to production';
		$this->info($this->formatLogMessage($message));
		return true;
	}

	public function updateVideoRecord()
	{
		$message = __METHOD__ . ' - updating video record...';
		$this->info($this->formatLogMessage($message));

		$message = __METHOD__ . ' - Getting video record...';
		$this->info($this->formatLogMessage($message));

		if(!$this->video) {
			$message = __METHOD__ . ' - The video did not have a record in the database, exiting...';
			$this->crit($this->formatLogMessage($message));
			chmod($this->getFLVCompletePath(), 0755);
			unlink($this->getFLVCompletePath());
			return false;
		}
		$message = __METHOD__ . ' - Got video record';
		$this->info($this->formatLogMessage($message));

		if(!$this->validateFileExists($this->getFLVProductionPath())) {
			$message = __METHOD__ . ' - uanble to find the production FLV, exiting...';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$this->getVideoInfo('flv_production');

		$message = __METHOD__ . ' - Get the file size';
		$this->info($this->formatLogMessage($message));
		$filesize = filesize($this->getFLVProductionPath());
		$message = __METHOD__ . ' - Got the file size, updating database...';
		$this->info($this->formatLogMessage($message));

		$this->video->setVideoId($this->video_id);
		if($this->video->getMemberProfile()->isOverStorageQuota()) {
			$this->video->setApproved(Video::APPROVED_ON_HOLD);
		}
		else {
			$this->video->setApproved(Video::APPROVED_YES);
		}
		$this->video->setFramerate($this->video_info->getFrameRate());
		$this->video->setVideoLengthSeconds($this->video_info->getDuration());
		$this->video->setFilesize($filesize);
		$this->video->setWidth($this->video_info->getWidth());
		$this->video->setHeight($this->video_info->getHeight());
		$this->video->setRequiresSync(Video::SYNC_YES);
		try {
			$this->video->save();
		}
		catch(\Exception $e) {
			$this->crit(__METHOD__ . ' - Unable to update record, reason: ' . $e->getMessage() . ', exiting...');
			return false;
		}

		$this->info(__METHOD__ . ' - Record updated');

		$message = __METHOD__ . ' - Database update complete';
		$this->info($this->formatLogMessage($message));
		return true;
	}

	public function scaleVideoThumbnail()
	{
		$message = __METHOD__ . ' - creating thumbnail...';
		$this->info($this->formatLogMessage($message));

		Image::buildInitialVideoThumb($this->video);

		$message = __METHOD__ . ' - created thumbnail successfully';
		$this->info($this->formatLogMessage($message));
		return true;
	}

	public function makeSWF()
	{
		// Create the Path variables
		$path_swf_working = $this->getSWFWorkingPath();
		$path_flv_production = $this->getFLVProductionPath();
		$path_flv_work = $path_swf_working . '/source.flv';
		$path_swf_working_work = $path_swf_working . '/work.swf';
		$path_swf_production = $this->getSWFProductionPath();

		$msg = __METHOD__ . " - Setting up our working path...";
		$this->info($msg);

		// Check if the working path exists.  If not just create it.
		if(!is_dir($path_swf_working)) {
			$message = __METHOD__ . " - FLV working path: " . $path_swf_working . ' does not exist, creating...';
			$this->info($this->formatLogMessage($message));
			mkdir($path_swf_working);
			if(!is_dir($path_swf_working)) {
				$message = __METHOD__ . " - unable to create FLV working path: " . $path_swf_working . ' , exiting...';
				$this->crit($this->formatLogMessage($message));
				return false;
			}
		}

		$msg = __METHOD__ . " - Copying the FLV to the working directory....";
		$this->info($msg);

		// move flv to working path
		$result = copy($path_flv_production, $path_flv_work);

		if(!$result) {
			$message = __METHOD__ . " - Could not copy the FLV to our working path: " . $path_flv_production . ' to ' . $path_flv_work;
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$msg = __METHOD__ . " - Getting media info on the source FLV...";
		$this->info($msg);
		// get the media info of the video
		$video_info = MediaInfoDriver::MediaInfoDriver($path_flv_work)->getVideoInfo();

		$msg = __METHOD__ . " - Getting the width and height for the FLV...";
		$this->info($msg);
		// set the width and height for the swf
		$width = $video_info->getWidth();
		$height = $video_info->getHeight();

		$msg = __METHOD__ . " - Getting a handle for a SWF movie object...";
		$this->info($msg);
		// Get a handle to a new swf movie.
		ming_useswfversion(9);
		$movie = new SWFMovie(9);
		$movie->setRate(30);

		$msg = __METHOD__ . " - Setting dimensions for the movie...";
		$this->info($msg);
		// Set it's width and height
		$movie->setDimension($width, $height);

		$msg = __METHOD__ . " - Loading the FLV into a stream...";
		$this->info($msg);
		// Load the stream
		$stream = new SWFVideoStream($path_flv_work);

		$msg = __METHOD__ . " - Setting the stream dimensions...";
		$this->info($msg);
		// Set the streams dimensions
		$stream->setdimension($width, $height);

		$msg = __METHOD__ . " - Adding the stream and a handle for the stream within the movie...";
		$this->info($msg);
		// Add the Stream to the Movie
		$item = $movie->add($stream);

		$msg = __METHOD__ . " - Naming the stream in the movie...";
		$this->info($msg);
		// Set it's name in the movie
		$item->setname("myvideo");

		// Get the frame count for the movie.
		$f = $stream->getnumframes();

		$msg = __METHOD__ . " - Iterating over the movie and advancing each frame...";
		$this->info($msg);
		// Iterate over the frames in the movie.
		for($ix = 0; $ix < $f; $ix++) {
			$movie->nextFrame();
		}

		$msg = __METHOD__ . " - Saving the movie to the disc...";
		$this->info($msg);
		// Save the movie to our working path.
		$movie->save($path_swf_working_work);

		$msg = __METHOD__ . " - Moving the SWF to production...";
		$this->info($msg);
		// Move from working path to production.
		rename($path_swf_working_work, $path_swf_production);

		$msg = __METHOD__ . " - Cleaning up the working directory...";
		$this->info($msg);
		// clean up our working directory.
		unlink($path_flv_work);
		rmdir($path_swf_working);

		$msg = __METHOD__ . " - SWF creation complete.";
		$this->info($msg);
		return true;
	}

	public function sendEmails()
	{
		$this->info(__METHOD__ . ' - sending...');

		if($this->video->isChalkTalk()) {
			try {
				PostOffice::sendChalktalkNotifySuccess($this->mailer, $this->video);
			}
			catch(PCException $e) {
				// ignore
			}

			FollowTable::getInstance()->queueFollowerNotifications($this->video->getUserId(), FollowNotification::ACTION_CHALKTALKED, 'Video', $this->video->getIndexer());
		}
		else {
			try {
				PostOffice::sendEmailVideoUploaded($this->mailer, $this->video);
			}
			catch(PCException $e) {
				$this->info(__METHOD__ . ' - Exception caught and ignored');
				// ignore
			}
			$this->info(__METHOD__ . ' - sent, sending follower notifications...');
			FollowTable::getInstance()->queueFollowerNotifications($this->video->getUserId(), FollowNotification::ACTION_UPLOADED, 'Video', $this->video->getIndexer());
		}

		$this->info(__METHOD__ . ' - all emails sent');
	}

	private function getVideoInfo($location = 'default')
	{
		$valid_location = false;
		if(!is_array($location)) {
			if(count(glob($this->getVideoLocation($location)))) {
				$valid_location = $location;
			}
			$location = array($location);
		}
		else {
			foreach($location as $l) {
				if(count(glob($this->getVideoLocation($l)))) {
					$valid_location = $l;
					$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - ' . $l . ' file found';
					$this->info($this->formatLogMessage($message));
					break;
				}
				else {
					$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - ' . $l . ' file NOT found...';
					$this->warn($this->formatLogMessage($message));
				}
			}
		}

		if(!$valid_location) {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - Unable to locate video in the following locations:
';
			foreach($location as $l) {
				$message .= $l . ':' . $this->getVideoLocation($l) . '
';
			}
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$source_file = $this->getVideoLocation($valid_location);
		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - getting video info driver for video located at ' . $source_file . '...';
		$this->info($this->formatLogMessage($message));

		$this->video_info = BaseVideoInfoDriver::MediaInfoDriver($source_file)->getVideoInfo();

		if(!$this->video_info->getIsValid()) {
			$errors = $this->video_info->getValidationErrors();
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - File was invalid.  Validation errors: ' . print_r($errors, true);
			$this->crit($this->formatLogMessage($message));
			return false;
		}
		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . " - got VALID video info for location '$valid_location'.";
		$this->info($this->formatLogMessage($message));
		return true;
	}

	private function getVideoLocation($location)
	{
		switch($location) {
			case 'original':
				$source_file = $this->getUploadCompletePath();
				break;
			case 'flv_complete':
				$source_file = $this->getFLVCompletePath();
				break;
			case 'flv_production':
				$source_file = $this->getFLVProductionPath();
				break;
			case 'default':
			default:
				$source_file = $this->getUploadSourcePath();
				break;
		}
		return $source_file;
	}

	private function getConverter($failed_status = null, $video = null)
	{
		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . " - getting the media converter...";
		$this->info($this->formatLogMessage($message));

		try {
			$this->media_converter = BaseMediaConverter::MediaConverterFactory($this->video_info, $video);
		}
		catch(MediaConverter_Exceptions_ICOD $e) {
			if(empty($failed_status)) {
				$failed_status = Video::APPROVED_FAILED_BAD_CODEC;
			}

			$this->video->setApproved($failed_status);
			$this->video->save();
			PostOffice::sendEmailInvalidCodecICOD($this->mailer, $this->video);

			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - Video contained an invalid Codec.';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		if(!$this->media_converter) {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - Video did not contain a known video conversion type, exiting...';
			$this->crit($this->formatLogMessage($message));

			$this->video->setApproved(Video::APPROVED_FAILED_UNKNOWN_TYPE);
			$this->video->save();
			return false;
		}

		$this->got_converter = true;
		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . ' - got media converter (' . get_class($this->media_converter) . ').';
		$this->info($this->formatLogMessage($message));
		return true;
	}

	private function validateFileExists($source)
	{
		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . " - verifying FLV exists.";
		$this->info($this->formatLogMessage($message));

		if(!count(glob($source))) {
			$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . 'File: ' . $source . ' does not exist!';
			$this->crit($this->formatLogMessage($message));
			return false;
		}

		$message = self::getCallingMethod(debug_backtrace()) . __METHOD__ . " - FLV exists!";
		$this->info($this->formatLogMessage($message));
		return true;
	}

	private function getUploadSourcePath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_upload_source') . '/' . $this->getUploadFilename();
	}

	private function getUploadFilename()
	{
		return $this->video_id . '*';
	}

	private function getUploadCompletePath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_source_complete') . '/' . $this->getUploadFilename();
	}

	private function getFLVCompletePath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_flv_complete') . '/' . $this->getFLVCompleteFilename();
	}

	private function getFLVCompleteFilename()
	{
		return $this->video_id . '.done.flv';
	}

	private function getFLVProductionPath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_flv_production') . '/' . $this->getFLVProductionFilename();
	}

	private function getFLVProductionFilename()
	{
		return $this->video_id . '.flv';
	}

	private function getThumbSourcePath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_thumb_raw') . '/' . $this->getThumbSourceFilename();
	}

	private function getThumbSourceFilename()
	{
		return $this->video_id . '.done.jpeg';
	}

	private function getThumbProductionPath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_thumb_production') . '/' . $this->getThumbProductionFilename();
	}

	private function getThumbProductionFilename()
	{
		return $this->video_id . '.jpg';
	}

	private function getMP4CompletePath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_mp4_complete') . '/' . $this->getMP4CompleteFilename();
	}

	private function getMP4CompleteFilename()
	{
		return $this->video_id . '.done.mp4';
	}

	private function getMP4ProductionPath()
	{
		return sfConfig::get('sf_web_dir') . sfConfig::get('app_mp4_production') . '/' . $this->getMP4ProductionFilename();
	}

	private function getMP4ProductionFilename()
	{
		return $this->video_id . '.mp4';
	}

	private function getSWFWorkingPath()
	{
		return sfConfig::get('app_swf_work') . '/' . $this->getSWFWorkingFilename();
	}

	private function getSWFWorkingFilename()
	{
		return $this->video_id;
	}

	private function getSWFProductionPath()
	{
		return sfconfig::get('sf_web_dir') . sfConfig::get('app_swf_production') . '/' . $this->getSWFProductionFilename();
	}

	private function getSWFProductionFilename()
	{
		return $this->video_id . '.swf';
	}

}
