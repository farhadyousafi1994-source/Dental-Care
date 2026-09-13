<?php
namespace App\Domains\Media;
/** Bounded raster transforms. Originals are never modified. Requires GD; JPEG orientation uses EXIF when available. */
final class RasterEditor {
    public function transform(string $path, array $options): array {
        abort_unless(extension_loaded('gd'), 503, 'Enable the PHP GD extension to edit images.');
        $info = @getimagesize($path);
        abort_unless($info && in_array($info['mime'], ['image/jpeg','image/png','image/webp']), 422, 'Edit a JPG, PNG or WEBP file uploaded to this website.');
        abort_if($info[0] * $info[1] > 16000000 || filesize($path) > 20971520, 422, 'Image editing is limited to 16 megapixels and 20 MB. Resize very large originals first.');
        $image = @imagecreatefromstring(file_get_contents($path));
        abort_unless($image, 422, 'The image could not be decoded.');
        $output = null;
        try {
            if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
                $orientation = (@exif_read_data($path)['Orientation']) ?? 1;
                if (in_array($orientation, [2,4,5,7])) imageflip($image, in_array($orientation,[2,5]) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
                $angle = match ((int) $orientation) {3 => 180, 5,6 => -90, 7,8 => 90, default => 0};
                if ($angle) { $rotated = imagerotate($image, $angle, 0); imagedestroy($image); $image = $rotated; }
            }
            $w = imagesx($image); $h = imagesy($image);
            $x = (int) floor($options['x'] / 100 * $w); $y = (int) floor($options['y'] / 100 * $h);
            $cw = max(1, (int) floor($options['crop_width'] / 100 * $w)); $ch = max(1, (int) floor($options['crop_height'] / 100 * $h));
            $width = (int) $options['width']; $height = max(1, (int) round($width * $ch / $cw));
            abort_if($height > 4096 || $width * $height > 4000000, 422, 'Output must be at most 4096 pixels per edge and 4 megapixels.');
            $output = imagecreatetruecolor($width, $height);
            imagealphablending($output, $options['format'] === 'jpeg'); imagesavealpha($output, $options['format'] !== 'jpeg');
            $fill = imagecolorallocatealpha($output, 255, 255, 255, $options['format'] === 'jpeg' ? 0 : 127);
            imagefill($output, 0, 0, $fill);
            imagecopyresampled($output, $image, 0, 0, $x, $y, $width, $height, $cw, $ch);
            ob_start();
            $ok = match ($options['format']) {
                'jpeg' => imagejpeg($output, null, $options['quality']),
                'webp' => function_exists('imagewebp') && imagewebp($output, null, $options['quality']),
                'png' => imagepng($output, null, 9),
            };
            $bytes = ob_get_clean();
            abort_unless($ok && $bytes, 422, 'This image format is not supported by your PHP GD build.');
            return ['bytes' => $bytes, 'width' => $width, 'height' => $height, 'mime' => 'image/'.$options['format']];
        } finally { imagedestroy($image); if ($output) imagedestroy($output); }
    }
}
