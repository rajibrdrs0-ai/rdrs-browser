<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Upload;

/**
 * Serves uploaded files (stored outside the web root) after an access check.
 */
final class FileController extends Controller
{
    public function show(): void
    {
        $rel = (string)input('f', '');
        if ($rel === '' || str_contains($rel, '..')) {
            $this->notFound('File');
        }
        $this->checkAccess($rel);
        $path = Upload::absolute($rel);
        if (!$path) {
            $this->notFound('File');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $download = input('dl') === '1';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }

    /** Find which record owns the file and verify center access */
    private function checkAccess(string $rel): void
    {
        $folder = explode('/', $rel)[0];
        $center = null;
        switch ($folder) {
            case 'photos':
                $center = DB::value('SELECT center_id FROM students WHERE photo = :f', ['f' => $rel])
                    ?? DB::value('SELECT center_id FROM staff WHERE photo = :f', ['f' => $rel]);
                break;
            case 'documents':
                $this->authorize('documents.view');
                $center = DB::value('SELECT s.center_id FROM student_documents d JOIN students s ON s.id = d.student_id WHERE d.file_path = :f', ['f' => $rel]);
                break;
            case 'expenses':
                $this->authorize('expenses.view');
                $center = DB::value('SELECT center_id FROM expenses WHERE supporting_document = :f', ['f' => $rel]);
                break;
            case 'assignments':
                $this->authorize('assignments.view');
                $center = DB::value('SELECT b.center_id FROM assignment_submissions s JOIN enrollments e ON e.id = s.enrollment_id JOIN batches b ON b.id = e.batch_id WHERE s.file_path = :f', ['f' => $rel]);
                if ($center === null) {
                    // assignment attachment – visible to anyone allowed to view assignments
                    if (DB::exists('SELECT 1 FROM assignments WHERE attachment = :f', ['f' => $rel])) {
                        return;
                    }
                }
                break;
            case 'courses':
                $this->authorize('courses.view');
                return;
            case 'settings':
                return;
            default:
                $this->notFound('File');
        }
        if ($center === null) {
            $this->notFound('File');
        }
        if (!Auth::canAccessCenter((int)$center)) {
            $this->deny('You cannot access this file.');
        }
    }
}
