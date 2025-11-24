<?php

namespace App\Helpers;

class FileUploadHelper
{
    private const UPLOAD_DIR = __DIR__ . '/../storage/upload';

    private const MAX_FILE_SIZE = 5242880;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'video/mp4',
        'video/webm',
    ];

    private const THUMB_WIDTH = 300;
    private const THUMB_HEIGHT = 300;

    
    public static function upload(array $file, string $module): string|false
    {
        if (!self::validate($file)) {
            return false;
        }

        $uuid = self::generateUuid();
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "{$uuid}.{$extension}";

        $date = date('Y/m/d');
        $relativePath = "{$module}/{$date}";
        $absolutePath = self::UPLOAD_DIR . "/{$relativePath}";

        if (!is_dir($absolutePath)) {
            mkdir($absolutePath, 0755, true);
        }

        $finalPath = "{$absolutePath}/{$filename}";
        if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
            return false;
        }

        $mimeType = mime_content_type($finalPath);
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            self::generateThumbnail($finalPath, "{$absolutePath}/{$uuid}_thumb.{$extension}");
        }

        return "{$relativePath}/{$filename}";
    }

    
    private static function validate(array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return false;
        }

        return true;
    }

    
    private static function generateThumbnail(string $sourcePath, string $thumbPath): bool
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        [$width, $height, $type] = $imageInfo;

        $source = match($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default => null
        };

        if (!$source) {
            return false;
        }

        $ratio = min(self::THUMB_WIDTH / $width, self::THUMB_HEIGHT / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $result = match($type) {
            IMAGETYPE_JPEG => imagejpeg($thumb, $thumbPath, 85),
            IMAGETYPE_PNG => imagepng($thumb, $thumbPath, 8),
            IMAGETYPE_GIF => imagegif($thumb, $thumbPath),
            IMAGETYPE_WEBP => imagewebp($thumb, $thumbPath, 85),
            default => false
        };

        imagedestroy($source);
        imagedestroy($thumb);

        return $result;
    }

    
    public static function delete(string $relativePath): bool
    {
        if (empty($relativePath)) {
            return false;
        }

        $absolutePath = self::UPLOAD_DIR . '/' . $relativePath;

        $deleted = false;
        if (file_exists($absolutePath)) {
            $deleted = unlink($absolutePath);
        }

        $pathInfo = pathinfo($absolutePath);
        $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];

        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        return $deleted;
    }

    
    public static function getAbsolutePath(string $relativePath): string
    {
        return self::UPLOAD_DIR . '/' . $relativePath;
    }

    
    public static function exists(string $relativePath): bool
    {
        return file_exists(self::getAbsolutePath($relativePath));
    }

    
    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    
    public static function getAllowedTypesLabel(): string
    {
        return 'Imagens (JPG, PNG, GIF, WebP, SVG), Documentos (PDF, DOC, DOCX), Planilhas (XLS, XLSX), Áudio (MP3, WAV, OGG), Vídeo (MP4, WebM)';
    }

    
    public static function getMaxSizeLabel(): string
    {
        return '5 MB';
    }
}
