<?php

namespace Rolandstarke\Thumbnail;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Source
{
    protected $path;
    protected $disk;
    protected $allowedSources = [];

    public $urlParams;

    public function __construct(array $allowedSources)
    {
        $this->allowedSources = $allowedSources;
    }

    /**
     * @throws Exception
     */
    public function src(string $path, string $disk = null): self
    {
        $this->urlParams = $this->constructUrlParams($path, $disk);
        if (!$this->urlParams) {
            throw new Exception('Source is not allowed. Given path "' . $path . '"' . ($disk ? ' on disk "' . $disk . '"' : ''));
        }

        if (Str::contains($this->urlParams['p'], '..')) {
            throw new Exception('Source is not allowed. The Path can not contain "..". Given path "' . $path . '"');
        }

        $this->path = $path;
        $this->disk = $disk;

        return $this;
    }

    protected function constructUrlParams(string $path, string $disk = null): ?array
    {
        $params = null;

        foreach ($this->allowedSources as $sourceKey => $allowedSource) {
            if ($disk) {
                if (Arr::get($allowedSource, 'disk') !== $disk) {
                    continue;
                }
                $allowedPath = $allowedSource['path'];
            } else {
                if (is_array($allowedSource)) {
                    continue;
                }
                $allowedPath = $allowedSource;
            }

            if (Str::startsWith($path, $allowedPath)) {
                $relativePath = substr($path, strlen($allowedPath));
                if (!$params || strlen($params['p']) > strlen($relativePath)) {
                    $params = ['p' => $relativePath, 's' => $sourceKey];
                }
            }
        }

        return $params;
    }

    /**
     * @throws Exception
     */
    public function setSrcFromUrlParams(array $urlParams): self
    {
        $path = '';
        $disk = null;

        if (isset($this->allowedSources[$urlParams['s']])) {
            $source = $this->allowedSources[$urlParams['s']];
            if (is_array($source)) {
                $disk = $source['disk'];
                $path = $source['path'];
            } else {
                $path = $source;
            }
            $path .= Arr::get($urlParams, 'p', '');
        }

        $this->src($path, $disk);

        return $this;
    }


    public function getFormat(): string
    {
        if (preg_match('/\\.(\\w+)($|[?#])/i', $this->path, $matches)) {
            return strtolower($matches[1]);
        }
        return 'jpg';
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException|Exception
     */
    public function getImage(): string
    {
        if ($this->disk) {
            return Storage::disk($this->disk)->get($this->path);
        } else {
            $url = $this->path;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HEADER , false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST , false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION , true);
            curl_setopt($ch, CURLOPT_AUTOREFERER , true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            $content = curl_exec($ch);
            curl_close($ch);


            
            if ($content === false) {
                throw new Exception('Could not get file content for path "' . $this->path . '"');
            }
            return $content;
        }
    }
}
