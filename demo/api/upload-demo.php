<?php
/**
 * Example endpoint for the `upload` option of a `richtext` field.
 *
 * The shape below is the contract — CKEditor's SimpleUploadAdapter, not
 * something GridKit invented. The file arrives as a plain multipart POST under
 * the key `upload`, and the answer is one of:
 *
 *   { "url": "https://example.com/uploads/2026/09/photo.jpg" }
 *   { "error": { "message": "Human-readable reason" } }
 *
 * Return an ABSOLUTE url. A relative one looks right on the page and breaks the
 * moment the content is sent as an e-mail: the reader's mail client fetches the
 * picture and knows nothing about which host wrote it.
 *
 * THIS demo stores nothing. A public endpoint that writes whatever it is handed
 * is a file drop for the whole internet, so it declines every upload and says
 * so. Everything else — the toolbar button, drag & drop, paste, the size
 * handles on a picture already in the text — works and can be tried above.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'error' => [
        'message' => 'This is the live demo — uploads are not stored here. '
                   . 'In your own application this endpoint saves the file and '
                   . 'answers { "url": "https://…" }. The picture already in the '
                   . 'text can be selected and resized to try the rest.',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
