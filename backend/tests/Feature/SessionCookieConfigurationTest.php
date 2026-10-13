<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionCookieConfigurationTest extends TestCase
{
    public function test_invalid_cookie_path_falls_back_to_the_site_root(): void
    {
        $this->assertSame('/', $this->sessionConfigurationWithPath('C:\\temp\\sessions')['path']);
    }

    public function test_valid_cookie_path_is_preserved(): void
    {
        $this->assertSame('/portal', $this->sessionConfigurationWithPath('/portal')['path']);
    }

    private function sessionConfigurationWithPath(string $path): array
    {
        $hadEnv = array_key_exists('SESSION_PATH', $_ENV);
        $previousEnv = $_ENV['SESSION_PATH'] ?? null;
        $hadServer = array_key_exists('SESSION_PATH', $_SERVER);
        $previousServer = $_SERVER['SESSION_PATH'] ?? null;
        $previousProcessValue = getenv('SESSION_PATH');

        $_ENV['SESSION_PATH'] = $path;
        $_SERVER['SESSION_PATH'] = $path;
        putenv('SESSION_PATH='.$path);

        try {
            return require config_path('session.php');
        } finally {
            if ($hadEnv) {
                $_ENV['SESSION_PATH'] = $previousEnv;
            } else {
                unset($_ENV['SESSION_PATH']);
            }

            if ($hadServer) {
                $_SERVER['SESSION_PATH'] = $previousServer;
            } else {
                unset($_SERVER['SESSION_PATH']);
            }

            putenv($previousProcessValue === false ? 'SESSION_PATH' : 'SESSION_PATH='.$previousProcessValue);
        }
    }
}
