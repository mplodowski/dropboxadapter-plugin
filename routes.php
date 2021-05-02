<?php

$dropboxDisks = array_where(config('cms.storage'), function ($disk) {
    return $disk['disk'] === 'dropbox';
});

/**
 * Registers routes for each dropbox disk proxying thro' the dropbox share link API.
 */
foreach ($dropboxDisks as $dropboxDisk) {
    $diskFolder = $dropboxDisk['folder'];
    if (!empty($diskFolder)) {
        Route::get('storage/' . $diskFolder . '/{path}', function ($path) use ($diskFolder) {
            $params = [
                'path'      => '/' . $diskFolder . '/' . $path,
                'short_url' => false
            ];
            $headers = [
                'Authorization' => 'Bearer ' . config('filesystems.disks.dropbox.authorizationToken'),
                'Content-Type'  => 'application/json'
            ];
            $out = Http::post('https://api.dropboxapi.com/2/sharing/create_shared_link', function ($http) use ($headers, $params) {
                $http->header($headers);
                $http->setOption(CURLOPT_POSTFIELDS, json_encode($params));
            });
            $response = json_decode($out, true);
            if ($out->code !== 200) {
                return Redirect::to('404');
            }
            return Redirect::to(explode('?', $response['url'])[0] . '?raw=1');
        })->where('path', '.*');
    }
}
