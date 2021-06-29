<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Modules\Api\Models\AttachmentModel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;


class ProcessConvertVideo extends Job implements ShouldQueue {

  use InteractsWithQueue,
      SerializesModels;

  protected $id;

  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($id) {
    //
    $this->id = $id;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle() {
    $ffmpeg = env('FFMPEG', '/usr/local/bin/ffmpeg');
    $ffprobe = env('FFPROBE', '/usr/local/bin/ffprobe');


    $video = AttachmentModel::find($this->id);
    if (!$video) {
      return null;
    }

//
    $file = public_path($video->path);//$video->path; //'public\uploads\models\2016\03\17\original-5b4e6b066a-Star-Wars-Episode-VII.mp4';// . $video->path;
        if(!file_exists($file)){
          $video->status = AttachmentModel::ERROR;
        }else{
          $uploadPath = public_path('uploads/models/' . Carbon::now()->format('Y/m/d')); // upload path
          //HD video and picture
          $saveURL = 'uploads/models/' . Carbon::now()->format('Y/m/d'); // upload path
          //create dir if not exist
          if(!is_dir($uploadPath)){
            $oldmask = umask(0);
            mkdir($uploadPath, 0777, true);
            umask($oldmask);            
          }
          //HD video and picture

          $output_file_mp4 = "/video-hd-" . MD5(time()) . '-' . $video->owner_id . '-' . $video->id . ".mp4";
          $output_file_frame = '/image-frame-hd-' . MD5(time()) . '-' . $video->owner_id . '-' . $video->id;
          //SD Video and picture
          $output_file_mp4_sd = "/video-sd-" . MD5(time()) . '-' . $video->owner_id . '-' . $video->id . ".mp4";
          $output_file_frame_sd = '/image-frame-sd-' . MD5(time()) . '-' . $video->owner_id . '-' . $video->id;


          $ffmpeg = FFMpeg::create([
              'ffmpeg.binaries' => $ffmpeg,
              'ffprobe.binaries' => $ffprobe
          ]);
          $ffprobe = FFProbe::create([
              //TODO - move to config
              'ffprobe.binaries' => $ffprobe
          ]);
            /* [
              'ffprobe.binaries' => '/opt/ffmpeg/bin/ffprobe', // the path to the FFProbe binary
              ] */

          //process convert default or HD video
          //


          $ffprobe = $ffprobe
            ->streams($file) // extracts streams informations
            ->videos()                      // filters video streams
            ->first();                       // returns the first video stream
          //
            $dimension = $ffprobe->getDimensions();              // returns a FFMpeg\Coordinate\Dimension object
          $width = $dimension->getWidth();
          $height = $dimension->getHeight();
          $ratio = $width / $height;

          $video->dimensions = $width . '*' . $height;
          //
          //TODO convert to sd if video is HD or Full HD

          if ($height >= 720) {
            $movie = $ffmpeg->open($file);
            $movie->filters()
              ->resize(new \FFMpeg\Coordinate\Dimension(1080, 720), 'height', true)
              ->synchronize();
            //get 5 image to set in view

            $frame1 = $movie->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(1));
            $frame1->save($uploadPath . $output_file_frame . '.png');
            //Test on window localhost

              $format = new \FFMpeg\Format\Video\X264();
              $format->setAudioCodec('libmp3lame');

              $movie->save($format, $uploadPath . $output_file_mp4);

          }
          //process sd video
          $sdVideo = $ffmpeg->open($file);
          $sdVideo
            ->filters()
            ->resize(new \FFMpeg\Coordinate\Dimension(480, 360), 'height', true)
            ->synchronize();
          $sdVideo
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(3))
            ->save($uploadPath . $output_file_frame_sd . '.png');


            $format = new \FFMpeg\Format\Video\X264();
            $format->setAudioCodec('libmp3lame');
            $sdVideo->save($format, $uploadPath . $output_file_mp4_sd);


          $duration = $ffprobe->get('duration');
          $hdVideoArr = array(
            'mp4' => (file_exists($uploadPath . $output_file_mp4)) ? $saveURL . $output_file_mp4 : '',
            'frame' => (file_exists($uploadPath . $output_file_frame . '.png')) ? $saveURL . $output_file_frame . '.png' : ''
          );
          $sdVideoArr = array(
            'mp4' => (file_exists($uploadPath . $output_file_mp4_sd)) ? $saveURL . $output_file_mp4_sd : '',
            'frame' => $saveURL . $output_file_frame_sd . '.png'
          );
          $videoMeta = array(
            'original' => (file_exists($file)) ? $video->path : '',
            'hd' => array_filter($hdVideoArr),
            'sd' => array_filter($sdVideoArr),
            'dimension' => $width . '*' . $height,
            'duration' => $duration
          );

          $video->status = AttachmentModel::ACTIVE;
          $video->mediaMeta = serialize(array_filter($videoMeta));
        }
        
        if (!$video->save()) {
          //TODO check convert all video and update video status
            echo 'Save error';
        }else{
            echo 'Convert successfully';
        }
  }

}
