<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\HttpException;

/**
 * Safe file upload handling: verifies the real MIME type with finfo,
 * enforces size limits, generates random file names and stores files
 * outside the web root (storage/uploads/<folder>).
 */
final class FileUpload
{
    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param array<string,string> $allowed mime => extension
     * @return string path relative to storage/uploads
     */
    public static function store(array $file, string $folder, array $allowed, ?int $maxBytes = null): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'File upload failed (code ' . (int) ($file['error'] ?? -1) . ').');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new HttpException(400, 'Invalid upload.');
        }
        $maxBytes ??= (int) Config::get('app.upload_max_mb', 5) * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            throw new HttpException(413, 'File is too large.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
        if (!isset($allowed[$mime])) {
            throw new HttpException(422, 'File type not allowed: ' . $mime);
        }

        $folder = trim(preg_replace('/[^a-z0-9_\/-]/i', '', $folder) ?? '', '/');
        $targetDir = BASE_PATH . '/storage/uploads/' . $folder . '/' . date('Y/m');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $name)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }
        @chmod($targetDir . '/' . $name, 0640);

        return $folder . '/' . date('Y/m') . '/' . $name;
    }

    /** Store a base64 data-URL image (signature pad / camera capture). */
    public static function storeDataUrl(string $dataUrl, string $folder, int $maxBytes = 2_097_152): string
    {
        if (!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new HttpException(422, 'Invalid image data.');
        }
        $binary = base64_decode($m[2], true);
        if ($binary === false || strlen($binary) > $maxBytes) {
            throw new HttpException(422, 'Image is invalid or too large.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $map = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($map[$mime])) {
            throw new HttpException(422, 'Image type not allowed.');
        }
        $folder = trim(preg_replace('/[^a-z0-9_\/-]/i', '', $folder) ?? '', '/');
        $targetDir = BASE_PATH . '/storage/uploads/' . $folder . '/' . date('Y/m');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $map[$mime];
        file_put_contents($targetDir . '/' . $name, $binary, LOCK_EX);
        return $folder . '/' . date('Y/m') . '/' . $name;
    }

    /** Resolve a stored relative path safely (no traversal outside storage/uploads). */
    public static function absolutePath(string $relative): string
    {
        $base = realpath(BASE_PATH . '/storage/uploads');
        $full = realpath(BASE_PATH . '/storage/uploads/' . ltrim($relative, '/'));
        if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            throw new HttpException(404);
        }
        return $full;
    }
}
