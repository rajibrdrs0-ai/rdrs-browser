<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Upload;

final class DocumentsController extends Controller
{
    public function index(): void
    {
        $this->authorize('documents.view');
        [$where, $params] = $this->centerFilter('s.center_id');
        if ($q = input('q')) {
            $where .= ' AND (s.student_code LIKE :q OR s.name LIKE :q2 OR d.title LIKE :q3)';
            $params += ['q' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if ($c = input_id('category_id')) {
            $where .= ' AND d.document_category_id = :c';
            $params['c'] = $c;
        }
        if (in_array($v = input('verification'), ['pending', 'verified', 'rejected'], true)) {
            $where .= ' AND d.verification_status = :v';
            $params['v'] = $v;
        }
        $sql = "SELECT d.*, dc.name category, s.id sid, s.student_code, s.name student, ctr.name center, u.name uploader, vu.name verifier
            FROM student_documents d JOIN students s ON s.id = d.student_id JOIN centers ctr ON ctr.id = s.center_id
            JOIN document_categories dc ON dc.id = d.document_category_id LEFT JOIN users u ON u.id = d.uploaded_by LEFT JOIN users vu ON vu.id = d.verified_by
            WHERE d.deleted_at IS NULL AND s.deleted_at IS NULL $where ORDER BY d.id DESC";
        $this->render('documents/index', ['title' => 'Student Documents', 'p' => DB::paginate($sql, $params),
            'categories' => DB::all('SELECT id, name FROM document_categories WHERE is_active = 1 ORDER BY name'),
            'breadcrumbs' => ['Students' => url('students/index'), 'Documents' => null]]);
    }

    public function store(): void
    {
        $this->requirePost();
        $this->authorize('documents.create');
        $sid = input_int('student_id');
        $s = DB::row('SELECT * FROM students WHERE id = :id AND deleted_at IS NULL', ['id' => $sid]) ?? $this->notFound('Student');
        $this->authorizeCenter((int)$s['center_id']);
        $cat = input_id('document_category_id');
        if (!$cat || !DB::exists('SELECT 1 FROM document_categories WHERE id = :id', ['id' => $cat])) {
            $this->fail('Select a document category.');
        }
        try {
            $files = Upload::storeMany('files', 'documents', Upload::DOCS);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        if (!$files) {
            $this->fail('Choose at least one file (PDF, JPG, PNG).');
        }
        $catName = DB::value('SELECT name FROM document_categories WHERE id = :id', ['id' => $cat]);
        foreach ($files as $f) {
            $id = DB::insert('student_documents', [
                'student_id' => $sid, 'document_category_id' => $cat, 'title' => input('title') ?: ($catName . ' – ' . $f['original']),
                'file_path' => $f['path'], 'original_name' => $f['original'], 'mime_type' => $f['mime'], 'file_size' => $f['size'], 'uploaded_by' => Auth::id(),
            ]);
            Audit::log('documents', 'upload', $id, "Document uploaded for {$s['student_code']}: {$f['original']}", null, null, (int)$s['center_id']);
        }
        flash('success', count($files) . ' document(s) uploaded.');
        redirect(input('back') === 'view' ? url('students/view', ['id' => $sid]) . '#tDocs' : url('documents/index'));
    }

    private function loadDoc(): array
    {
        $d = DB::row('SELECT d.*, s.center_id, s.student_code FROM student_documents d JOIN students s ON s.id = d.student_id WHERE d.id = :id AND d.deleted_at IS NULL', ['id' => input_int('id')]) ?? $this->notFound('Document');
        $this->authorizeCenter((int)$d['center_id']);
        return $d;
    }

    public function verify(): void
    {
        $this->requirePost();
        $this->authorize('documents.verify');
        $d = $this->loadDoc();
        $status = input('status') === 'rejected' ? 'rejected' : 'verified';
        DB::update('student_documents', ['verification_status' => $status, 'verified_by' => Auth::id(), 'verified_at' => date('Y-m-d H:i:s'), 'remarks' => nullify(input('reason'))], 'id = :id', ['id' => $d['id']]);
        Audit::log('documents', $status, $d['id'], "Document $status for {$d['student_code']}: {$d['title']}", null, null, (int)$d['center_id']);
        flash('success', "Document marked as $status.");
        back();
    }

    public function delete(): void
    {
        $this->requirePost();
        $this->authorize('documents.delete');
        $d = $this->loadDoc();
        DB::update('student_documents', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $d['id']]);
        Audit::log('documents', 'delete', $d['id'], "Document deleted for {$d['student_code']}: {$d['title']} – " . input('reason'), $d, null, (int)$d['center_id']);
        flash('success', 'Document removed.');
        back();
    }
}
