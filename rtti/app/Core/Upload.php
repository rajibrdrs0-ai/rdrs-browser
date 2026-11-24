<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Secure file upload: extension + real MIME check, size limit, random file name,
 * stored outside the web root (storage/uploads) and served via FileController.
 */
final class Upload
{
    public const IMAGE = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    public const DOCS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

    /**
     * @return array{path:string,original:string,mime:string,size:int}|null  null when no file was sent
     * @throws \RuntimeException on invalid upload
     */
    public static function store(string $field, string $folder, array $allowed = self::DOCS, ?int $maxMb = null): ?array
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f = $_FILES[$field];
        if (is_array($f['name'])) {
            throw new \RuntimeException('Invalid upload.');
        }
        return self::handle($f, $folder, $allowed, $maxMb);
    }

    /** Handle multiple files from <input name="x[]" multiple> */
    public static function storeMany(string $field, string $folder, array $allowed = self::DOCS): array
    {
        $out = [];
        if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
            return $out;
        }
        foreach ($_FILES[$field]['name'] as $i => $name) {
            if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = self::handle([
                'name' => $name, 'tmp_name' => $_FILES[$field]['tmp_name'][$i], 'error' => $_FILES[$field]['error'][$i],
                'size' => $_FILES[$field]['size'][$i],
            ], $folder, $allowed, null);
        }
        return $out;
    }

    private static function handle(array $f, string $folder, array $allowed, ?int $maxMb): array
    {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed (error code ' . $f['error'] . ').');
        }
        $maxMb ??= (int)config('upload_max_mb', 5);
        if ($f['size'] > $maxMb * 1024 * 1024) {
            throw new \RuntimeException("File is too large. Maximum size is {$maxMb} MB.");
        }
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            throw new \RuntimeException('File type not allowed. Allowed: ' . strtoupper(implode(', ', array_keys($allowed))) . '.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if (!in_array($mime, array_values($allowed), true)) {
            throw new \RuntimeException('File content does not match its extension.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($f['tmp_name']) === false) {
            throw new \RuntimeException('Invalid image file.');
        }
        $folder = preg_replace('/[^a-z0-9_\/-]/i', '', $folder);
        $dir = STORAGE_PATH . '/uploads/' . $folder . '/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload folder.');
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        $moved = is_uploaded_file($f['tmp_name']) ? move_uploaded_file($f['tmp_name'], $dest) : rename($f['tmp_name'], $dest);
        if (!$moved) {
            throw new \RuntimeException('Could not save uploaded file.');
        }
        return [
            'path' => $folder . '/' . date('Y/m') . '/' . $name,
            'original' => mb_substr(basename((string)$f['name']), 0, 255),
            'mime' => $mime,
            'size' => (int)$f['size'],
        ];
    }

    public static function absolute(string $relative): ?string
    {
        $base = realpath(STORAGE_PATH . '/uploads');
        $full = realpath(STORAGE_PATH . '/uploads/' . $relative);
        if (!$base || !$full || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $full;
    }
}
