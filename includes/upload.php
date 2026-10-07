<?php
// includes/upload.php：ファイルアップロードの共通処理
// 保存先は公開フォルダの外（UPLOAD_DIR）。ファイル名はランダムにし、元の名前は使わない。

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5MB
const UPLOAD_MIME_EXT = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'application/pdf' => 'pdf',
];

/**
 * アップロードされたファイルを検証して保存する。
 * @param array $file      $_FILES['xxx']
 * @param string $subdir   'photos' / 'documents' など
 * @param array $allowed   許可するMIMEタイプ（UPLOAD_MIME_EXTのキー）
 * @return array ['path' => 相対パス, 'mime' => ..., 'size' => ..., 'original' => ...]
 * @throws RuntimeException 画面に出してよいエラーメッセージ
 */
function save_upload(array $file, string $subdir, array $allowed): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('ファイルサイズは5MB以下にしてください。');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('ファイルのアップロードに失敗しました。もう一度お試しください。');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('ファイルサイズは5MB以下にしてください。');
    }

    // 拡張子ではなく、中身からファイル形式を判定する
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        $names = implode('・', array_map(fn($m) => strtoupper(UPLOAD_MIME_EXT[$m]), $allowed));
        throw new RuntimeException("ファイル形式は {$names} のみ使えます。");
    }

    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('保存先を作成できませんでした。');
    }
    $name = bin2hex(random_bytes(16)) . '.' . UPLOAD_MIME_EXT[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('ファイルを保存できませんでした。');
    }

    return [
        'path'     => $subdir . '/' . $name,
        'mime'     => $mime,
        'size'     => (int)$file['size'],
        'original' => mb_substr(basename((string)$file['name']), 0, 200),
    ];
}

// 保存済みファイルを削除する（パスがUPLOAD_DIRの中かも確認）
function delete_upload(?string $relPath): void
{
    if (!$relPath || str_contains($relPath, '..')) {
        return;
    }
    $full = UPLOAD_DIR . '/' . $relPath;
    if (is_file($full)) {
        @unlink($full);
    }
}
