<?php

namespace Elsed\ResourceFileManager;

use Laravel\Nova\ResourceTool;
use Closure;

class ResourceFileManager extends ResourceTool
{
    protected ?Closure $filesystemCallback = null;
    protected array $typeOptions = []; // Per salvare le opzioni dinamiche dei tipi

    /**
     * Aggiunge le opzioni per il tipo (passate dal componente padre).
     *
     * @param array $options
     * @return $this
     */
    public function typeOptions(array $options): self
    {
        $this->typeOptions = $options;
        return $this;
    }

    /**
     * Rende le opzioni disponibili per Vue.
     *
     * @param $request
     * @return array
     */
    public function resolveTypeOptions($request): array
    {
        return $this->typeOptions;
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

    public function hasCustomFilesystem(): bool
    {
        return (bool) $this->filesystemCallback;
    }
}
