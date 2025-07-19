<?php

namespace Elsed\ResourceFileManager;

use Laravel\Nova\ResourceTool;
use Closure;

class ResourceFileManager extends ResourceTool 
{
    protected ?Closure $filesystemCallback = null;

    /**
     * Costruttore: fissa il nome del componente Vue.
     */
    public function __construct()
    {
        parent::__construct();
        $this->withMeta([
            'name' => $this->name(),
            'typeOptions' => [], // default
        ]);
    }

    public function name(): string
    {
        return 'Resource File Manager';
    }

    public function component(): string
    {
        return 'resource-file-manager';
    }

    /**
     * Permette di passare le opzioni di tipo dinamiche.
     */
    public function typeOptions(array $options): self
    {
        return $this->withMeta([
            'typeOptions' => $options,
        ]);
    }

    /**
     * Se necessario, permette di cambiare filesystem via callback.
     */
    public function filesystem(Closure $callback): self
    {
        $this->filesystemCallback = $callback;
        return $this;
    }

    public function resolveFilesystem($request)
    {
        if ($this->filesystemCallback) {
            return \call_user_func($this->filesystemCallback, $request);
        }
        return \Storage::disk('minio');
    }

    public function hasCustomFilesystem(): bool
    {
        return (bool) $this->filesystemCallback;
    }
}
