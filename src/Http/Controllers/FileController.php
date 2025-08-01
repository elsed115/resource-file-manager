<?php

namespace Elsed\ResourceFileManager\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Illuminate\Support\Str;
use App\Models\Work;
use Laravel\Nova\Nova;
use Aws\S3\S3Client;

class FileController extends Controller
{
    /**
     * Get the resource model instance from the request.
     *
     * @param NovaRequest $request
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getResourceModel(NovaRequest $request)
    {
        $resourceName = $request->input('resourceName');
        $resourceId = $request->input('resourceId');

        // Automatizza la risoluzione del model
        $modelClass = '\\App\\Models\\' . Str::studly(Str::singular($resourceName));

        if (!class_exists($modelClass)) {
            abort(404, 'Resource model not found.');
        }

        $model = (new $modelClass)->findOrFail($resourceId);

        if (!method_exists($model, 'resourceFileManagerDisk') || !method_exists($model, 'resourceFileManagerRootPath')) {
            abort(500, 'The resource model must implement resourceFileManagerDisk() and resourceFileManagerRootPath() methods.');
        }

        return $model;
    }

    /**
     * Elenca file e cartelle in un dato percorso, garantendo la sicurezza.
     */
    public function list(NovaRequest $request)
    {

        try {
            $model = $this->getResourceModel($request);
            $diskName = $model->resourceFileManagerDisk();
            $rootPath = $model->resourceFileManagerRootPath();
            $requestedPath = $request->input('path', '');
            $page = $request->input('page', 1);
            $perPage = $request->input('perPage', 15);
            $search = $request->input('search', ''); // Aggiunto per la ricerca

            // Security check: Prevent directory traversal
            if (Str::contains($requestedPath, '..')) {
                abort(403, 'Invalid path.');
            }

            // Always construct path from the root to prevent escaping
            if (Str::startsWith($requestedPath, $rootPath)) {
                $fullPath = rtrim($requestedPath, '/');
            } else {
                $fullPath = rtrim($rootPath . '/' . $requestedPath, '/');
            }

            // Recupera i file
            $files = collect(Storage::disk($diskName)->files($fullPath))
                ->map(function ($file) use ($diskName) {
                    try {
                        // Recupera i tag
                        $tags = $this->getFileTags($diskName, $file);
                        $mimetype = Storage::disk($diskName)->mimeType($file);

                        // Crea l'URL temporaneo
                        $url = Storage::disk($diskName)->temporaryUrl($file, now()->addMinutes(5));

                        return [
                            'name' => basename($file),
                            'path' => $file,
                            'type' => 'file',
                            'size' => Storage::disk($diskName)->size($file),
                            'last_modified' => Storage::disk($diskName)->lastModified($file),
                            'url' => $url,
                            'tags' => $tags ?: '-',  // Imposta '-' se non ci sono tag
                            'mimetype' => $mimetype,
                        ];
                    } catch (UnableToRetrieveMetadata $e) {
                        return null;
                    } catch (\Exception $e) {
                        return null;
                    }
                })->filter();

            // Recupera le cartelle
            $directories = collect(Storage::disk($diskName)->directories($fullPath))
                ->map(function ($dir) {
                    return [
                        'name' => basename($dir),
                        'path' => $dir,
                        'type' => 'folder',
                        'tags' => '-',  // Imposta '-' per le cartelle
                        'mimetype' => 'folder',
                    ];
                });


            // Combina file e cartelle
            $allItems = $files->merge($directories);

            // Filtra per ricerca
            if (!empty($search)) {
                $allItems = $allItems->filter(function ($item) use ($search) {
                    return Str::contains(strtolower($item['name']), strtolower($search));
                });
            }

            // Filtra per tag
            $filterTag = $request->input('filter_tag');
            if ($filterTag) {
                $allItems = $allItems->filter(function ($item) use ($filterTag) {
                    if ($item['type'] === 'folder') {
                        return true; // Mantieni sempre le cartelle
                    }
                    return $item['tags'] === $filterTag;
                });
            }

            // Filtra per mimetype
            $filterMimetype = $request->input('filter_mimetype');
            if ($filterMimetype) {
                $allItems = $allItems->filter(function ($item) use ($filterMimetype) {
                     if ($item['type'] === 'folder') {
                        return true; // Mantieni sempre le cartelle
                    }
                    return $item['mimetype'] === $filterMimetype;
                });
            }


            // ordina per cartelle prima, poi per nome e applica la paginazione
            $allItems = $allItems->sortBy(function($item) {
                return ($item['type'] === 'folder' ? '0' : '1') . $item['name'];
            })->values();
            
            $total = $allItems->count();
            $paginatedItems = $allItems->forPage($page, $perPage)->values();

            // Risposta finale con paginazione e breadcrumb
            $responsePayload = [
                'files' => $paginatedItems,
                'breadcrumbs' => $this->generateBreadcrumbs($rootPath, $requestedPath),
                'pagination' => [
                    'currentPage' => (int)$page,
                    'perPage' => (int)$perPage,
                    'total' => $total,
                    'lastPage' => ceil($total / $perPage),
                ],
            ];


            return response()->json($responsePayload);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Resource not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred on the server.'], 500);
        }
    }

    /**
     * Recupera i tag associati a un file su S3/MinIO
     */
    private function getFileTags($diskName, $file)
    {
        try {
            // Ottieni la configurazione del disco
            $cfg = config("filesystems.disks.$diskName");

            // Crea l'istanza del client S3
            $client = new S3Client([
                'version' => 'latest',
                'region' => $cfg['region'],
                'endpoint' => $cfg['endpoint'],  // Usa l'endpoint configurato
                'credentials' => [
                    'key' => $cfg['key'],
                    'secret' => $cfg['secret'],
                ],
                'use_path_style_endpoint' => true,
            ]);

            $bucket = $cfg['bucket'];

            // Recupera i tag
            $result = $client->getObjectTagging([
                'Bucket' => $bucket,
                'Key'    => $file,
            ]);

            // Estrai e restituisci solo il valore del tag 'type'
            $tags = collect($result->get('TagSet'))
                ->filter(function($tag) {
                    return $tag['Key'] === 'type';
                })
                ->pluck('Value')
                ->first();  // Restituisce il primo valore trovato

            return $tags;  // Restituisce il valore direttamente come stringa

        } catch (\Aws\Exception\AwsException $e) {
            return null;  // Se c'è un errore, ritorna null
        } catch (\Exception $e) {
            return null;
        }
    }


    public function generateTempLink(NovaRequest $request)
    {
        $path = $request->get('path');
        $resourceName = $request->get('resourceName');
        $resourceId = $request->get('resourceId');
        $expires = now()->addMinutes(5); // valido 10 minuti

        $token = bin2hex(random_bytes(16));
        cache()->put("file_token:$token", [
            'path' => $path,
            'resourceName' => $resourceName,
            'resourceId' => $resourceId,
            'expires' => $expires
        ], $expires);

        return response()->json([
            'url' => url("/api/rfm/proxy-download?token=$token")
        ]);
    }

    public function proxyDownload(NovaRequest $request)
    {
        $token = $request->get('token');
        $data = cache()->get("file_token:$token");

        if (!$data || now()->greaterThan($data['expires'])) {
            abort(403, 'Link scaduto o non valido');
        }

        // Crea una NovaRequest fittizia con i parametri necessari
        $fakeRequest = NovaRequest::create('', 'GET', [
            'resourceName' => $data['resourceName'],
            'resourceId' => $data['resourceId'],
        ]);
        $model = $this->getResourceModel($fakeRequest);

        $diskName = $model->resourceFileManagerDisk();
        $path = $data['path'];
        $filename = basename($path);

        if (!Storage::disk($diskName)->exists($path)) {
            abort(404, 'File not found');
        }

        $stream = Storage::disk($diskName)->readStream($path);
        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => Storage::disk($diskName)->mimeType($path),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
    /**
     * Carica un file nel percorso specificato.
     */
    public function upload(NovaRequest $request)
    {
        try {
            $request->validate([
                'file' => 'required|file',
                'path' => 'nullable|string',
                'resourceName' => 'required|string',
                'resourceId' => 'required',
            ]);

            $model = $this->getResourceModel($request);
            $diskName = $model->resourceFileManagerDisk();
            $rootPath = $model->resourceFileManagerRootPath();
            $uploadPath = $request->input('path', '');

            if (Str::contains($uploadPath, '..')) {
                abort(403, 'Invalid path.');
            }

            if (Str::startsWith($uploadPath, $rootPath)) {
                $realUploadPath = rtrim($uploadPath, '/');
            } else {
                $realUploadPath = rtrim($rootPath . '/' . $uploadPath, '/');
            }
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();

            Storage::disk($diskName)->putFileAs($realUploadPath, $file, $fileName);

            return response()->json(['success' => true, 'path' => $uploadPath]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Resource not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred on the server.'], 500);
        }
    }


    /**
     * Crea una nuova cartella.
     */
    public function createFolder(NovaRequest $request)
    {
        try {
            $request->validate(['folderName' => 'required|string|max:255']);

            $model = $this->getResourceModel($request);
            $diskName = $model->resourceFileManagerDisk();
            $rootPath = $model->resourceFileManagerRootPath();
            $currentPath = $request->input('path', '');
            $newFolderName = $request->input('folderName');

            if (Str::contains($currentPath, '..') || Str::contains($newFolderName, ['/', '\\', '..'])) {
                abort(403, 'Invalid path or folder name.');
            }

            if (Str::startsWith($currentPath, $rootPath)) {
                $realFolderPath = rtrim($currentPath, '/') . '/' . $newFolderName;
            } else {
                $realFolderPath = rtrim($rootPath . '/' . $currentPath, '/') . '/' . $newFolderName;
            }

            Storage::disk($diskName)->makeDirectory($realFolderPath);

            return response()->json(['success' => true]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Resource not found.'], 404);
        } catch (\Exception $e) {  
            return response()->json(['error' => 'An unexpected error occurred on the server.'], 500);
        }
    }

    private function isFolder(string $key, string $disk): bool
{
    $d = Storage::disk($disk);

    // se il prefisso o il marker esistono, è cartella
    return $d->directoryExists($key) || $d->directoryExists($key . '/');
}

private function s3Client(string $disk): array
{
    $cfg = config("filesystems.disks.$disk");

    $client = new S3Client([
        'version'                 => 'latest',
        'region'                  => $cfg['region']   ?? 'us-east-1',
        'endpoint'                => $cfg['endpoint'] ?? null,
        'use_path_style_endpoint' => $cfg['use_path_style_endpoint'] ?? false,
        'credentials'             => [
            'key'    => $cfg['key'],
            'secret' => $cfg['secret'],
        ],
    ]);

    return [$client, $cfg['bucket']];
}

public function delete(NovaRequest $request)
{
    $request->validate(['path' => 'required|string']);

    $model    = $this->getResourceModel($request);
    $diskName = $model->resourceFileManagerDisk();
    $disk     = Storage::disk($diskName);
    [$s3, $bucket] = $this->s3Client($diskName);

    $key     = trim($request->input('path'), '/');   // es. user_files/1/aa
    $prefix  = Str::finish($key, '/');

    /* -------- FILE -------- */
    if ($disk->fileExists($key)) {
        $s3->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
        return response()->json(['message' => 'Deleted file'], 200);
    }

    /* -------- CARTELLA -------- */

    // 1. elimina subito i possibili marker
    foreach ([$key, $key . '/'] as $marker) {
        $s3->deleteObject(['Bucket' => $bucket, 'Key' => $marker]);
    }

    // 2. enumera e cancella ogni oggetto sotto il prefisso (uno per volta → no MD5)
    $paginator = $s3->getPaginator('ListObjectsV2', [
        'Bucket' => $bucket,
        'Prefix' => $prefix,
    ]);

    foreach ($paginator as $page) {
        if (!empty($page['Contents'])) {
            foreach ($page['Contents'] as $obj) {
                $s3->deleteObject(['Bucket' => $bucket, 'Key' => $obj['Key']]);
            }
        }
    }

    return response()->json(['message' => 'Deleted folder'], 200);
}

public function rename(NovaRequest $request)
{
    $request->validate([
        'path'    => 'required|string',
        'newName' => 'required|string',
    ]);

    $model    = $this->getResourceModel($request);
    $diskName = $model->resourceFileManagerDisk();
    $disk     = Storage::disk($diskName);
    [$s3, $bucket] = $this->s3Client($diskName);

    $oldKey = trim($request->input('path'), '/');                // user_files/1/aa
    $newKey = Str::finish(trim(dirname($oldKey), '/'), '/') . trim($request->input('newName'));
    $src    = Str::finish($oldKey, '/');
    $dst    = Str::finish($newKey, '/');

    /* -------- FILE -------- */
    if ($disk->fileExists($oldKey)) {
        // copia e poi cancella l’originale
        $s3->copyObject([
            'Bucket'     => $bucket,
            'Key'        => $newKey,
            'CopySource' => "$bucket/$oldKey",
        ]);
        $s3->deleteObject(['Bucket' => $bucket, 'Key' => $oldKey]);

        return response()->json(['message' => 'Renamed file'], 200);
    }

    /* -------- CARTELLA -------- */

    // crea subito il marker della nuova cartella
    $disk->makeDirectory($dst);

    // copia ogni oggetto
    $paginator = $s3->getPaginator('ListObjectsV2', [
        'Bucket' => $bucket,
        'Prefix' => $src,
    ]);

    foreach ($paginator as $page) {
        if (!empty($page['Contents'])) {
            foreach ($page['Contents'] as $obj) {
                $from = $obj['Key'];
                $to   = $dst . Str::after($from, $src);

                $s3->copyObject([
                    'Bucket'     => $bucket,
                    'Key'        => $to,
                    'CopySource' => "$bucket/$from",
                ]);
            }
        }
    }

    // ora elimina il vecchio prefisso usando la delete qui sopra
    $fake = NovaRequest::create('', 'POST', [
        'resourceName' => $request->input('resourceName'),
        'resourceId'   => $request->input('resourceId'),
        'path'         => $oldKey,
    ]);
    return $this->delete($fake);   // risponde già in JSON
}


    /**
     * Scarica un file.
     */
    public function download(NovaRequest $request)
    {
        try {
            $validated = $request->validate(['path' => 'required|string']);

            $model = $this->getResourceModel($request);
            $diskName = $model->resourceFileManagerDisk();

            $path = $validated['path'];
            if (Str::contains($path, '..')) {
                abort(403, 'Invalid path.');
            }

            $realPath = $path;

            if (!Storage::disk($diskName)->exists($realPath)) {
                abort(404, 'File not found.');
            }

            return Storage::disk($diskName)->download($realPath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred on the server.'], 500);
        }
    }

    /**
     * Genera breadcrumb per la navigazione.
     */
    private function generateBreadcrumbs($rootPath, $path)
    {
        // Remove the root path from the start of the current path to get the relative path
        $relativePath = Str::startsWith($path, $rootPath)
            ? Str::after($path, $rootPath)
            : $path;

        $relativePath = trim($relativePath, '/');

        if (empty($relativePath)) {
            return [];
        }

        $crumbs = collect(explode('/', $relativePath))->filter();
        $breadcrumbs = [];
        $currentPath = '';

        foreach ($crumbs as $crumb) {
            $currentPath = empty($currentPath) ? $crumb : $currentPath . '/' . $crumb;
            $breadcrumbs[] = ['name' => $crumb, 'path' => $rootPath . '/' . $currentPath];
        }

        return $breadcrumbs;
    }

    public function assignType(NovaRequest $request)
    {
        $request->validate([
            'path' => 'required|string',
            'type' => 'required|string',
        ]);

        try {
            $model = $this->getResourceModel($request);
            $diskName = $model->resourceFileManagerDisk();
            $disk = Storage::disk($diskName);

            // Ottieni il percorso del file o della cartella
            $path = $request->input('path');
            if (!$disk->exists($path)) {
                return response()->json(['error' => 'File non trovato'], 404);
            }

            // Aggiungi il tag 'type'
            $tags = [
                [
                    'Key' => 'type',
                    'Value' => $request->input('type'),
                ],
            ];

            // Crea un client S3 (o MinIO)
            $client = new S3Client([
                'version' => 'latest',
                'region' => config("filesystems.disks.$diskName.region"),
                'endpoint' => config("filesystems.disks.$diskName.endpoint"),
                'credentials' => [
                    'key' => config("filesystems.disks.$diskName.key"),
                    'secret' => config("filesystems.disks.$diskName.secret"),
                ],
                'use_path_style_endpoint' => true,  // Essenziale per MinIO
            ]);

            $bucket = config("filesystems.disks.$diskName.bucket");

            // Aggiungi o aggiorna il tag sul file
            $client->putObjectTagging([
                'Bucket' => $bucket,
                'Key' => $path,
                'Tagging' => [
                    'TagSet' => $tags,
                ],
            ]);

            return response()->json(['message' => 'Tipo assegnato con successo!']);
        } catch (\Aws\Exception\AwsException $e) {
            return response()->json(['error' => 'Errore nell\'assegnazione del tipo.'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Errore inaspettato nell\'assegnazione del tipo.'], 500);
        }
    }


}
