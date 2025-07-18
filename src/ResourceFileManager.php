<?php

namespace Elsed\ResourceFileManager;

use Laravel\Nova\ResourceTool;
use Closure;
use Laravel\Nova\Fields\Field;

class ResourceFileManager extends ResourceTool
{
    /**
     * Get the displayable name of the resource tool.
     *
     * @return string
     */
    public function name()
    {
        return 'Resource File Manager';
    }

    /**
     * Get the component name for the resource tool.
     *
     * @return string
     */
    public function component()
    {
        return 'resource-file-manager';
    }
    protected ?Closure $filesystemCallback = null;

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
