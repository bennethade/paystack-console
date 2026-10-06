<?php

namespace App\Services\Paystack;

use App\Services\Paystack\Data\PaystackRequest;

/**
 * Generates a copy-pasteable cURL command. The key is ALWAYS a placeholder.
 * (The browser builds the same command live from the form; this server-side
 * version is used for history entries and tests.)
 */
class CurlGenerator
{
    public function generate(PaystackRequest $request, ?array $displayBody = null): string
    {
        $placeholder = $request->auth === 'public' ? 'YOUR_PUBLIC_KEY' : 'YOUR_SECRET_KEY';

        $lines = [
            sprintf('curl -X %s %s', $request->method, $this->quote($request->url())),
            sprintf('  -H %s', $this->quote("Authorization: Bearer {$placeholder}")),
        ];

        $body = $displayBody ?? $request->displayBody();

        if ($request->hasBody()) {
            if ($request->files !== []) {
                foreach ($body as $name => $value) {
                    $isFile = array_key_exists($name, $request->files);
                    $content = $isFile
                        ? '@/path/to/'.$request->files[$name]->getClientOriginalName()
                        : (is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
                    $lines[] = sprintf('  -F %s', $this->quote("{$name}={$content}"));
                }
            } else {
                $lines[] = sprintf('  -H %s', $this->quote('Content-Type: application/json'));
                $lines[] = sprintf('  -d %s', $this->quote(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
            }
        }

        return implode(" \\\n", $lines);
    }

    /**
     * POSIX single-quote escaping.
     */
    private function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
