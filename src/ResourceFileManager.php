<?php

namespace Elsed\ResourceFileManager;

use Laravel\Nova\ResourceTool;
use Closure;

class ResourceFileManager extends ResourceTool
{
    protected ?Closure $filesystemCallback = null;

    /**
     * Imposta le opzioni per il tipo di file,
     * e le rende disponibili al front-end via `meta`.
     */
    public function assignType(array $options): self
    {
        return $this->withMeta([
            'typeOptions' => $options,
        ]);
    }

    public function name()
    {
        return 'Resource File Manager';
    }

    public function component()
    {
        return 'resource-file-manager';
    }

    public function filesystem(Closure $callback): self
    {
        $this->filesystemCallback = $callback;
        return $this;
    }

    public function resolveFilesystem($request)
    {
        if ($this->filesystemCallback) {
            return call_user_func($this->filesystemCallback, $request);
        }
        return \Storage::disk('minio');
    }
}
