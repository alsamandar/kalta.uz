<?php

namespace Tests\Feature;

use App\Http\Middleware\CloudflareClientIp;
use Illuminate\Http\Request;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    private function ipAfterMiddleware(array $server): string
    {
        $request = Request::create('/', 'GET', [], [], [], $server);
        (new CloudflareClientIp)->handle($request, fn () => response('ok'));

        return $request->ip();
    }

    public function test_the_real_visitor_ip_is_used_when_the_request_comes_through_cloudflare(): void
    {
        $this->assertSame('203.0.113.9', $this->ipAfterMiddleware(['REMOTE_ADDR' => '173.245.48.5', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9']));
        $this->assertSame('203.0.113.9', $this->ipAfterMiddleware(['REMOTE_ADDR' => '188.114.96.3', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9']));
        $this->assertSame('2001:db8::7', $this->ipAfterMiddleware(['REMOTE_ADDR' => '2a06:98c1:3120::2', 'HTTP_CF_CONNECTING_IP' => '2001:db8::7']));
    }

    public function test_the_header_is_ignored_when_it_does_not_come_from_cloudflare(): void
    {
        // somebody talking to the server directly cannot pick their own IP
        $this->assertSame('198.51.100.7', $this->ipAfterMiddleware(['REMOTE_ADDR' => '198.51.100.7', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9']));
        $this->assertSame('173.245.48.5', $this->ipAfterMiddleware(['REMOTE_ADDR' => '173.245.48.5', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip']));
        $this->assertSame('173.245.48.5', $this->ipAfterMiddleware(['REMOTE_ADDR' => '173.245.48.5']));
    }

    public function test_every_translatable_string_in_the_code_exists_in_uzbek_and_russian(): void
    {
        $keys = [];
        $roots = [app_path(), resource_path('views'), base_path('routes')];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all('/(?:__|trans)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/s', file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
                foreach ($matches as $m) {
                    $key = stripslashes($m[1] !== '' ? $m[1] : ($m[2] ?? ''));
                    if ($key !== '' && !preg_match('/^[a-z_]+\.[a-z_.]+$/', $key)) { // skip "validation.required"-style keys
                        $keys[$key] = true;
                    }
                }
            }
        }

        $this->assertGreaterThan(300, count($keys), 'the scan should find the site\'s strings');

        foreach (['uz', 'ru'] as $locale) {
            $translations = json_decode(file_get_contents(lang_path("$locale.json")), true, 512, JSON_THROW_ON_ERROR);
            $missing = array_values(array_diff(array_keys($keys), array_keys($translations)));
            $this->assertSame([], $missing, "Missing $locale translations");

            foreach ($translations as $key => $value) {
                $this->assertSame(
                    preg_match_all('/:[A-Za-z]+/', $key), preg_match_all('/:[A-Za-z]+/', $value),
                    "$locale translation of \"$key\" must keep its :placeholders"
                );
                $this->assertNotSame('', trim($value), "$locale translation of \"$key\" is empty");
            }
        }
    }

    public function test_uzbek_uses_the_proper_apostrophes(): void
    {
        $uz = file_get_contents(lang_path('uz.json'));

        $this->assertStringNotContainsString("o'", $uz, 'use oʻ (U+02BB), not o\'');
        $this->assertStringNotContainsString("g'", $uz);
        $this->assertStringNotContainsString("\u{2018}", $uz, 'use U+02BB/U+02BC, not a curly quote');
    }
}
