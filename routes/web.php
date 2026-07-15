<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

Route::get('/test-r2', function () {
    $results = [];

    // 1. Upload a test file
    $content = 'Hello from Laravel — '.now();
    $path = 'test/r2-test-'.time().'.txt';

    $uploaded = Storage::put($path, $content);
    $results['upload'] = $uploaded ? 'OK' : 'FAILED';
    $results['path'] = $path;

    // 2. Check it exists
    $results['exists'] = Storage::exists($path) ? 'YES' : 'NO';

    // 3. Read it back
    $results['read_content'] = Storage::get($path);

    // 4. Get file size
    $results['size'] = Storage::size($path).' bytes';

    // 5. Get last modified
    $results['last_modified'] = date('Y-m-d H:i:s', Storage::lastModified($path));

    // 6. Generate a temporary (presigned) URL — works even on private bucket
    $results['temporary_url'] = Storage::temporaryUrl($path, now()->addMinutes(5));

    // 6b. Copy the file
    $copyPath = 'test/r2-test-copy-'.time().'.txt';
    try {
        $copied = Storage::copy($path, $copyPath);
        $results['copy'] = $copied ? 'OK' : 'FAILED';
    } catch (Exception $e) {
        $results['copy'] = 'EXCEPTION: '.$e->getMessage();
    }
    $results['copy_exists'] = Storage::exists($copyPath) ? 'YES' : 'NO';

    // 6c. Move (rename) the copy
    $movePath = 'test/r2-test-moved-'.time().'.txt';
    $moved = Storage::move($copyPath, $movePath);
    $results['move'] = $moved ? 'OK' : 'FAILED';
    $results['original_gone_after_move'] = Storage::exists($copyPath) ? 'STILL THERE (bad)' : 'GONE (good)';
    $results['moved_file_exists'] = Storage::exists($movePath) ? 'YES' : 'NO';

    // clean up the moved file too (in addition to your existing delete of $path)
    Storage::delete($movePath);

    // 7. Try the public url() too (only works if CLOUDFLARE_R2_URL is set + public access enabled)
    try {
        $results['public_url'] = Storage::url($path);
    } catch (Exception $e) {
        $results['public_url'] = 'N/A — '.$e->getMessage();
    }

    // 8. List files in test/ directory
    $results['files_in_test_dir'] = Storage::files('test');

    // 9. Delete the test file
    $deleted = Storage::delete($path);
    $results['delete'] = $deleted ? 'OK' : 'FAILED';

    // 10. Confirm deletion
    $results['exists_after_delete'] = Storage::exists($path) ? 'STILL THERE (bad)' : 'GONE (good)';

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
