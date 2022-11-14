<?php

namespace Core;

class ImageMerger {

    private $Img = NULL;
    private $Width = NULL;
    private $Height = NULL;

    /**
     * Cretaes a new image with a certain width and height
     * @param $width The width of the image
     * @param $height The eight of the image
     */
    public function __construct(int $width, int $height) {

        // store attributes
        $this->Width = $width;
        $this->Height = $height;

        // create image
        $this->Img = imagecreatetruecolor($this->Width, $this->Height);

        // enable transparency
        imagesavealpha($this->Img, TRUE);
        $trans_colour = imagecolorallocatealpha($this->Img, 0, 0, 0, 127);
        imagefill($this->Img, 0, 0, $trans_colour);
    }


    /**
     * Calculates a factor whcih can be applied to width and height to fit into this image
     *
     * @param $width
     * @param $height
     * @param $overlap Set to FALSE (default) if the image shall fit completely, TRUE to fit shortest edge
     * @return The factor to multiply width and height so that they fit into max width/height (can be greater than 1.0)
     */
    private function calculateImageRescaleFactor(int $width, int $height,
                                                 bool $overlap = FALSE) : float {
        $width_factor = $this->Width / $width;
        $height_factor = $this->Height / $height;
        $compare = ($overlap) ? ($width_factor > $height_factor) : ($width_factor < $height_factor);
        $factor = ($compare) ? $width_factor : $height_factor;
        return $factor;
    }


    /**
     * Merge a source picture into this picture.
     *
     * The source picture will be rescaled and overlayed to the previous image.
     * In transparent areas of the source image, the previous image will be visible.
     *
     * @param $src_path The full qualified name of the source image
     * @param $overlap If TRUE (default), the source image will be scaled down so that width or height will fit. If FALSE, width and height will fit
     * @param $ratio This is an additional scaling factor which will be applied after the shrink-scaling.
     * @param $force_format If set (e.g. If set, eg to "png", then the src_path is interpretet in this format)
     */
    public function merge(string $src_path, bool $overlap=TRUE, $ratio=1.0, string $force_format=NULL) {
        // load source image
        $img_src = NULL;
        if (($force_format === NULL && substr($src_path, -4, 4) == ".jpg") || ($force_format === "jpg"))     {
            $img_src = imagecreatefromjpeg($src_path);
        } else if (($force_format === NULL && substr($src_path, -4, 4) == ".png") || ($force_format === "png"))     {
            $img_src = imagecreatefrompng($src_path);
        } else if ($format !== NULL) {
            \Core\Log::error("Unsupported file format '{$force_format}'!");
        } else {
            \Core\Log::error("Unsupported file format in '{$src_path}'!");
        }

        // calculate scaling factor
        $width_src = imagesx($img_src);
        $height_src = imagesy($img_src);
        $scale = $this->calculateImageRescaleFactor($width_src, $height_src, $overlap);
        $scale *= $ratio;

        // merge
        $width_dst = (int) ($width_src * $scale);
        $height_dst = (int) ($height_src * $scale);
        $dst_x = intdiv($this->Width - $width_dst, 2);
        $dst_y = intdiv($this->Height - $height_dst, 2);
        $succ = imagecopyresampled($this->Img, $img_src,
                                   $dst_x, $dst_y,
                                   0, 0,
                                   $width_dst, $height_dst,
                                   $width_src, $height_src);
    }


    /**
     * Save image in a certain format
     * The file extension determines the file format (eg foo/bar/file.png exports a png file)
     * @param $dst_path The full qualified destination path (file extension determines format)
     */
    public function save($dst_path) {

        // export PNG
        if (substr($dst_path, -4, 4) == ".png") {
            if (imagepng($this->Img, $dst_path) !== TRUE) {
                \Core\Log::error("Failed to save PNG image '{$dst_path}'");
            }

        // unkown format
        } else {
            \Core\Log::error("Unsupported file format at saving '{$dst_path}'!");
        }
    }
}
